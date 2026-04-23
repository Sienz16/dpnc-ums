<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $suffix = (string) random_int(100000, 999999);

        return [
            'name' => 'Team '.$suffix,
            'institution' => 'Institution '.$suffix,
            'is_active' => true,
        ];
    }
}
