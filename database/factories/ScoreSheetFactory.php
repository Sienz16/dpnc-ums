<?php

namespace Database\Factories;

use App\Domain\Debate\Enums\ScoreSheetState;
use App\Domain\Debate\Enums\TeamSide;
use App\Models\DebateMatch;
use App\Models\ScoreSheet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoreSheet>
 */
class ScoreSheetFactory extends Factory
{
    protected $model = ScoreSheet::class;

    public function definition(): array
    {
        return [
            'match_id' => DebateMatch::factory(),
            'judge_id' => User::factory()->judge(),
            'mark_pm' => 75,
            'mark_tpm' => 75,
            'mark_m1' => 75,
            'mark_kp' => 74,
            'mark_tkp' => 74,
            'mark_p1' => 74,
            'mark_penggulungan_gov' => 75,
            'mark_penggulungan_opp' => 74,
            'gov_total' => 300,
            'opp_total' => 296,
            'margin' => 4,
            'winner_side' => TeamSide::Government,
            'best_debater_member_id' => null,
            'state' => ScoreSheetState::Draft,
            'submitted_at' => null,
        ];
    }
}
