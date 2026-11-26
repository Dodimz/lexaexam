<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $fillable = [
        'exam_id',
        'exam_section_id',
        'question_text',
        'type',
        'options',
        'option_scores',
        'correct_answer',
        'explanation'
    ];

    protected $casts = [
        'options' => 'array',
        'option_scores' => 'array',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function examSection(): BelongsTo
    {
        return $this->belongsTo(ExamSection::class);
    }
}