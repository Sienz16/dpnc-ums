<?php

namespace App\Domain\Debate\Services;

use App\Domain\Debate\Enums\MatchCompletionType;
use App\Domain\Debate\Enums\MatchResultState;
use App\Domain\Debate\Enums\MatchStatus;
use App\Models\DebateMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MatchLifecycleService
{
    public function __construct(
        private MatchStatusService $matchStatusService,
        private MatchResultCalculator $resultCalculator,
        private ScoreSheetService $scoreSheetService,
        private AuditLogService $auditLogService,
    ) {}

    public function checkIn(DebateMatch $match, User $judge): DebateMatch
    {
        return DB::transaction(function () use ($match, $judge): DebateMatch {
            $assignment = $match->judgeAssignments()->where('judge_id', $judge->id)->first();

            if (! $assignment) {
                throw new InvalidArgumentException('Judge is not assigned to this match.');
            }

            if ($match->status === MatchStatus::Completed) {
                throw new InvalidArgumentException('Cannot check in for completed match.');
            }

            $assignment->update([
                'checked_in_at' => $assignment->checked_in_at ?? now(),
            ]);

            return $this->matchStatusService->syncCheckedInStatus($match->fresh());
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $scoreSheets
     */
    public function forceComplete(DebateMatch $match, User $actor, string $reason, array $scoreSheets = []): DebateMatch
    {
        return DB::transaction(function () use ($match, $actor, $reason, $scoreSheets): DebateMatch {
            if ($match->status === MatchStatus::Completed) {
                throw new InvalidArgumentException('Match is already completed.');
            }

            $missingAssignments = $match->judgeAssignments()
                ->whereNull('submitted_at')
                ->get();

            if ($missingAssignments->isNotEmpty()) {
                $payloadsByJudgeId = collect($scoreSheets)->keyBy(fn (array $payload): int => (int) $payload['judge_id']);

                foreach ($missingAssignments as $assignment) {
                    $payload = $payloadsByJudgeId->get($assignment->judge_id);

                    if (! is_array($payload)) {
                        throw new InvalidArgumentException('Force-complete requires scores for every missing judge.');
                    }

                    $judge = User::query()->findOrFail($assignment->judge_id);

                    $this->scoreSheetService->submitAsAdmin(
                        $match->fresh()->loadMissing(['governmentTeam.members', 'oppositionTeam.members']),
                        $judge,
                        $actor,
                        collect($payload)->except('judge_id')->all(),
                        $reason,
                        'admin_submitted_on_behalf',
                    );
                }
            }

            $refreshedMatch = $match->fresh();
            $missingSubmissionExists = $refreshedMatch->judgeAssignments()
                ->whereNull('submitted_at')
                ->exists();

            $resultState = $missingSubmissionExists
                ? MatchResultState::Provisional
                : MatchResultState::Final;

            $result = $this->resultCalculator->recalculate(
                $refreshedMatch,
                isForceCompleted: true,
                isProvisional: $resultState === MatchResultState::Provisional,
            );

            if (! $result) {
                throw new InvalidArgumentException('Force-complete requires at least one submitted score sheet.');
            }

            $refreshedMatch->update([
                'status' => MatchStatus::Completed,
                'completion_type' => MatchCompletionType::ForceCompleted,
                'result_state' => $resultState,
            ]);

            $this->auditLogService->log(
                actor: $actor,
                entityType: 'match',
                entityId: $refreshedMatch->id,
                action: 'force_completed',
                reason: $reason,
                metadata: [
                    'missing_submission_exists' => $missingSubmissionExists,
                    'admin_entered_score_sheet_judge_ids' => collect($scoreSheets)
                        ->pluck('judge_id')
                        ->map(fn (mixed $judgeId): int => (int) $judgeId)
                        ->values()
                        ->all(),
                ],
            );

            return $refreshedMatch->fresh();
        });
    }

    public function reopen(DebateMatch $match, User $actor, string $reason): DebateMatch
    {
        return DB::transaction(function () use ($match, $actor, $reason): DebateMatch {
            if ($match->status !== MatchStatus::Completed) {
                throw new InvalidArgumentException('Only completed matches can be reopened.');
            }

            $match->update([
                'status' => MatchStatus::InProgress,
                'completion_type' => null,
                'result_state' => null,
            ]);

            $this->auditLogService->log(
                actor: $actor,
                entityType: 'match',
                entityId: $match->id,
                action: 'reopened',
                reason: $reason,
            );

            return $match->fresh();
        });
    }
}
