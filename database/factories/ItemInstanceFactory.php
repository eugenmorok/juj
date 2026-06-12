<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemInstance>
 */
class ItemInstanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'owner_user_id' => User::factory(),
            'bound_creature_id' => null,
            'durability' => 100,
            'state' => 'stored',
        ];
    }

    public function equipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'equipped',
        ]);
    }
}
