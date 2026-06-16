<?php

namespace Database\Factories;

use App\Models\Creature;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'creature_id' => null,
            'inventory_type' => Inventory::TYPE_PLAYER,
            'slots' => 7,
        ];
    }

    public function creature(): static
    {
        return $this->state(function (array $attributes): array {
            $creature = Creature::factory()->create();

            return [
                'owner_user_id' => $creature->user_id,
                'creature_id' => $creature->id,
                'inventory_type' => Inventory::TYPE_CREATURE,
                'slots' => $creature->inventoryCapacity(),
            ];
        });
    }
}
