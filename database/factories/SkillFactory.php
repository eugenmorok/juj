<?php

namespace Database\Factories;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'code' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'description' => fake()->sentence(),
            'skill_type' => 'passive',
            'cost' => fake()->numberBetween(5, 25),
            'required_level' => 1,
            'required_creature_type_id' => null,
            'required_creature_species_id' => null,
            'required_strength' => 0,
            'required_perception' => 0,
            'required_endurance' => 0,
            'required_charisma' => 0,
            'required_intelligence' => 0,
            'required_agility' => 0,
            'required_luck' => 0,
            'effect' => fake()->sentence(),
            'cooldown_turns' => 0,
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
