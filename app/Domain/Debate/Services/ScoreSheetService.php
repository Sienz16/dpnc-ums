<?php

namespace App\Domain\Debate\Services;

use App\Domain\Debate\Enums\MatchCompletionType;
use App\Domain\Debate\Enums\MatchResultState;
use App\Domain\Debate\Enums\MatchStatus;
use App\Domain\Debate\Enums\ScoreSheetState;
use App\Domain\Debate\Enums\TeamSide;
use App\Models\DebateMatch;
use App\Models\ScoreSheet;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScoreSheetService
{
    public function __construct(
        private MatchStatusService $matchStatusService,
        private MatchResultCalculator $resultCalculator,
        private MatchLineupService $matchLineupService,
        private AuditLogService $auditLogService,
    ) {}

    protected const string REOPENED_MATCH_EDIT_LOCK_MESSAGE = 'Submitted score sheets are locked unless match is reopened.';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function saveDraft(DebateMatch $match, User $judge, array $payload): ScoreSheet
    {
        return DB::transaction(function () use ($match, $judge, $payload): ScoreSheet {
            $assignment = $match->judgeAssignments()->where('judge_id', $judge->id)->first();

            if (! $assignment) {
                throw new InvalidArgumentException('Judge is not assigned to this match.');
            }

            if ($assignment->checked_in_at === null) {
                throw new InvalidArgumentException('Judge must check in before saving scores.');
            }

            if ($match->status === MatchStatus::Completed) {
                throw new InvalidArgumentException('Cannot edit scores for a completed match.');
            }

            $scoreSheet = ScoreSheet::query()->firstOrNew([
                'match_id' => $match->id,
                'judge_id' => $judge->id,
            ]);

            if (
                $scoreSheet->exists
                && $scoreSheet->state === ScoreSheetState::Submitted
                && ! $this->canEditSubmittedSheetInCurrentMatchState($match)
            ) {
                throw new InvalidArgumentException(self::REOPENED_MATCH_EDIT_LOCK_MESSAGE);
            }

            $bestDebaterMemberId = (int) $payload['best_debater_member_id'];

            if (! $this->isMemberInMatch($match, $bestDebaterMemberId)) {
                throw new InvalidArgumentException('Best debater must belong to one of the six match speakers.');
            }

            $computedValues = $this->computeTotals($payload);

            $scoreSheet->fill(array_merge($payload, $computedValues, [
                'state' => ScoreSheetState::Draft,
                'submitted_at' => null,
            ]));

            $scoreSheet->save();

            $assignment->update(['submitted_at' => null]);

            return $scoreSheet->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(DebateMatch $match, User $judge, array $payload): ScoreSheet
    {
        return DB::transaction(function () use ($match, $judge, $payload): ScoreSheet {
            $scoreSheet = $this->saveDraft($match, $judge, $payload);

            $scoreSheet->update([
                'state' => ScoreSheetState::Submitted,
                'submitted_at' => now(),
            ]);

            $match->judgeAssignments()
                ->where('judge_id', $judge->id)
                ->update(['submitted_at' => now()]);

            $refreshedMatch = $match->fresh();

            if ($this->matchStatusService->allAssignedJudgesSubmitted($refreshedMatch)) {
                $this->resultCalculator->recalculate(
                    $refreshedMatch,
                    isForceCompleted: false,
                    isProvisional: false,
                );

                $this->matchStatusService->completeNormally($refreshedMatch);
            }

            return $scoreSheet->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submitAsAdmin(
        DebateMatch $match,
        User $judge,
        User $actor,
        array $payload,
        string $reason,
        string $action,
    ): ScoreSheet {
        return DB::transaction(function () use ($match, $judge, $actor, $payload, $reason, $action): ScoreSheet {
            $assignment = $match->judgeAssignments()->where('judge_id', $judge->id)->first();

            if (! $assignment) {
                throw new InvalidArgumentException('Judge is not assigned to this match.');
            }

            $bestDebaterMemberId = (int) $payload['best_debater_member_id'];

            if (! $this->isMemberInMatch($match, $bestDebaterMemberId)) {
                throw new InvalidArgumentException('Best debater must belong to one of the six match speakers.');
            }

            $computedValues = $this->computeTotals($payload);

            $scoreSheet = ScoreSheet::query()->firstOrNew([
                'match_id' => $match->id,
                'judge_id' => $judge->id,
            ]);

            $before = $scoreSheet->exists ? $scoreSheet->only([
                'mark_pm',
                'mark_tpm',
                'mark_m1',
                'mark_kp',
                'mark_tkp',
                'mark_p1',
                'mark_penggulungan_gov',
                'mark_penggulungan_opp',
                'gov_total',
                'opp_total',
                'margin',
                'winner_side',
                'best_debater_member_id',
                'state',
                'submitted_at',
            ]) : null;

            $scoreSheet->fill(array_merge($payload, $computedValues, [
                'state' => ScoreSheetState::Submitted,
                'submitted_at' => now(),
            ]));

            $scoreSheet->save();

            $assignment->update([
                'checked_in_at' => $assignment->checked_in_at ?? now(),
                'submitted_at' => now(),
            ]);

            $refreshedMatch = $match->fresh();

            if ($refreshedMatch->status === MatchStatus::Completed) {
                $isProvisional = $refreshedMatch->judgeAssignments()
                    ->whereNull('submitted_at')
                    ->exists();

                $this->resultCalculator->recalculate(
                    $refreshedMatch,
                    isForceCompleted: $refreshedMatch->completion_type === MatchCompletionType::ForceCompleted,
                    isProvisional: $isProvisional,
                );

                $refreshedMatch->update([
                    'result_state' => $isProvisional
                        ? MatchResultState::Provisional
                        : MatchResultState::Final,
                ]);
            } elseif (
                $refreshedMatch->status === MatchStatus::InProgress
                && $this->matchStatusService->allAssignedJudgesSubmitted($refreshedMatch)
            ) {
                $this->resultCalculator->recalculate(
                    $refreshedMatch,
                    isForceCompleted: false,
                    isProvisional: false,
                );

                $this->matchStatusService->completeNormally($refreshedMatch);
            }

            $this->auditLogService->log(
                actor: $actor,
                entityType: 'score_sheet',
                entityId: $scoreSheet->id,
                action: $action,
                reason: $reason,
                metadata: [
                    'match_id' => $match->id,
                    'judge_id' => $judge->id,
                    'before' => $before,
                    'after' => $scoreSheet->only([
                        'mark_pm',
                        'mark_tpm',
                        'mark_m1',
                        'mark_kp',
                        'mark_tkp',
                        'mark_p1',
                        'mark_penggulungan_gov',
                        'mark_penggulungan_opp',
                        'gov_total',
                        'opp_total',
                        'margin',
                        'winner_side',
                        'best_debater_member_id',
                        'state',
                        'submitted_at',
                    ]),
                ],
            );

            return $scoreSheet->fresh(['judge', 'bestDebater']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function computeTotals(array $payload): array
    {
        $govTotal = (float) $payload['mark_pm']
            + (float) $payload['mark_tpm']
            + (float) $payload['mark_m1']
            + (float) $payload['mark_penggulungan_gov'];

        $oppTotal = (float) $payload['mark_kp']
            + (float) $payload['mark_tkp']
            + (float) $payload['mark_p1']
            + (float) $payload['mark_penggulungan_opp'];

        if (round(abs($govTotal - $oppTotal), 1) === 0.0) {
            throw new InvalidArgumentException('Team totals cannot be tied.');
        }

        return [
            'gov_total' => round($govTotal, 1),
            'opp_total' => round($oppTotal, 1),
            'margin' => round((float) $payload['margin'], 1),
            'winner_side' => $govTotal > $oppTotal
                ? TeamSide::Government
                : TeamSide::Opposition,
        ];
    }

    protected function isMemberInMatch(DebateMatch $match, int $memberId): bool
    {
        return $this->matchLineupService->scoredMembers($match)
            ->contains(fn (TeamMember $member): bool => $member->id === $memberId);
    }

    protected function canEditSubmittedSheetInCurrentMatchState(DebateMatch $match): bool
    {
        return $match->status === MatchStatus::InProgress && $match->completion_type === null;
    }
}
