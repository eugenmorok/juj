<?php

namespace Database\Factories;

use App\Models\ArenaMatchmakingSession;
use App\Models\Creature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArenaMatchmakingSession>
 */
class ArenaMatchmakingSessionFactory extends Factory
{
    public function definition(): array
    {
        $user = User::factory();

        return [
            'user_id' => $user,
            'creature_id' => Creature::factory()->state([
                'user_id' => $user,
            ]),
            'power_score' => fake()->numberBetween(50, 300),
            'status' => ArenaMatchmakingSession::STATUS_ACTIVE,
            'expires_at' => now()->addMinutes(ArenaMatchmakingSession::TTL_MINUTES),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArenaMatchmakingSession::STATUS_EXPIRED,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
