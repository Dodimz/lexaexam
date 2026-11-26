<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_exam_section_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_exam_session_id')->constrained('student_exam_sessions')->cascadeOnDelete();
            $table->foreignId('exam_section_id')->constrained('exam_sections')->cascadeOnDelete();
            $table->decimal('score', 8, 2)->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('answered_count')->default(0);
            $table->boolean('passed')->default(false);
            $table->timestamps();

            $table->unique(['student_exam_session_id', 'exam_section_id'], 'session_section_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_exam_section_scores');
    }
};
