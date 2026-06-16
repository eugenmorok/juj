<?php

namespace Database\Factories;

use App\Models\ArenaChallenge;
use App\Models\Creature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArenaChallenge>
 */
class ArenaChallengeFactory extends Factory
{
    public function definition(): array
    {
        $challenger = User::factory();
        $defender = User::factory();

        return [
            'challenger_user_id' => $challenger,
            'challenger_creature_id' => Creature::factory()->state([
                'user_id' => $challenger,
            ]),
            'defender_user_id' => $defender,
            'defender_creature_id' => Creature::factory()->state([
                'user_id' => $defender,
            ]),
            'defender_is_bot' => false,
            'status' => ArenaChallenge::STATUS_PENDING,
            'expires_at' => now()->addSeconds(ArenaChallenge::ACCEPTANCE_SECONDS),
            'accepted_at' => null,
            'declined_at' => null,
            'battle_id' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArenaChallenge::STATUS_EXPIRED,
            'expires_at' => now()->subSecond(),
        ]);
    }
}
