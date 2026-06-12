<?php

namespace Database\Factories;

use App\Models\ArenaSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArenaSetting>
 */
class ArenaSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ArenaSetting::defaults();
    }
}
