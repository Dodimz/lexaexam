<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentExamSectionScore extends Model
{
    protected $fillable = [
        'student_exam_session_id',
        'exam_section_id',
        'score',
        'correct_count',
        'answered_count',
        'passed',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'correct_count' => 'integer',
        'answered_count' => 'integer',
        'passed' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(StudentExamSession::class, 'student_exam_session_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ExamSection::class, 'exam_section_id');
    }
}
