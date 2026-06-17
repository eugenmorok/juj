<?php

namespace Database\Factories;

use App\Models\EquipmentSlot;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'code' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'description' => fake()->sentence(),
            'icon' => null,
            'item_type' => 'equipment',
            'rarity' => 'common',
            'price' => fake()->numberBetween(20, 250),
            'required_level' => 1,
            'allowed_types' => null,
            'allowed_species' => null,
            'slot_key' => fn (): string => EquipmentSlot::factory()->create()->code,
            'slots_required' => null,
            'bonuses' => ['strength' => 1],
            'duration_type' => 'permanent',
            'uses_count' => null,
            'is_unique' => false,
            'is_active' => true,
        ];
    }

    public function potion(): static
    {
        return $this->state(fn (array $attributes) => [
            'item_type' => 'potion',
            'slot_key' => null,
            'slots_required' => null,
            'bonuses' => ['heal' => 25],
            'duration_type' => 'consumable',
            'uses_count' => 1,
        ]);
    }

    public function unique(): static
    {
        return $this->state(fn (array $attributes) => [
            'rarity' => 'unique',
            'is_unique' => true,
            'required_level' => 3,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
