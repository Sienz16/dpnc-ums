<?php

namespace App\Models;

use App\Domain\Debate\Enums\TeamSide;
use Database\Factories\MatchResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchResult extends Model
{
    /** @use HasFactory<MatchResultFactory> */
    use HasFactory;

    protected $fillable = [
        'match_id',
        'winner_side',
        'winner_vote_count',
        'loser_vote_count',
        'official_margin',
        'official_team_score_government',
        'official_team_score_opposition',
        'best_speaker_member_id',
        'is_force_completed',
        'is_provisional',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'winner_side' => TeamSide::class,
            'official_margin' => 'decimal:1',
            'official_team_score_government' => 'decimal:1',
            'official_team_score_opposition' => 'decimal:1',
            'is_force_completed' => 'boolean',
            'is_provisional' => 'boolean',
            'calculated_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(DebateMatch::class, 'match_id');
    }

    public function bestSpeaker(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'best_speaker_member_id');
    }
}
