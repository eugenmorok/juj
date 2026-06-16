<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\ItemInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_id' => Inventory::factory(),
            'item_instance_id' => ItemInstance::factory(),
            'slot_number' => fake()->unique()->numberBetween(1, 100),
        ];
    }
}
