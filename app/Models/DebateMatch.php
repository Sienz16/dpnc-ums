<?php

namespace App\Models;

use App\Domain\Debate\Enums\JudgePanelSize;
use App\Domain\Debate\Enums\MatchCompletionType;
use App\Domain\Debate\Enums\MatchResultState;
use App\Domain\Debate\Enums\MatchStatus;
use Database\Factories\DebateMatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DebateMatch extends Model
{
    /** @use HasFactory<DebateMatchFactory> */
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'round_id',
        'room_id',
        'government_team_id',
        'opposition_team_id',
        'judge_panel_size',
        'status',
        'completion_type',
        'result_state',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'judge_panel_size' => JudgePanelSize::class,
            'status' => MatchStatus::class,
            'completion_type' => MatchCompletionType::class,
            'result_state' => MatchResultState::class,
            'scheduled_at' => 'datetime',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function governmentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'government_team_id');
    }

    public function oppositionTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'opposition_team_id');
    }

    public function judgeAssignments(): HasMany
    {
        return $this->hasMany(JudgeAssignment::class, 'match_id');
    }

    public function scoreSheets(): HasMany
    {
        return $this->hasMany(ScoreSheet::class, 'match_id');
    }

    public function matchSpeakers(): HasMany
    {
        return $this->hasMany(MatchSpeaker::class, 'match_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(MatchResult::class, 'match_id');
    }
}
