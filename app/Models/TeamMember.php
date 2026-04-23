<?php

namespace App\Models;

use App\Domain\Debate\Enums\SpeakerPosition;
use Database\Factories\TeamMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    /** @use HasFactory<TeamMemberFactory> */
    use HasFactory;

    protected $appends = ['speaker_position_label'];

    protected $fillable = ['team_id', 'full_name', 'speaker_position', 'is_active'];

    protected function casts(): array
    {
        return [
            'speaker_position' => SpeakerPosition::class,
            'is_active' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function getSpeakerPositionLabelAttribute(): string
    {
        return $this->speaker_position->label();
    }
}
