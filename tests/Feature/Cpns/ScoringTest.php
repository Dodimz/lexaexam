<?php

namespace Tests\Feature\Cpns;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Question;
use App\Models\StudentExamAnswer;
use App\Models\StudentExamSession;
use App\Models\User;
use App\Services\Exam\CpnsScoringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_cpns_scoring_uses_section_rules_and_requires_all_sections_to_pass(): void
    {
        $exam = $this->createCpnsExam([
            ['code' => 'TWK', 'question_count' => 2, 'max_score' => 10, 'passing_score' => 5, 'scoring_mode' => 'fixed_correct', 'points_per_correct' => 5],
            ['code' => 'TIU', 'question_count' => 1, 'max_score' => 5, 'passing_score' => 5, 'scoring_mode' => 'fixed_correct', 'points_per_correct' => 5],
            ['code' => 'TKP', 'question_count' => 1, 'max_score' => 5, 'passing_score' => 4, 'scoring_mode' => 'option_score', 'points_per_correct' => 0],
        ]);

        $sections = $exam->sections->keyBy('code');
        $twkCorrect = $this->createQuestion($exam, $sections['TWK']->id, 'A');
        $this->createQuestion($exam, $sections['TWK']->id, 'B');
        $tiuCorrect = $this->createQuestion($exam, $sections['TIU']->id, 'B');
        $tkp = Question::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $sections['TKP']->id,
            'question_text' => 'TKP question',
            'type' => 'mcq',
            'options' => ['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E'],
            'option_scores' => ['A' => 1, 'B' => 4, 'C' => 3, 'D' => 2, 'E' => 5],
            'correct_answer' => '',
        ]);

        $user = User::factory()->create(['password' => bcrypt('password')]);
        $session = StudentExamSession::create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'status' => 'completed',
            'total_questions' => 4,
            'start_time' => Carbon::now()->subMinutes(10),
            'end_time' => Carbon::now(),
        ]);

        foreach ([[$twkCorrect, 'A'], [$tiuCorrect, 'B'], [$tkp, 'B']] as [$question, $selected]) {
            StudentExamAnswer::create([
                'student_exam_session_id' => $session->id,
                'user_id' => $user->id,
                'exam_id' => $exam->id,
                'question_id' => $question->id,
                'selected_option_id' => $selected,
            ]);
        }

        app(CpnsScoringService::class)->score($session);
        $session->refresh();

        $this->assertEquals(14, $session->score);
        $this->assertTrue($session->is_passed);

        $scores = $session->sectionScores->keyBy(fn ($score) => $score->section->code);
        $this->assertEquals(5, $scores['TWK']->score);
        $this->assertEquals(5, $scores['TIU']->score);
        $this->assertEquals(4, $scores['TKP']->score);
        $this->assertTrue($scores['TWK']->passed);
        $this->assertTrue($scores['TIU']->passed);
        $this->assertTrue($scores['TKP']->passed);
    }

    public function test_cpns_start_is_blocked_when_question_counts_do_not_match_sections(): void
    {
        $exam = $this->createCpnsExam();
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->post(route('exam.start', $exam));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('student_exam_sessions', 0);
    }

    private function createCpnsExam(array $sections = []): Exam
    {
        $category = Category::create(['name' => 'CPNS', 'slug' => 'cpns', 'is_active' => true]);

        $exam = Exam::create([
            'title' => 'Simulasi CAT CPNS',
            'slug' => 'simulasi-cat-cpns',
            'category_id' => $category->id,
            'type' => 'cpns',
            'duration_minutes' => 100,
            'pass_percentage' => 100,
            'total_marks' => 550,
            'is_active' => true,
            'is_paid' => false,
        ]);

        $defaults = [
            ['code' => 'TWK', 'question_count' => 30, 'max_score' => 150, 'passing_score' => 65, 'scoring_mode' => 'fixed_correct', 'points_per_correct' => 5],
            ['code' => 'TIU', 'question_count' => 35, 'max_score' => 175, 'passing_score' => 80, 'scoring_mode' => 'fixed_correct', 'points_per_correct' => 5],
            ['code' => 'TKP', 'question_count' => 45, 'max_score' => 225, 'passing_score' => 166, 'scoring_mode' => 'option_score', 'points_per_correct' => 0],
        ];

        foreach (($sections ?: $defaults) as $index => $section) {
            $exam->sections()->create(array_merge($section, [
                'name' => $section['code'],
                'sort_order' => $index + 1,
            ]));
        }

        return $exam->load('sections');
    }

    private function createQuestion(Exam $exam, int $sectionId, string $correct): Question
    {
        return Question::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $sectionId,
            'question_text' => 'Question ' . $correct,
            'type' => 'mcq',
            'options' => ['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D'],
            'correct_answer' => $correct,
        ]);
    }
}
