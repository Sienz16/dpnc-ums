<?php

namespace App\Domain\Debate\Services;

use App\Domain\Debate\Enums\JudgePanelSize;
use App\Domain\Debate\Enums\MatchCompletionType;
use App\Domain\Debate\Enums\MatchResultState;
use App\Domain\Debate\Enums\MatchStatus;
use App\Models\DebateMatch;

class MatchStatusService
{
    public function syncCheckedInStatus(DebateMatch $match): DebateMatch
    {
        $match->loadCount('judgeAssignments');

        if ($match->status !== MatchStatus::Pending) {
            return $match;
        }

        if (! $this->hasFullPanel($match)) {
            return $match;
        }

        $allCheckedIn = $match->judgeAssignments()
            ->whereNull('checked_in_at')
            ->doesntExist();

        if ($allCheckedIn) {
            $match->update([
                'status' => MatchStatus::InProgress,
                'completion_type' => null,
                'result_state' => null,
            ]);
        }

        return $match->fresh();
    }

    public function completeNormally(DebateMatch $match): DebateMatch
    {
        $match->update([
            'status' => MatchStatus::Completed,
            'completion_type' => MatchCompletionType::Normal,
            'result_state' => MatchResultState::Final,
        ]);

        return $match->fresh();
    }

    public function hasFullPanel(DebateMatch $match): bool
    {
        $expectedSize = $match->judge_panel_size instanceof JudgePanelSize
            ? $match->judge_panel_size->value
            : (int) $match->judge_panel_size;

        return $match->judgeAssignments()->count() === $expectedSize;
    }

    public function allAssignedJudgesSubmitted(DebateMatch $match): bool
    {
        if (! $this->hasFullPanel($match)) {
            return false;
        }

        return $match->judgeAssignments()
            ->whereNull('submitted_at')
            ->doesntExist();
    }
}
