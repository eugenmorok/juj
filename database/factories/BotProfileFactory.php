<?php

namespace Database\Factories;

use App\Models\BotProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BotProfile>
 */
class BotProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->bot(),
            'display_name' => 'Bot '.fake()->unique()->numberBetween(100, 999),
            'style' => fake()->randomElement(array_keys(BotProfile::STYLES)),
            'is_active' => true,
            'min_level' => 1,
            'max_level' => 3,
            'spawn_chance' => 100,
            'strength_percent' => 100,
            'generated_creatures_count' => 0,
            'last_generated_at' => null,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
