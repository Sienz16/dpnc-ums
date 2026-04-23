<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'name' => 'Sidang '.$this->faker->unique()->numberBetween(1, 20),
            'location' => $this->faker->city(),
            'is_active' => true,
        ];
    }
}
