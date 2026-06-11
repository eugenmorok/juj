<?php

namespace Database\Factories;

use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Creature>
 */
class CreatureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $species = CreatureSpecies::factory()->create();
        $endurance = fake()->numberBetween($species->base_endurance, Creature::STARTER_SPECIAL_CAP);
        $maxHp = Creature::maxHpForEndurance($endurance);

        return [
            'user_id' => User::factory(),
            'creature_type_id' => $species->creature_type_id,
            'creature_species_id' => $species->id,
            'name' => fake()->firstName().' '.fake()->word(),
            'level' => 1,
            'xp' => 0,
            'development_points' => 0,
            'strength' => fake()->numberBetween($species->base_strength, Creature::STARTER_SPECIAL_CAP),
            'perception' => fake()->numberBetween($species->base_perception, Creature::STARTER_SPECIAL_CAP),
            'endurance' => $endurance,
            'charisma' => fake()->numberBetween($species->base_charisma, Creature::STARTER_SPECIAL_CAP),
            'intelligence' => fake()->numberBetween($species->base_intelligence, Creature::STARTER_SPECIAL_CAP),
            'agility' => fake()->numberBetween($species->base_agility, Creature::STARTER_SPECIAL_CAP),
            'luck' => fake()->numberBetween($species->base_luck, Creature::STARTER_SPECIAL_CAP),
            'current_hp' => $maxHp,
            'max_hp' => $maxHp,
            'inventory_slots' => Creature::STARTER_INVENTORY_SLOTS,
            'wins' => 0,
            'losses' => 0,
            'draws' => 0,
            'is_available_for_battle' => true,
        ];
    }
}
