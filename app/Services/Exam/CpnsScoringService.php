<?php

namespace App\Services\Exam;

use App\Models\StudentExamAnswer;
use App\Models\StudentExamSectionScore;
use App\Models\StudentExamSession;

class CpnsScoringService
{
    public function score(StudentExamSession $session): void
    {
        $session->loadMissing(['answers.question', 'exam.sections']);

        $sections = $session->exam->sections;
        $answersByQuestion = $session->answers->keyBy('question_id');
        $totalScore = 0;
        $totalCorrect = 0;
        $totalAnswered = 0;

        foreach ($sections as $section) {
            $questions = $session->exam->questions->where('exam_section_id', $section->id);
            $sectionScore = 0;
            $sectionCorrect = 0;
            $sectionAnswered = 0;

            foreach ($questions as $question) {
                $answer = $answersByQuestion->get($question->id);
                $selected = $answer ? trim((string) $answer->selected_option_id) : '';

                if ($selected === '') {
                    continue;
                }

                $sectionAnswered++;
                $totalAnswered++;
                $awarded = 0;
                $isCorrect = false;

                if ($section->scoring_mode === 'option_score') {
                    $scores = $question->option_scores ?: [];
                    $awarded = (int) ($scores[$selected] ?? 0);
                    $isCorrect = $awarded > 0;
                } else {
                    $isCorrect = $selected === trim((string) $question->correct_answer);
                    $awarded = $isCorrect ? (float) ($section->points_per_correct ?: 5) : 0;
                }

                if ($awarded > 0) {
                    $sectionCorrect++;
                    $totalCorrect++;
                }

                $sectionScore += $awarded;

                if ($answer) {
                    StudentExamAnswer::where('id', $answer->id)->update([
                        'is_correct' => $isCorrect,
                        'marks_awarded' => $awarded,
                    ]);
                }
            }

            $totalScore += $sectionScore;

            StudentExamSectionScore::updateOrCreate(
                [
                    'student_exam_session_id' => $session->id,
                    'exam_section_id' => $section->id,
                ],
                [
                    'score' => $sectionScore,
                    'correct_count' => $sectionCorrect,
                    'answered_count' => $sectionAnswered,
                    'passed' => $sectionScore >= (float) $section->passing_score,
                ]
            );
        }

        $maxScore = (float) ($session->exam->total_marks ?: $sections->sum('max_score'));
        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100) : 0;

        $session->update([
            'score' => $totalScore,
            'progress_percentage' => $percentage,
            'correct_answers' => $totalCorrect,
            'total_answered' => $totalAnswered,
        ]);
    }
}
