<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'entity_type' => 'match',
            'entity_id' => fake()->numberBetween(1, 1000),
            'action' => 'force_completed',
            'reason' => fake()->sentence(),
            'metadata_json' => ['source' => 'factory'],
        ];
    }
}
