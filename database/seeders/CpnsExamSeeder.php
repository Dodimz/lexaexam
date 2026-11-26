<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Exam;
use Illuminate\Database\Seeder;

class CpnsExamSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::firstOrCreate(
            ['slug' => 'cpns'],
            ['name' => 'CPNS', 'is_active' => true]
        );

        $exam = Exam::firstOrCreate(
            ['slug' => 'simulasi-cat-cpns'],
            [
                'title' => 'Simulasi CAT CPNS',
                'category_id' => $category->id,
                'type' => 'cpns',
                'description' => 'Simulasi CAT CPNS dengan subtes TWK, TIU, dan TKP.',
                'duration_minutes' => 100,
                'pass_percentage' => 100,
                'total_marks' => 550,
                'is_active' => true,
                'is_paid' => false,
                'allow_retake' => true,
            ]
        );

        $sections = [
            ['code' => 'TWK', 'name' => 'Tes Wawasan Kebangsaan', 'question_count' => 30, 'max_score' => 150, 'passing_score' => 65, 'scoring_mode' => 'fixed_correct', 'points_per_correct' => 5, 'sort_order' => 1],
            ['code' => 'TIU', 'name' => 'Tes Intelegensia Umum', 'question_count' => 35, 'max_score' => 175, 'passing_score' => 80, 'scoring_mode' => 'fixed_correct', 'points_per_correct' => 5, 'sort_order' => 2],
            ['code' => 'TKP', 'name' => 'Tes Karakteristik Pribadi', 'question_count' => 45, 'max_score' => 225, 'passing_score' => 166, 'scoring_mode' => 'option_score', 'points_per_correct' => 0, 'sort_order' => 3],
        ];

        foreach ($sections as $section) {
            $exam->sections()->updateOrCreate(['code' => $section['code']], $section);
        }
    }
}
