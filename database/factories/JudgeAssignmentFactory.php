<?php

namespace Database\Factories;

use App\Domain\Debate\Enums\JudgeAssignmentMode;
use App\Models\DebateMatch;
use App\Models\JudgeAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JudgeAssignment>
 */
class JudgeAssignmentFactory extends Factory
{
    protected $model = JudgeAssignment::class;

    public function definition(): array
    {
        return [
            'match_id' => DebateMatch::factory(),
            'judge_id' => User::factory()->judge(),
            'assigned_mode' => JudgeAssignmentMode::Manual,
            'checked_in_at' => null,
            'submitted_at' => null,
        ];
    }
}
