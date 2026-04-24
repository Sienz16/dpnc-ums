<?php

namespace App\Domain\Debate\Services;

use App\Domain\Debate\Enums\MatchStatus;
use App\Domain\Debate\Enums\SpeakerPosition;
use App\Domain\Debate\Enums\TeamSide;
use App\Models\DebateMatch;
use App\Models\MatchSpeaker;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MatchLineupService
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function positionForMember(DebateMatch $match, TeamMember $member): ?SpeakerPosition
    {
        $override = $this->matchSpeakers($match)
            ->firstWhere('team_member_id', $member->id);

        if ($override) {
            return $override->speaker_position;
        }

        return $member->speaker_position;
    }

    /**
     * @return Collection<int, TeamMember>
     */
    public function lineupMembers(DebateMatch $match, TeamSide $side): Collection
    {
        $team = $side === TeamSide::Government
            ? $match->governmentTeam
            : $match->oppositionTeam;

        $overrides = $this->matchSpeakers($match)
            ->where('team_id', $team->id)
            ->sortBy(fn (MatchSpeaker $speaker): int => $speaker->speaker_position->slotNumber())
            ->values();

        if ($overrides->isNotEmpty()) {
            return $overrides
                ->map(fn (MatchSpeaker $speaker): TeamMember => $this->memberWithMatchPosition($speaker->teamMember, $speaker->speaker_position))
                ->values();
        }

        /** @var EloquentCollection<int, TeamMember> $members */
        $members = $team->members;

        return $members
            ->sortBy(fn (TeamMember $member): int => $member->speaker_position->slotNumber())
            ->values();
    }

    /**
     * @return Collection<int, TeamMember>
     */
    public function scoredMembers(DebateMatch $match): Collection
    {
        return $this->lineupMembers($match, TeamSide::Government)
            ->concat($this->lineupMembers($match, TeamSide::Opposition))
            ->filter(fn (TeamMember $member): bool => $member->speaker_position->isReserve() === false)
            ->values();
    }

    public function scoreFieldForMember(DebateMatch $match, TeamMember $member): ?string
    {
        $side = $member->team_id === $match->government_team_id
            ? TeamSide::Government
            : TeamSide::Opposition;

        return $this->positionForMember($match, $member)?->scoreField($side);
    }

    public function decorateMatch(DebateMatch $match): DebateMatch
    {
        $match->setAttribute('government_lineup', $this->lineupMembers($match, TeamSide::Government)->values()->all());
        $match->setAttribute('opposition_lineup', $this->lineupMembers($match, TeamSide::Opposition)->values()->all());
        $match->setAttribute('can_edit_lineup', $this->canEditLineup($match));

        if ($match->relationLoaded('result') && $match->result?->relationLoaded('bestSpeaker') && $match->result->bestSpeaker) {
            $match->result->setRelation(
                'bestSpeaker',
                $this->memberWithMatchPosition(
                    $match->result->bestSpeaker,
                    $this->positionForMember($match, $match->result->bestSpeaker) ?? $match->result->bestSpeaker->speaker_position,
                ),
            );
        }

        return $match;
    }

    /**
     * @param  array<string, array<string, int|null>>  $payload
     */
    public function sync(DebateMatch $match, array $payload, User $actor): DebateMatch
    {
        return DB::transaction(function () use ($match, $payload, $actor): DebateMatch {
            if (! $this->canEditLineup($match)) {
                throw new InvalidArgumentException('Lineup hanya boleh diubah sebelum mana-mana borang markah diwujudkan dan sebelum perlawanan selesai.');
            }

            $before = [
                'government' => $this->serializeLineup($this->lineupMembers($match, TeamSide::Government)),
                'opposition' => $this->serializeLineup($this->lineupMembers($match, TeamSide::Opposition)),
            ];

            $match->matchSpeakers()->delete();

            foreach ([
                'government' => $match->government_team_id,
                'opposition' => $match->opposition_team_id,
            ] as $side => $teamId) {
                foreach (SpeakerPosition::ordered() as $position) {
                    $memberId = $payload[$side][$position->value] ?? null;

                    if ($memberId === null) {
                        continue;
                    }

                    $match->matchSpeakers()->create([
                        'team_id' => $teamId,
                        'team_member_id' => $memberId,
                        'speaker_position' => $position,
                    ]);
                }
            }

            $freshMatch = $match->fresh()->load([
                'governmentTeam.members',
                'oppositionTeam.members',
                'matchSpeakers.teamMember',
            ]);

            $after = [
                'government' => $this->serializeLineup($this->lineupMembers($freshMatch, TeamSide::Government)),
                'opposition' => $this->serializeLineup($this->lineupMembers($freshMatch, TeamSide::Opposition)),
            ];

            $this->auditLogService->log(
                actor: $actor,
                entityType: 'match',
                entityId: $freshMatch->id,
                action: 'lineup_updated',
                reason: 'Lineup perlawanan dikemas kini',
                metadata: [
                    'before' => $before,
                    'after' => $after,
                ],
            );

            return $freshMatch;
        });
    }

    public function canEditLineup(DebateMatch $match): bool
    {
        if ($match->status === MatchStatus::Completed) {
            return false;
        }

        return ! $match->scoreSheets()->exists();
    }

    /**
     * @return Collection<int, MatchSpeaker>
     */
    protected function matchSpeakers(DebateMatch $match): Collection
    {
        if (! $match->relationLoaded('matchSpeakers')) {
            $match->load('matchSpeakers.teamMember');
        }

        /** @var EloquentCollection<int, MatchSpeaker> $matchSpeakers */
        $matchSpeakers = $match->getRelation('matchSpeakers');

        return $matchSpeakers;
    }

    protected function memberWithMatchPosition(TeamMember $member, SpeakerPosition $position): TeamMember
    {
        $decoratedMember = clone $member;
        $decoratedMember->setAttribute('speaker_position', $position->value);

        return $decoratedMember;
    }

    /**
     * @param  Collection<int, TeamMember>  $members
     * @return array<int, array<string, mixed>>
     */
    protected function serializeLineup(Collection $members): array
    {
        return $members
            ->map(fn (TeamMember $member): array => [
                'member_id' => $member->id,
                'member_name' => $member->full_name,
                'speaker_position' => $member->speaker_position->value,
                'speaker_position_label' => $member->speaker_position->label(),
            ])
            ->values()
            ->all();
    }
}
