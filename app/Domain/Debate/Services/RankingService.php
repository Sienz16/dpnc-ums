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
    public function __construct(private MatchLineupService $matchLineupService) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function teamRankings(): Collection
    {
        $rows = Team::query()
            ->get()
            ->mapWithKeys(fn (Team $team): array => [$team->id => [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'win_count' => 0,
                'judge_count' => 0,
                'average_margin' => 0.0,
                'average_team_score' => 0.0,
                '_margins' => [],
                '_scores' => [],
            ]])
            ->all();

        MatchResult::query()
            ->with('match')
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
                $row['average_team_score'] = round((float) $scores->avg(), 1);

                unset($row['_margins'], $row['_scores']);

                return $row;
            })
            ->sort(function (array $left, array $right): int {
                if ($left['win_count'] !== $right['win_count']) {
                    return $right['win_count'] <=> $left['win_count'];
                }

                if ($left['judge_count'] !== $right['judge_count']) {
                    return $right['judge_count'] <=> $left['judge_count'];
                }

                if ($left['average_margin'] !== $right['average_margin']) {
                    return $right['average_margin'] <=> $left['average_margin'];
                }

                if ($left['average_team_score'] !== $right['average_team_score']) {
                    return $right['average_team_score'] <=> $left['average_team_score'];
                }

                return $left['team_name'] <=> $right['team_name'];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function speakerRankings(): Collection
    {
        $members = TeamMember::query()->with('team')->get();

        $appearanceScores = $this->appearanceScores();
        $speakerVoteCounts = MatchResult::query()
            ->whereNotNull('best_speaker_member_id')
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
     * @return array<int, array<int, array<string, float>>>
     */
    protected function appearanceScores(): array
    {
        $scoresByMember = [];

        DebateMatch::query()
            ->with(['governmentTeam.members', 'oppositionTeam.members', 'matchSpeakers.teamMember'])
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
