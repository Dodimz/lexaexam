<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Question;
use App\Services\AI\QuestionGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function index($examId)
    {
        $exam = Exam::with(['category', 'sections'])->withCount('questions')->findOrFail($examId);
        
        $questions = Question::where('exam_id', $examId)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $sectionCounts = Question::where('exam_id', $examId)
            ->whereNotNull('exam_section_id')
            ->select('exam_section_id', DB::raw('count(*) as total'))
            ->groupBy('exam_section_id')
            ->pluck('total', 'exam_section_id');

        $codingAssessments = class_exists('\Addons\CodingAssessments\Models\CodingAssessment') 
            ? \Addons\CodingAssessments\Models\CodingAssessment::all() 
            : collect();

        return view('admin.exams.questions.index', compact('exam', 'questions', 'codingAssessments', 'sectionCounts'));
    }

    public function generate(Request $request, QuestionGeneratorService $aiService, $examId)
    {
        $request->validate([
            'topic'      => 'required|string|max:255',
            'count'      => 'required|integer|min:1|max:50',
            'difficulty' => 'required|in:easy,normal,hard',
            'type'       => 'required|in:mcq,true_false',
            'provider'   => 'required|in:custom,gemini',
        ]);
        
        $result = $aiService->generate(
            $request->topic,
            $request->count,
            $request->difficulty,
            $request->type,
            $request->provider
        );

        if ($result['status'] === 'error') {
            return response()->json(['status' => 'error', 'message' => $result['message']], 422);
        }

        return response()->json([
            'status' => 'success',
            'questions' => $result['questions']
        ]);
    }
    
    public function store(Request $request, $examId) 
    {
        if ($request->has('questions') && is_array($request->questions)) {
             return $this->storeBatch($request, $examId);
        }

        $section = $this->sectionForRequest($examId, $request->exam_section_id);
        $optionScored = $section && $section->scoring_mode === 'option_score';
        $correctRule = ($optionScored || $request->type === 'coding') ? 'nullable' : 'required';

        $validator = Validator::make($request->all(), [
            'exam_id' => 'required|integer|in:' . $examId,
            'question_text' => 'required|string',
            'type' => 'required|in:mcq,true_false,short_answer,coding',
            'correct_answer' => $correctRule . '|string|max:255',
            'options' => 'nullable|array',
            'explanation' => 'nullable|string',
            'exam_section_id' => 'nullable|integer',
            'option_scores' => 'nullable|array',
            'option_scores.*' => 'integer|min:1|max:5',
            'allowed_languages' => 'required_if:type,coding|array',
            'test_cases' => 'required_if:type,coding|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $optionScores = $this->normalizedScores($request->option_scores);
        if ($optionScored && count($optionScores) !== 5) {
            return response()->json(['status' => 'error', 'message' => 'TKP memerlukan skor untuk opsi A–E (1–5).'], 422);
        }
        
        $isMcq = $request->type === 'mcq';
        $isCoding = $request->type === 'coding';

        $options = null;
        if ($isMcq) {
            $options = $request->options;
        } elseif ($isCoding) {
            $options = [
                'allowed_languages' => $request->allowed_languages,
                'test_cases' => $request->test_cases
            ];
        }

        $question = Question::create([
            'exam_id'        => $examId,
            'exam_section_id'=> $section ? $section->id : null,
            'question_text'  => $request->question_text,
            'type'           => $request->type,
            'correct_answer' => $isCoding ? 'coding_submission' : ($request->correct_answer ?? ''),
            'explanation'    => $request->explanation,
            'options'        => $options,
            'option_scores'  => $optionScored ? $optionScores : null,
        ]);

        return response()->json([
            'status' => 'success', 
            'message' => __('questions.save_success'), 
            'question_id' => $question->id
        ]);
    }

    public function import(Request $request, $examId)
    {
        try {
            $exam = Exam::with('sections')->findOrFail($examId);
            $sectionsByCode = $exam->sections->keyBy('code');
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:20480'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'message' => $validator->errors()->first('file')]);
            }

            $extension = strtolower($request->file('file')->getClientOriginalExtension());
            if (!in_array($extension, ['csv', 'txt'])) {
                return response()->json(['status' => 'error', 'message' => 'Only CSV or TXT files are allowed.']);
            }

            set_time_limit(0);
            ini_set('memory_limit', '512M');

            $filePath = $request->file('file')->getRealPath();
            $handle = fopen($filePath, "r");
            
            if (!$handle) {
                return response()->json(['status' => 'error', 'message' => 'Failed to open the uploaded file.']);
            }

            $header = fgetcsv($handle, 10000, ",");
            
            if (!$header || empty(array_filter($header))) {
                fclose($handle);
                return response()->json(['status' => 'error', 'message' => 'Invalid or empty CSV format.']);
            }

            $header[0] = preg_replace('/[\xef\xbb\xbf]/', '', $header[0]);
            $header = array_map('trim', $header);
            $header = array_map('strtolower', $header);

            $required = ['question_text', 'type', 'correct_answer'];
            foreach ($required as $req) {
                if (!in_array($req, $header)) {
                    fclose($handle);
                    return response()->json(['status' => 'error', 'message' => "CSV missing required column: {$req}"]);
                }
            }

            $chunkSize = 500;
            $questions = [];
            $totalImported = 0;
            $now = now();
            $line = 1;
            $importErrors = [];

            DB::beginTransaction();

            while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                $line++;
                if (empty(array_filter($data))) continue;

                if (count($data) < count($header)) {
                    $data = array_pad($data, count($header), '');
                } elseif (count($data) > count($header)) {
                    $data = array_slice($data, 0, count($header));
                }

                $row = array_combine($header, $data);
                
                $type = strtolower(trim($row['type'] ?? 'mcq'));
                if (!in_array($type, ['mcq', 'true_false', 'short_answer'])) {
                    $type = 'mcq';
                }

                $options = null;
                if ($type === 'mcq' && !empty($row['options'])) {
                    $rawOptions = explode('|', $row['options']);
                    $formattedOptions = [];
                    $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
                    foreach ($rawOptions as $index => $val) {
                        if (isset($letters[$index]) && trim($val) !== '') {
                            $formattedOptions[$letters[$index]] = trim($val);
                        }
                    }
                    $options = json_encode($formattedOptions);
                }

                $section = null;
                $optionScores = null;
                $sectionCode = strtoupper(trim($row['section'] ?? ''));
                if ($sectionCode !== '') {
                    $section = $sectionsByCode->get($sectionCode);
                    if (!$section) {
                        $importErrors[] = "Baris {$line}: kode subtes '{$sectionCode}' tidak dikenal.";
                    } elseif ($section->scoring_mode === 'option_score') {
                        $optionScores = $this->parseOptionScores($row['option_scores'] ?? '');
                        if (count($optionScores) !== 5) {
                            $importErrors[] = "Baris {$line}: TKP memerlukan 5 skor opsi (1–5) dipisah '|'.";
                        }
                    }
                }

                if (count($importErrors) >= 10) break;

                $questions[] = [
                    'exam_id'        => $examId,
                    'exam_section_id'=> $section ? $section->id : null,
                    'question_text'  => $row['question_text'] ?? 'Untitled Question',
                    'type'           => $type,
                    'correct_answer' => trim($row['correct_answer'] ?? ''),
                    'explanation'    => $row['explanation'] ?? null,
                    'options'        => $options,
                    'option_scores'  => $optionScores ? json_encode($optionScores) : null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];

                if (count($questions) >= $chunkSize) {
                    Question::insert($questions);
                    $totalImported += count($questions);
                    $questions = [];
                }
            }

            if (count($importErrors) > 0) {
                DB::rollBack();
                fclose($handle);
                return response()->json([
                    'status' => 'error',
                    'message' => implode(' ', array_slice($importErrors, 0, 5))
                ]);
            }

            if (count($questions) > 0) {
                Question::insert($questions);
                $totalImported += count($questions);
            }

            DB::commit();
            fclose($handle);

            $counts = Question::where('exam_id', $examId)
                ->whereNotNull('exam_section_id')
                ->select('exam_section_id', DB::raw('count(*) as total'))
                ->groupBy('exam_section_id')
                ->pluck('total', 'exam_section_id');

            $summary = $exam->sections
                ->map(fn ($section) => $section->code . ': ' . ($counts[$section->id] ?? 0) . '/' . $section->question_count)
                ->implode(' | ');

            return response()->json([
                'status' => 'success',
                'message' => $totalImported . ' questions imported successfully.' . ($summary ? ' (' . $summary . ')' : '')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
            return response()->json([
                'status' => 'error', 
                'message' => 'System Error: ' . $e->getMessage() . ' at line ' . $e->getLine()
            ]);
        }
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="question_template.csv"',
        ];

        $columns = ['question_text', 'type', 'correct_answer', 'options', 'explanation', 'section', 'option_scores'];

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fputcsv($file, [
                'What is the capital of France?',
                'mcq',
                'A',
                'Paris|London|Berlin|Madrid',
                'Paris is the capital and most populous city of France.',
                'TWK',
                ''
            ]);
            fputcsv($file, [
                '2 + 2 = ?',
                'mcq',
                'B',
                '3|4|5|6',
                '',
                'TIU',
                ''
            ]);
            fputcsv($file, [
                'You notice a colleague struggling with a task. What do you do?',
                'mcq',
                '',
                'Ignore|Offer help|Report them|Laugh|Wait',
                'TKP uses option scores 1-5, matching options A-E.',
                'TKP',
                '1|5|3|2|4'
            ]);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function update(Request $request, $examId, $questionId) 
    {
        $section = $this->sectionForRequest($examId, $request->exam_section_id);
        $optionScored = $section && $section->scoring_mode === 'option_score';
        $correctRule = ($optionScored || $request->type === 'coding') ? 'nullable' : 'required';

        $request->validate([
            'question_text' => 'required|string',
            'type' => 'required|in:mcq,true_false,short_answer,coding',
            'correct_answer' => $correctRule . '|string|max:255',
            'options' => 'nullable|array',
            'explanation' => 'nullable|string',
            'exam_section_id' => 'nullable|integer',
            'option_scores' => 'nullable|array',
            'option_scores.*' => 'integer|min:1|max:5',
            'allowed_languages' => 'required_if:type,coding|array',
            'test_cases' => 'required_if:type,coding|array'
        ]);

        $optionScores = $this->normalizedScores($request->option_scores);
        if ($optionScored && count($optionScores) !== 5) {
            return response()->json(['status' => 'error', 'message' => 'TKP memerlukan skor untuk opsi A–E (1–5).'], 422);
        }
        
        $question = Question::where('exam_id', $examId)->findOrFail($questionId);
        
        $isMcq = $request->type === 'mcq';
        $isCoding = $request->type === 'coding';

        $options = null;
        if ($isMcq) {
            $options = $request->options;
        } elseif ($isCoding) {
            $options = [
                'allowed_languages' => $request->allowed_languages,
                'test_cases' => $request->test_cases
            ];
        }

        $question->update([
            'exam_section_id'=> $section ? $section->id : null,
            'question_text'  => $request->question_text,
            'type'           => $request->type,
            'correct_answer' => $isCoding ? 'coding_submission' : ($request->correct_answer ?? ''),
            'explanation'    => $request->explanation,
            'options'        => $options,
            'option_scores'  => $optionScored ? $optionScores : null,
        ]);
        
        return response()->json(['status' => 'success', 'message' => __('questions.update_success')]);
    }

    protected function storeBatch(Request $request, $examId) 
    {
        $validator = Validator::make($request->all(), [
            'questions' => 'required|array|min:1',
            'questions.*.question_text' => 'required|string',
            'questions.*.type' => 'required|in:mcq,true_false,short_answer,coding',
            'questions.*.correct_answer' => 'required_unless:questions.*.type,coding|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $chunkSize = 500;
        $questionsToInsert = [];
        $now = now();

        DB::beginTransaction();
        try {
            foreach ($request->questions as $q) {
                $isMcq = isset($q['type']) && $q['type'] === 'mcq';
                $isCoding = isset($q['type']) && $q['type'] === 'coding';
                
                $options = null;
                if ($isMcq && isset($q['options'])) {
                    $options = json_encode($q['options']);
                } elseif ($isCoding && isset($q['coding_data'])) {
                    $options = json_encode($q['coding_data']);
                }

                $questionsToInsert[] = [
                    'exam_id'        => $examId,
                    'question_text'  => $q['question_text'],
                    'type'           => $q['type'],
                    'options'        => $options, 
                    'correct_answer' => $isCoding ? 'coding_submission' : $q['correct_answer'],
                    'explanation'    => $q['explanation'] ?? null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];

                if (count($questionsToInsert) >= $chunkSize) {
                    Question::insert($questionsToInsert);
                    $questionsToInsert = [];
                }
            }

            if (count($questionsToInsert) > 0) {
                Question::insert($questionsToInsert);
            }
            DB::commit();
            return response()->json(['status' => 'success', 'message' => __('questions.save_success')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    
    public function destroy($examId, $questionId) 
    {
        $question = Question::where('exam_id', $examId)->findOrFail($questionId);
        $question->delete();
        return redirect()->back()->with('success', __('questions.delete_success'));
    }

    private function sectionForRequest($examId, $sectionId): ?ExamSection
    {
        if (!$sectionId) return null;
        return ExamSection::where('exam_id', $examId)->findOrFail($sectionId);
    }

    private function normalizedScores($scores): array
    {
        $result = [];
        foreach (['A', 'B', 'C', 'D', 'E'] as $letter) {
            if (isset($scores[$letter]) && $scores[$letter] !== '') {
                $result[$letter] = (int) $scores[$letter];
            }
        }
        return $result;
    }

    private function parseOptionScores($value): array
    {
        if (trim((string) $value) === '') return [];

        $letters = ['A', 'B', 'C', 'D', 'E'];
        $scores = [];
        foreach (explode('|', $value) as $index => $part) {
            if (!isset($letters[$index])) break;
            $part = trim($part);
            if ($part === '') continue;
            $score = (int) $part;
            if ($score < 1 || $score > 5) return [];
            $scores[$letters[$index]] = $score;
        }
        return $scores;
    }
}