<?php

namespace Tests\Feature;

use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_page_displays_active_items_and_filters_catalog(): void
    {
        $user = User::factory()->create(['tokens' => 300]);
        $rareItem = Item::factory()->create([
            'name' => 'Rare Lens',
            'rarity' => 'rare',
            'price' => 120,
            'required_level' => 1,
            'bonuses' => ['damage' => 6, 'defense' => 4],
        ]);
        Item::factory()->inactive()->create(['name' => 'Hidden Item']);
        Item::factory()->create(['name' => 'Common Plate', 'rarity' => 'common']);

        $this->actingAs($user)
            ->get(route('shop', ['rarity' => 'rare']))
            ->assertOk()
            ->assertSee('Магазин')
            ->assertSee('width: 85%; max-width: none;', false)
            ->assertSee('item-details__tile', false)
            ->assertDontSee('bg-zinc-950/60', false)
            ->assertSee($rareItem->name)
            ->assertSeeText('Урон')
            ->assertSeeText('+6')
            ->assertSeeText('Защита')
            ->assertSeeText('+4')
            ->assertDontSee('Hidden Item')
            ->assertDontSee('Common Plate');
    }

    public function test_player_can_buy_item_into_player_inventory(): void
    {
        $user = User::factory()->create(['tokens' => 200]);
        $inventory = $user->ensureInventory();
        $item = Item::factory()->create(['price' => 80, 'required_level' => 1]);

        $this->actingAs($user)
            ->from(route('shop'))
            ->post(route('shop.items.buy', $item))
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame(120, $user->refresh()->tokens);
        $this->assertDatabaseHas('item_instances', [
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
            'bound_creature_id' => null,
            'state' => 'stored',
        ]);
        $this->assertSame(1, $inventory->refresh()->inventoryItems()->count());
    }

    public function test_player_level_discount_reduces_item_purchase_price(): void
    {
        $user = User::factory()->create([
            'tokens' => 100,
            'level' => 11,
        ]);
        $item = Item::factory()->create(['price' => 100, 'required_level' => 1]);

        $this->actingAs($user)
            ->from(route('shop'))
            ->post(route('shop.items.buy', $item))
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame(10, $user->refresh()->tokens);
    }

    public function test_shop_search_and_available_filter_hide_unavailable_items(): void
    {
        $user = User::factory()->create(['tokens' => 300, 'level' => 1]);
        $availableItem = Item::factory()->create([
            'name' => 'Rare Lens',
            'rarity' => 'rare',
            'price' => 120,
            'required_level' => 1,
        ]);

        Item::factory()->create([
            'name' => 'Locked Lens',
            'rarity' => 'rare',
            'price' => 120,
            'required_level' => 3,
        ]);
        Item::factory()->create([
            'name' => 'Costly Lens',
            'rarity' => 'rare',
            'price' => 400,
            'required_level' => 1,
        ]);
        Item::factory()->create(['name' => 'Common Plate', 'rarity' => 'common']);

        $this->actingAs($user)
            ->get(route('shop', [
                'q' => 'Lens',
                'rarity' => 'rare',
                'available' => '1',
            ]))
            ->assertOk()
            ->assertSee($availableItem->name)
            ->assertDontSee('Locked Lens')
            ->assertDontSee('Costly Lens')
            ->assertDontSee('Common Plate');
    }

    public function test_player_cannot_buy_item_without_tokens_or_inventory_space(): void
    {
        $poorUser = User::factory()->create(['tokens' => 10]);
        $item = Item::factory()->create(['price' => 80]);

        $this->actingAs($poorUser)
            ->from(route('shop'))
            ->post(route('shop.items.buy', $item))
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasErrors('tokens');

        $fullUser = User::factory()->create(['tokens' => 500]);
        $inventory = $fullUser->ensureInventory();

        for ($i = 0; $i < $inventory->capacity(); $i++) {
            $inventory->addItemInstance(ItemInstance::factory()->create(['owner_user_id' => $fullUser->id]));
        }

        $this->actingAs($fullUser)
            ->from(route('shop'))
            ->post(route('shop.items.buy', $item))
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasErrors('inventory');

        $this->assertSame($inventory->capacity(), $inventory->refresh()->inventoryItems()->count());
    }

    public function test_player_cannot_buy_unavailable_or_duplicate_unique_item(): void
    {
        $user = User::factory()->create(['tokens' => 1000, 'level' => 1]);
        $highLevelItem = Item::factory()->create(['price' => 80, 'required_level' => 3]);

        $this->actingAs($user)
            ->from(route('shop'))
            ->post(route('shop.items.buy', $highLevelItem))
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasErrors('item');

        $uniqueItem = Item::factory()->unique()->create(['price' => 80, 'required_level' => 1]);
        $user->ensureInventory()->addItemInstance(ItemInstance::factory()->create([
            'item_id' => $uniqueItem->id,
            'owner_user_id' => $user->id,
        ]));

        $this->actingAs($user)
            ->from(route('shop'))
            ->post(route('shop.items.buy', $uniqueItem))
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasErrors('item');

        $this->assertSame(1, ItemInstance::query()->where('item_id', $uniqueItem->id)->count());
    }

    public function test_player_can_buy_unique_item_again_after_selling_it(): void
    {
        $user = User::factory()->create(['tokens' => 1000, 'level' => 1]);
        $uniqueItem = Item::factory()->unique()->create([
            'price' => 80,
            'required_level' => 1,
        ]);
        ItemInstance::factory()->create([
            'item_id' => $uniqueItem->id,
            'owner_user_id' => $user->id,
            'state' => 'sold',
        ]);

        $this->actingAs($user)
            ->from(route('shop'))
            ->post(route('shop.items.buy', $uniqueItem))
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame(920, $user->refresh()->tokens);
        $this->assertSame(2, ItemInstance::query()->where('item_id', $uniqueItem->id)->count());
        $this->assertDatabaseHas('item_instances', [
            'item_id' => $uniqueItem->id,
            'owner_user_id' => $user->id,
            'state' => 'stored',
        ]);
    }

    public function test_player_can_buy_inventory_expansion(): void
    {
        $user = User::factory()->create(['tokens' => 200, 'inventory_slots' => 5]);
        $inventory = $user->ensureInventory();

        $this->assertSame(7, $inventory->capacity());

        $this->actingAs($user)
            ->from(route('shop'))
            ->post(route('shop.inventory-slots.buy'))
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $inventory->refresh();

        $this->assertSame(100, $user->tokens);
        $this->assertSame(6, $user->inventory_slots);
        $this->assertSame(8, $inventory->capacity());
        $this->assertDatabaseHas('inventories', [
            'owner_user_id' => $user->id,
            'inventory_type' => Inventory::TYPE_PLAYER,
            'slots' => 8,
        ]);
    }

    public function test_player_can_use_rename_service(): void
    {
        $user = User::factory()->create(['tokens' => 100]);
        $creature = Creature::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

        $this->actingAs($user)
            ->from(route('shop'))
            ->post(route('shop.services.rename-creature'), [
                'creature_id' => $creature->id,
                'name' => 'New Name',
            ])
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $creature->refresh()->name);
        $this->assertSame(75, $user->refresh()->tokens);
    }

    public function test_player_can_reset_skills_for_tokens(): void
    {
        $user = User::factory()->create(['tokens' => 200]);
        $creature = Creature::factory()->create(['user_id' => $user->id, 'development_points' => 5]);
        $firstSkill = Skill::factory()->create(['cost' => 10]);
        $secondSkill = Skill::factory()->create(['cost' => 20]);

        $creature->skills()->attach($firstSkill->id, ['cost_paid' => 10, 'source' => 'development']);
        $creature->skills()->attach($secondSkill->id, ['cost_paid' => 20, 'source' => 'development']);

        $this->actingAs($user)
            ->from(route('shop'))
            ->post(route('shop.services.reset-skills'), [
                'creature_id' => $creature->id,
            ])
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame(80, $user->refresh()->tokens);
        $this->assertSame(35, $creature->refresh()->development_points);
        $this->assertSame(0, $creature->skills()->count());
    }

    public function test_player_can_reset_special_to_species_base_for_tokens(): void
    {
        $user = User::factory()->create(['tokens' => 300]);
        $type = CreatureType::factory()->create();
        $species = CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'base_strength' => 5,
            'base_perception' => 5,
            'base_endurance' => 5,
            'base_charisma' => 5,
            'base_intelligence' => 5,
            'base_agility' => 5,
            'base_luck' => 5,
        ]);
        $creature = Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $type->id,
            'creature_species_id' => $species->id,
            'development_points' => 7,
            'strength' => 7,
            'perception' => 6,
            'endurance' => 8,
            'charisma' => 5,
            'intelligence' => 5,
            'agility' => 5,
            'luck' => 5,
            'max_hp' => Creature::maxHpForEndurance(8),
            'current_hp' => Creature::maxHpForEndurance(8),
        ]);

        $this->actingAs($user)
            ->from(route('shop'))
            ->post(route('shop.services.reset-special'), [
                'creature_id' => $creature->id,
            ])
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasNoErrors();

        $creature->refresh();

        $this->assertSame(120, $user->refresh()->tokens);
        $this->assertSame(13, $creature->development_points);
        $this->assertSame(5, $creature->strength);
        $this->assertSame(5, $creature->perception);
        $this->assertSame(5, $creature->endurance);
        $this->assertSame(Creature::maxHpForEndurance(5), $creature->max_hp);
        $this->assertSame(Creature::maxHpForEndurance(5), $creature->current_hp);
    }
}
