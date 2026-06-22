<?php

namespace Tests\Feature;

use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\EquipmentSlot;
use App\Models\Item;
use App\Models\ShopGenerationState;
use App\Models\User;
use App\Services\ShopItemGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopItemGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_generates_two_or_three_persistent_items_at_most_once_per_three_hours(): void
    {
        $type = CreatureType::factory()->create(['name' => 'Животные']);
        CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'name' => 'Волк',
        ]);
        EquipmentSlot::factory()->create(['code' => 'body']);

        $service = app(ShopItemGenerationService::class);
        $firstBatch = $service->generateIfDue();

        $this->assertContains($firstBatch->count(), [2, 3]);
        $this->assertSame($firstBatch->count(), Item::query()->where('is_generated', true)->count());
        $this->assertTrue($firstBatch->every(fn (Item $item): bool => $item->is_active && $item->generated_at !== null));
        $this->assertTrue($firstBatch->every(fn (Item $item): bool => str_starts_with((string) $item->icon, 'game-assets/shop/')));

        $this->assertCount(0, $service->generateIfDue());
        $this->assertSame($firstBatch->count(), Item::query()->where('is_generated', true)->count());

        $this->travel(3)->hours();
        $this->travel(1)->second();
        $secondBatch = $service->generateIfDue();

        $this->assertContains($secondBatch->count(), [2, 3]);
        $this->assertSame($firstBatch->count() + $secondBatch->count(), Item::query()->where('is_generated', true)->count());
    }

    public function test_generated_weapon_and_defense_slots_receive_direct_combat_bonuses(): void
    {
        $service = app(ShopItemGenerationService::class);
        $combatBonus = new \ReflectionMethod($service, 'combatBonus');

        $this->assertSame(['damage' => 4], $combatBonus->invoke($service, 'primary-weapon', 2));
        $this->assertSame(['damage' => 4], $combatBonus->invoke($service, 'secondary-weapon', 2));
        $this->assertSame(['defense' => 4], $combatBonus->invoke($service, 'body', 2));
        $this->assertSame(['defense' => 4], $combatBonus->invoke($service, 'defense', 2));
    }

    public function test_shop_displays_sixteen_random_items_and_entity_applicability(): void
    {
        $user = User::factory()->create();
        $type = CreatureType::factory()->create(['name' => 'Механоиды']);
        $species = CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'name' => 'Штурмовик',
        ]);

        ShopGenerationState::query()->create([
            'id' => 1,
            'last_generated_at' => now(),
        ]);

        Item::factory()->create([
            'name' => 'Тестовый модуль применимости',
            'code' => 'applicability-module',
            'allowed_types' => [$type->id],
            'allowed_species' => [$species->id],
        ]);
        Item::factory()->count(19)->create();

        $this->actingAs($user)
            ->get(route('shop'))
            ->assertOk()
            ->assertViewHas('items', fn ($items): bool => $items->count() === 16);

        $this->actingAs($user)
            ->get(route('shop', ['q' => 'applicability-module']))
            ->assertOk()
            ->assertSee('Типы: Механоиды; виды: Штурмовик');
    }
}
