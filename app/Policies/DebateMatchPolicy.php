<?php

namespace App\Policies;

use App\Domain\Debate\Enums\UserRole;
use App\Models\DebateMatch;
use App\Models\User;

class DebateMatchPolicy
{
    public function view(User $user, DebateMatch $match): bool
    {
        if ($user->role === UserRole::Superadmin) {
            return true;
        }

        if ($user->role !== UserRole::Judge) {
            return false;
        }

        return $match->judgeAssignments()
            ->where('judge_id', $user->id)
            ->exists();
    }
}
