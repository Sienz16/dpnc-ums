<?php

namespace App\Domain\Debate\Services;

use App\Domain\Debate\Enums\JudgeAssignmentMode;
use App\Domain\Debate\Enums\JudgePanelSize;
use App\Domain\Debate\Enums\MatchStatus;
use App\Domain\Debate\Enums\UserRole;
use App\Models\DebateMatch;
use App\Models\JudgeAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JudgeAssignmentService
{
    public function __construct(private MatchStatusService $matchStatusService)
    {
    }

    /**
     * @param array<int, int> $judgeIds
     */
    public function assignManual(DebateMatch $match, array $judgeIds): Collection
    {
        $uniqueJudgeIds = collect($judgeIds)->map(static fn (mixed $id): int => (int) $id)->unique()->values();

        $this->assertCanAssign($match, $uniqueJudgeIds->all());

        return DB::transaction(function () use ($match, $uniqueJudgeIds): Collection {
            $match->judgeAssignments()->delete();
            $match->scoreSheets()->delete();

            $assignments = $uniqueJudgeIds->map(fn (int $judgeId): JudgeAssignment => $match->judgeAssignments()->create([
                'judge_id' => $judgeId,
                'assigned_mode' => JudgeAssignmentMode::Manual,
            ]));

            $match->update(['status' => MatchStatus::Pending]);

            return $assignments;
        });
    }

    public function clear(DebateMatch $match): void
    {
        $this->assertMatchIsPending($match);

        DB::transaction(function () use ($match): void {
            $match->judgeAssignments()->delete();
            $match->scoreSheets()->delete();
            $match->update(['status' => MatchStatus::Pending]);
        });
    }

    /**
     * @param array<int, int>|null $eligibleJudgeIds
     */
    public function assignRandom(DebateMatch $match, ?array $eligibleJudgeIds = null): Collection
    {
        $panelSize = $match->judge_panel_size instanceof JudgePanelSize
            ? $match->judge_panel_size->value
            : (int) $match->judge_panel_size;

        $query = User::query()
            ->where('role', UserRole::Judge)
            ->where('is_active', true);

        $unavailableJudgeIds = $this->unavailableJudgeIdsForRound($match);
        if ($unavailableJudgeIds->isNotEmpty()) {
            $query->whereNotIn('id', $unavailableJudgeIds->all());
        }

        if ($eligibleJudgeIds !== null) {
            $query->whereIn('id', $eligibleJudgeIds);
        }

        $judgeIds = $query->inRandomOrder()->limit($panelSize)->pluck('id');

        if ($judgeIds->count() !== $panelSize) {
            throw new InvalidArgumentException('Not enough judges available to fill panel size.');
        }

        return DB::transaction(function () use ($match, $judgeIds): Collection {
            $match->judgeAssignments()->delete();
            $match->scoreSheets()->delete();

            $assignments = $judgeIds->map(fn (int $judgeId): JudgeAssignment => $match->judgeAssignments()->create([
                'judge_id' => $judgeId,
                'assigned_mode' => JudgeAssignmentMode::Random,
            ]));

            $match->update(['status' => MatchStatus::Pending]);

            return $assignments;
        });
    }

    /**
     * @param array<int, int> $judgeIds
     */
    protected function assertCanAssign(DebateMatch $match, array $judgeIds): void
    {
        $this->assertMatchIsPending($match);

        $panelSize = $match->judge_panel_size instanceof JudgePanelSize
            ? $match->judge_panel_size->value
            : (int) $match->judge_panel_size;

        if (count($judgeIds) !== $panelSize) {
            throw new InvalidArgumentException('Assigned judges must exactly match judge panel size.');
        }

        $judgeCount = User::query()
            ->whereIn('id', $judgeIds)
            ->where('role', UserRole::Judge)
            ->where('is_active', true)
            ->count();

        if ($judgeCount !== count($judgeIds)) {
            throw new InvalidArgumentException('Only active judges can be assigned to matches.');
        }

        $unavailableJudgeIds = $this->unavailableJudgeIdsForRound($match)
            ->intersect($judgeIds)
            ->values();

        if ($unavailableJudgeIds->isNotEmpty()) {
            throw new InvalidArgumentException('Hakim yang dipilih sudah ditugaskan pada sidang lain dalam pusingan yang sama.');
        }
    }

    protected function assertMatchIsPending(DebateMatch $match): void
    {
        if ($match->status !== MatchStatus::Pending) {
            throw new InvalidArgumentException('Judge assignment is only allowed while match is pending.');
        }
    }

    /**
     * @return Collection<int, int>
     */
    protected function unavailableJudgeIdsForRound(DebateMatch $match): Collection
    {
        return JudgeAssignment::query()
            ->whereHas('match', function ($query) use ($match): void {
                $query->where('round_id', $match->round_id)
                    ->whereKeyNot($match->id);
            })
            ->pluck('judge_id')
            ->unique()
            ->values();
    }
}
