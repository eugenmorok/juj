<?php

namespace Database\Factories;

use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreatureSpecies>
 */
class CreatureSpeciesFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'creature_type_id' => CreatureType::factory(),
            'name' => ucfirst($name),
            'code' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'description' => fake()->sentence(),
            'rarity' => 'common',
            'base_strength' => fake()->numberBetween(1, 10),
            'base_perception' => fake()->numberBetween(1, 10),
            'base_endurance' => fake()->numberBetween(1, 10),
            'base_charisma' => fake()->numberBetween(1, 10),
            'base_intelligence' => fake()->numberBetween(1, 10),
            'base_agility' => fake()->numberBetween(1, 10),
            'base_luck' => fake()->numberBetween(1, 10),
            'is_starter_available' => true,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function notStarter(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_starter_available' => false,
        ]);
    }
}
