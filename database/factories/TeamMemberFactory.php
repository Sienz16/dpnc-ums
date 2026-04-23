<?php

namespace Database\Factories;

use App\Domain\Debate\Enums\SpeakerPosition;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMember>
 */
class TeamMemberFactory extends Factory
{
    protected $model = TeamMember::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'full_name' => $this->faker->name(),
            'speaker_position' => $this->faker->randomElement(SpeakerPosition::values()),
            'is_active' => true,
        ];
    }
}
