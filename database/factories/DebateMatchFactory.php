<?php

namespace Database\Factories;

use App\Domain\Debate\Enums\JudgePanelSize;
use App\Domain\Debate\Enums\MatchStatus;
use App\Models\DebateMatch;
use App\Models\Room;
use App\Models\Round;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebateMatch>
 */
class DebateMatchFactory extends Factory
{
    protected $model = DebateMatch::class;

    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'room_id' => Room::factory(),
            'government_team_id' => Team::factory(),
            'opposition_team_id' => Team::factory(),
            'judge_panel_size' => JudgePanelSize::Three,
            'status' => MatchStatus::Pending,
            'completion_type' => null,
            'result_state' => null,
            'scheduled_at' => now()->addDay(),
        ];
    }
}
