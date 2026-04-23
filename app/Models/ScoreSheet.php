<?php

namespace App\Models;

use App\Domain\Debate\Enums\ScoreSheetState;
use App\Domain\Debate\Enums\TeamSide;
use Database\Factories\ScoreSheetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreSheet extends Model
{
    /** @use HasFactory<ScoreSheetFactory> */
    use HasFactory;

    protected $fillable = [
        'match_id',
        'judge_id',
        'mark_pm',
        'mark_tpm',
        'mark_m1',
        'mark_kp',
        'mark_tkp',
        'mark_p1',
        'mark_penggulungan_gov',
        'mark_penggulungan_opp',
        'gov_total',
        'opp_total',
        'margin',
        'winner_side',
        'best_debater_member_id',
        'state',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'winner_side' => TeamSide::class,
            'state' => ScoreSheetState::class,
            'submitted_at' => 'datetime',
            'mark_pm' => 'decimal:1',
            'mark_tpm' => 'decimal:1',
            'mark_m1' => 'decimal:1',
            'mark_kp' => 'decimal:1',
            'mark_tkp' => 'decimal:1',
            'mark_p1' => 'decimal:1',
            'mark_penggulungan_gov' => 'decimal:1',
            'mark_penggulungan_opp' => 'decimal:1',
            'gov_total' => 'decimal:1',
            'opp_total' => 'decimal:1',
            'margin' => 'decimal:1',
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

    public function bestDebater(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'best_debater_member_id');
    }
}
