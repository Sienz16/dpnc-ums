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
        private AuditLogService $auditLogService,
    ) {
    }

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

    public function forceComplete(DebateMatch $match, User $actor, string $reason): DebateMatch
    {
        return DB::transaction(function () use ($match, $actor, $reason): DebateMatch {
            if ($match->status === MatchStatus::Completed) {
                throw new InvalidArgumentException('Match is already completed.');
            }

            $missingSubmissionExists = $match->judgeAssignments()
                ->whereNull('submitted_at')
                ->exists();

            $resultState = $missingSubmissionExists
                ? MatchResultState::Provisional
                : MatchResultState::Final;

            $result = $this->resultCalculator->recalculate(
                $match->fresh(),
                isForceCompleted: true,
                isProvisional: $resultState === MatchResultState::Provisional,
            );

            if (! $result) {
                throw new InvalidArgumentException('Force-complete requires at least one submitted score sheet.');
            }

            $match->update([
                'status' => MatchStatus::Completed,
                'completion_type' => MatchCompletionType::ForceCompleted,
                'result_state' => $resultState,
            ]);

            $this->auditLogService->log(
                actor: $actor,
                entityType: 'match',
                entityId: $match->id,
                action: 'force_completed',
                reason: $reason,
                metadata: [
                    'missing_submission_exists' => $missingSubmissionExists,
                ],
            );

            return $match->fresh();
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
