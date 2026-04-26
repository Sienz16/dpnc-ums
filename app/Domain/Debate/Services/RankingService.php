<?php

namespace App\Domain\Debate\Services;

use App\Domain\Debate\Enums\ScoreSheetState;
use App\Domain\Debate\Enums\TeamSide;
use App\Models\DebateMatch;
use App\Models\MatchResult;
use App\Models\ScoreSheet;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Collection;

class RankingService
{
    private const array DefaultTeamRankingSequence = ['win', 'judge', 'margin', 'marks'];

    private const array TeamRankingFields = [
        'win' => 'win_count',
        'judge' => 'judge_count',
        'margin' => 'total_margin',
        'marks' => 'average_team_score',
    ];

    public function __construct(private MatchLineupService $matchLineupService) {}

    /**
     * @param  array<int, string>  $rankingSequence
     * @param  array<int, int>  $roundIds
     * @return Collection<int, array<string, mixed>>
     */
    public function teamRankings(array $rankingSequence = [], array $roundIds = []): Collection
    {
        $sortFields = $this->teamRankingSortFields($rankingSequence);
        $roundIds = array_values(array_unique(array_map('intval', $roundIds)));

        $rows = Team::query()
            ->get()
            ->mapWithKeys(fn (Team $team): array => [$team->id => [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'win_count' => 0,
                'judge_count' => 0,
                'average_margin' => 0.0,
                'total_margin' => 0.0,
                'average_team_score' => 0.0,
                '_margins' => [],
                '_scores' => [],
            ]])
            ->all();

        MatchResult::query()
            ->with('match')
            ->when($roundIds !== [], fn ($query) => $query->whereHas(
                'match',
                fn ($query) => $query->whereIn('round_id', $roundIds),
            ))
            ->get()
            ->each(function (MatchResult $result) use (&$rows): void {
                $match = $result->match;

                if (! $match) {
                    return;
                }

                $winnerTeamId = $result->winner_side->value === 'government'
                    ? $match->government_team_id
                    : $match->opposition_team_id;

                if (! array_key_exists($winnerTeamId, $rows)) {
                    return;
                }

                $rows[$winnerTeamId]['win_count']++;
                $rows[$winnerTeamId]['judge_count'] += $result->winner_vote_count;
            });

        DebateMatch::query()
            ->with('result')
            ->when($roundIds !== [], fn ($query) => $query->whereIn('round_id', $roundIds))
            ->get()
            ->each(function (DebateMatch $match) use (&$rows): void {
                $result = $match->result;

                if (! $result) {
                    return;
                }

                $officialMargin = (float) $result->official_margin;
                $govMargin = $result->winner_side === TeamSide::Government
                    ? $officialMargin
                    : -$officialMargin;
                $oppMargin = -$govMargin;

                if (array_key_exists($match->government_team_id, $rows)) {
                    $rows[$match->government_team_id]['_margins'][] = $govMargin;
                    $rows[$match->government_team_id]['_scores'][] = (float) $result->official_team_score_government;
                }

                if (array_key_exists($match->opposition_team_id, $rows)) {
                    $rows[$match->opposition_team_id]['_margins'][] = $oppMargin;
                    $rows[$match->opposition_team_id]['_scores'][] = (float) $result->official_team_score_opposition;
                }
            });

        return collect($rows)
            ->map(function (array $row): array {
                $margins = collect($row['_margins']);
                $scores = collect($row['_scores']);

                $row['average_margin'] = round((float) $margins->avg(), 1);
                $row['total_margin'] = round((float) $margins->sum(), 1);
                $row['average_team_score'] = round((float) $scores->avg(), 1);

                unset($row['_margins'], $row['_scores']);

                return $row;
            })
            ->sort(fn (array $left, array $right): int => $this->compareTeamRankingRows($left, $right, $sortFields))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @param  array<int, string>  $sortFields
     */
    private function compareTeamRankingRows(array $left, array $right, array $sortFields): int
    {
        foreach ($sortFields as $field) {
            if ($left[$field] !== $right[$field]) {
                return $right[$field] <=> $left[$field];
            }
        }

        return $left['team_name'] <=> $right['team_name'];
    }

    /**
     * @param  array<int, string>  $rankingSequence
     * @return array<int, string>
     */
    private function teamRankingSortFields(array $rankingSequence): array
    {
        $normalizedSequence = array_values(array_unique(array_filter(
            $rankingSequence,
            fn (string $factor): bool => array_key_exists($factor, self::TeamRankingFields),
        )));

        foreach (self::DefaultTeamRankingSequence as $factor) {
            if (! in_array($factor, $normalizedSequence, true)) {
                $normalizedSequence[] = $factor;
            }
        }

        return array_map(
            fn (string $factor): string => self::TeamRankingFields[$factor],
            $normalizedSequence,
        );
    }

    /**
     * @param  array<int, int>  $roundIds
     * @return Collection<int, array<string, mixed>>
     */
    public function speakerRankings(array $roundIds = []): Collection
    {
        $roundIds = array_values(array_unique(array_map('intval', $roundIds)));
        $members = TeamMember::query()->with('team')->get();

        $appearanceScores = $this->appearanceScores($roundIds);
        $speakerVoteCounts = MatchResult::query()
            ->whereNotNull('best_speaker_member_id')
            ->when($roundIds !== [], fn ($query) => $query->whereHas(
                'match',
                fn ($query) => $query->whereIn('round_id', $roundIds),
            ))
            ->pluck('best_speaker_member_id')
            ->countBy();

        return $members
            ->map(function (TeamMember $member) use ($appearanceScores, $speakerVoteCounts): array {
                $appearanceItems = collect($appearanceScores[$member->id] ?? []);

                return [
                    'speaker_id' => $member->id,
                    'speaker_name' => $member->full_name,
                    'team_name' => $member->team?->name,
                    'appearances' => $appearanceItems->count(),
                    'average_official_points_per_appearance' => round((float) $appearanceItems->avg('official_points'), 1),
                    'best_speaker_wins_count' => (int) ($speakerVoteCounts[$member->id] ?? 0),
                    'average_score_per_appearance' => round((float) $appearanceItems->avg('judge_weighted_points'), 1),
                ];
            })
            ->filter(fn (array $row): bool => $row['appearances'] > 0)
            ->sortByDesc('average_score_per_appearance')
            ->sortByDesc('best_speaker_wins_count')
            ->sortByDesc('average_official_points_per_appearance')
            ->values();
    }

    /**
     * @param  array<int, int>  $roundIds
     * @return array<int, array<int, array<string, float>>>
     */
    protected function appearanceScores(array $roundIds = []): array
    {
        $scoresByMember = [];

        DebateMatch::query()
            ->with(['governmentTeam.members', 'oppositionTeam.members', 'matchSpeakers.teamMember'])
            ->when($roundIds !== [], fn ($query) => $query->whereIn('round_id', $roundIds))
            ->get()
            ->each(function (DebateMatch $match) use (&$scoresByMember): void {
                $submittedSheets = ScoreSheet::query()
                    ->where('match_id', $match->id)
                    ->where('state', ScoreSheetState::Submitted)
                    ->get();

                if ($submittedSheets->isEmpty()) {
                    return;
                }

                $members = $this->matchLineupService->scoredMembers($match);

                $members->each(function (TeamMember $member) use (&$scoresByMember, $submittedSheets, $match): void {
                    $scoreField = $this->matchLineupService->scoreFieldForMember($match, $member);

                    if ($scoreField === null) {
                        return;
                    }

                    $marks = $submittedSheets->map(fn (ScoreSheet $sheet): float => (float) $sheet->{$scoreField});

                    if (! array_key_exists($member->id, $scoresByMember)) {
                        $scoresByMember[$member->id] = [];
                    }

                    $scoresByMember[$member->id][] = [
                        'official_points' => (float) round((float) $marks->avg(), 1),
                        'judge_weighted_points' => (float) round((float) $marks->avg(), 4),
                    ];
                });
            });

        return $scoresByMember;
    }
}
