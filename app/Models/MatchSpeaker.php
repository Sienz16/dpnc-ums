<?php

namespace App\Models;

use App\Domain\Debate\Enums\SpeakerPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchSpeaker extends Model
{
    protected $fillable = [
        'match_id',
        'team_id',
        'team_member_id',
        'speaker_position',
    ];

    protected function casts(): array
    {
        return [
            'speaker_position' => SpeakerPosition::class,
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(DebateMatch::class, 'match_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }
}
