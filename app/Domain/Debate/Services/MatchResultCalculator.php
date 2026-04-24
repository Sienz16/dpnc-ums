<?php

namespace App\Domain\Debate\Services;

use App\Domain\Debate\Enums\TeamSide;
use App\Models\DebateMatch;
use App\Models\MatchResult;
use App\Models\ScoreSheet;
use App\Models\TeamMember;
use Illuminate\Support\Collection;

class MatchResultCalculator
{
    public function recalculate(DebateMatch $match, bool $isForceCompleted, bool $isProvisional): ?MatchResult
    {
        $match->loadMissing([
            'scoreSheets' => fn ($query) => $query->where('state', 'submitted'),
            'governmentTeam.members',
            'oppositionTeam.members',
        ]);

        $submittedSheets = $match->scoreSheets;

        if ($submittedSheets->isEmpty()) {
            return null;
        }

        $voteCounts = $submittedSheets
            ->groupBy(static fn (ScoreSheet $sheet): string => $sheet->winner_side->value)
            ->map(static fn (Collection $group): int => $group->count())
            ->sortDesc();

        $winnerSide = TeamSide::from((string) $voteCounts->keys()->first());
        $winnerVoteCount = (int) $voteCounts->first();
        $loserVoteCount = (int) $voteCounts->slice(1)->sum();

        $bestSpeakerMemberId = $this->resolveBestSpeakerMemberId($match, $submittedSheets);

        return MatchResult::query()->updateOrCreate(
            ['match_id' => $match->id],
            [
                'winner_side' => $winnerSide,
                'winner_vote_count' => $winnerVoteCount,
                'loser_vote_count' => $loserVoteCount,
                'official_margin' => round((float) $submittedSheets->avg('margin'), 1),
                'official_team_score_government' => round((float) $submittedSheets->avg('gov_total'), 1),
                'official_team_score_opposition' => round((float) $submittedSheets->avg('opp_total'), 1),
                'best_speaker_member_id' => $bestSpeakerMemberId,
                'is_force_completed' => $isForceCompleted,
                'is_provisional' => $isProvisional,
                'calculated_at' => now(),
            ],
        );
    }

    /**
     * @param  Collection<int, ScoreSheet>  $submittedSheets
     */
    protected function resolveBestSpeakerMemberId(DebateMatch $match, Collection $submittedSheets): ?int
    {
        $membersById = $match->governmentTeam->members
            ->concat($match->oppositionTeam->members)
            ->keyBy('id');

        $voteCounts = $submittedSheets
            ->pluck('best_debater_member_id')
            ->filter()
            ->countBy();

        if ($voteCounts->isEmpty()) {
            return null;
        }

        $maxVotes = (int) $voteCounts->max();

        $candidateIds = $voteCounts
            ->filter(static fn (int $count): bool => $count === $maxVotes)
            ->keys()
            ->map(static fn (mixed $id): int => (int) $id)
            ->values();

        if ($candidateIds->count() === 1) {
            return $candidateIds->first();
        }

        $averageScores = $candidateIds->mapWithKeys(function (int $memberId) use ($match, $submittedSheets, $membersById): array {
            /** @var TeamMember|null $member */
            $member = $membersById->get($memberId);

            if (! $member) {
                return [$memberId => 0.0];
            }

            $average = round((float) $submittedSheets
                ->map(fn (ScoreSheet $sheet): float => $this->speakerMarkForSheet($match, $sheet, $member))
                ->avg(), 4);

            return [$memberId => $average];
        });

        return (int) $averageScores->sortDesc()->keys()->first();
    }

    protected function speakerMarkForSheet(DebateMatch $match, ScoreSheet $sheet, TeamMember $member): float
    {
        $side = $member->team_id === $match->government_team_id
            ? TeamSide::Government
            : TeamSide::Opposition;

        $scoreField = $member->speaker_position->scoreField($side);

        if ($scoreField === null) {
            return 0.0;
        }

        return (float) $sheet->{$scoreField};
    }
}
