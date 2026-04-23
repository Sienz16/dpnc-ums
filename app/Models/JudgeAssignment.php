<?php

namespace App\Models;

use App\Domain\Debate\Enums\JudgeAssignmentMode;
use Database\Factories\JudgeAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JudgeAssignment extends Model
{
    /** @use HasFactory<JudgeAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'match_id',
        'judge_id',
        'assigned_mode',
        'checked_in_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_mode' => JudgeAssignmentMode::class,
            'checked_in_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(DebateMatch::class, 'match_id');
    }

    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_id');
    }
}
