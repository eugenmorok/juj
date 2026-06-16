<?php

namespace Tests\Feature;

use App\Models\BotProfile;
use App\Models\Creature;
use App\Models\CreatureEquipment;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\EquipmentSlot;
use App\Models\Item;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleasePreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_prepares_starter_mvp_content_and_bots(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(3, CreatureType::query()->count());
        $this->assertGreaterThanOrEqual(15, CreatureSpecies::query()->count());
        $this->assertGreaterThanOrEqual(6, Skill::query()->count());
        $this->assertGreaterThanOrEqual(10, EquipmentSlot::query()->count());
        $this->assertGreaterThanOrEqual(8, Item::query()->count());

        $this->assertDatabaseHas('users', [
            'email' => 'admin@rpg-arena.test',
            'is_admin' => true,
        ]);

        $this->assertGreaterThanOrEqual(5, BotProfile::query()->active()->count());
        $this->assertGreaterThanOrEqual(5, User::query()->where('is_bot', true)->count());
        $this->assertGreaterThanOrEqual(5, Creature::query()->whereHas('user', fn ($query) => $query->where('is_bot', true))->count());
        $this->assertGreaterThanOrEqual(1, CreatureEquipment::query()->count());
    }
}
