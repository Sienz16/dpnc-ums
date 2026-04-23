<?php

namespace Database\Factories;

use App\Domain\Debate\Enums\TeamSide;
use App\Models\DebateMatch;
use App\Models\MatchResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchResult>
 */
class MatchResultFactory extends Factory
{
    protected $model = MatchResult::class;

    public function definition(): array
    {
        return [
            'match_id' => DebateMatch::factory(),
            'winner_side' => TeamSide::Government,
            'winner_vote_count' => 2,
            'loser_vote_count' => 1,
            'official_margin' => 5,
            'official_team_score_government' => 300,
            'official_team_score_opposition' => 295,
            'best_speaker_member_id' => null,
            'is_force_completed' => false,
            'is_provisional' => false,
            'calculated_at' => now(),
        ];
    }
}
