<?php

namespace Database\Factories;

use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Round>
 */
class RoundFactory extends Factory
{
    protected $model = Round::class;

    public function definition(): array
    {
        return [
            'name' => 'Pusingan '.$this->faker->unique()->numberBetween(1, 20),
            'sequence' => $this->faker->unique()->numberBetween(1, 20),
        ];
    }
}
