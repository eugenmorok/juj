<?php

namespace Database\Factories;

use App\Models\Creature;
use App\Models\CreatureEquipment;
use App\Models\EquipmentSlot;
use App\Models\ItemInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreatureEquipment>
 */
class CreatureEquipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'creature_id' => Creature::factory(),
            'item_instance_id' => ItemInstance::factory(),
            'slot_key' => fn (): string => EquipmentSlot::factory()->create()->code,
        ];
    }
}
