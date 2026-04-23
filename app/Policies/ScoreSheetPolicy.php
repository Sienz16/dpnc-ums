<?php

namespace App\Policies;

use App\Domain\Debate\Enums\UserRole;
use App\Models\DebateMatch;
use App\Models\ScoreSheet;
use App\Models\User;

class ScoreSheetPolicy
{
    public function view(User $user, DebateMatch $match): bool
    {
        if ($user->role === UserRole::Superadmin) {
            return true;
        }

        if ($user->role !== UserRole::Judge) {
            return false;
        }

        return $match->judgeAssignments()->where('judge_id', $user->id)->exists();
    }

    public function update(User $user, DebateMatch $match, ?ScoreSheet $scoreSheet = null): bool
    {
        if (! $this->view($user, $match)) {
            return false;
        }

        if (! $scoreSheet) {
            return true;
        }

        return $scoreSheet->judge_id === $user->id;
    }
}
