<?php

namespace App\Models;

use App\Domain\Debate\Enums\MatchStatus;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected $appends = ['roster_locked'];

    protected $fillable = ['name', 'institution', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class)->orderBy('speaker_position');
    }

    public function getRosterLockedAttribute(): bool
    {
        return DebateMatch::query()
            ->where(function ($query): void {
                $query->where('government_team_id', $this->id)
                    ->orWhere('opposition_team_id', $this->id);
            })
            ->where('status', '!=', MatchStatus::Pending->value)
            ->exists();
    }
}
