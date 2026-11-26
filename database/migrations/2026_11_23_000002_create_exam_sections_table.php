<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('question_count');
            $table->decimal('max_score', 8, 2);
            $table->decimal('passing_score', 8, 2);
            $table->string('scoring_mode')->default('fixed_correct');
            $table->decimal('points_per_correct', 8, 2)->default(5);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['exam_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sections');
    }
};
