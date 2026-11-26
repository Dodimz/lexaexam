<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSection extends Model
{
    protected $fillable = [
        'exam_id',
        'code',
        'name',
        'question_count',
        'max_score',
        'passing_score',
        'scoring_mode',
        'points_per_correct',
        'sort_order',
    ];

    protected $casts = [
        'question_count' => 'integer',
        'max_score' => 'decimal:2',
        'passing_score' => 'decimal:2',
        'points_per_correct' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(StudentExamSectionScore::class);
    }
}
