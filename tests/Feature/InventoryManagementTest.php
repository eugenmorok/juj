<?php

namespace Tests\Feature;

use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_player_inventory_with_calculated_capacity(): void
    {
        $this->post(route('register'), [
            'name' => 'Inventory Player',
            'email' => 'inventory@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'inventory@example.com')->firstOrFail();

        $this->assertSame(7, $user->inventoryCapacity());
        $this->assertDatabaseHas('inventories', [
            'owner_user_id' => $user->id,
            'creature_id' => null,
            'inventory_type' => Inventory::TYPE_PLAYER,
            'slots' => 7,
        ]);
    }

    public function test_creature_creation_creates_creature_inventory(): void
    {
        $user = User::factory()->create();
        $species = $this->starterSpecies();

        $this->actingAs($user)->post(route('entities.store'), [
            'name' => 'Carrier',
            'creature_species_id' => $species->id,
            'strength' => 6,
            'perception' => 6,
            'endurance' => 10,
            'charisma' => 5,
            'intelligence' => 5,
            'agility' => 6,
            'luck' => 5,
            'skills' => [],
        ]);

        $creature = Creature::query()->firstOrFail();

        $this->assertSame(4, $creature->inventoryCapacity());
        $this->assertDatabaseHas('inventories', [
            'owner_user_id' => $user->id,
            'creature_id' => $creature->id,
            'inventory_type' => Inventory::TYPE_CREATURE,
            'slots' => 4,
        ]);
    }

    public function test_player_can_move_item_to_creature_and_back(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, ['endurance' => 10]);
        $playerInventory = $user->ensureInventory();
        $creatureInventory = $creature->ensureInventory();
        $itemInstance = ItemInstance::factory()->create(['owner_user_id' => $user->id]);
        $inventoryItem = $playerInventory->addItemInstance($itemInstance);

        $this->actingAs($user)
            ->from(route('inventory'))
            ->post(route('inventory-items.move-to-creature', $inventoryItem), [
                'creature_id' => $creature->id,
            ])
            ->assertRedirect(route('inventory', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame($creatureInventory->id, $inventoryItem->refresh()->inventory_id);
        $this->assertSame($creature->id, $itemInstance->refresh()->bound_creature_id);

        $this->actingAs($user)
            ->from(route('entities.show', $creature))
            ->post(route('inventory-items.move-to-player', $inventoryItem))
            ->assertRedirect(route('entities.show', $creature, absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame($playerInventory->id, $inventoryItem->refresh()->inventory_id);
        $this->assertNull($itemInstance->refresh()->bound_creature_id);
    }

    public function test_player_cannot_move_item_when_target_inventory_is_full(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, ['endurance' => 1, 'level' => 1]);
        $playerInventory = $user->ensureInventory();
        $creatureInventory = $creature->ensureInventory();

        for ($i = 0; $i < $creatureInventory->capacity(); $i++) {
            $creatureInventory->addItemInstance(ItemInstance::factory()->create(['owner_user_id' => $user->id]));
        }

        $inventoryItem = $playerInventory->addItemInstance(ItemInstance::factory()->create(['owner_user_id' => $user->id]));

        $this->actingAs($user)
            ->from(route('inventory'))
            ->post(route('inventory-items.move-to-creature', $inventoryItem), [
                'creature_id' => $creature->id,
            ])
            ->assertRedirect(route('inventory', absolute: false))
            ->assertSessionHasErrors('inventory');

        $this->assertSame($playerInventory->id, $inventoryItem->refresh()->inventory_id);
    }

    public function test_player_cannot_move_foreign_inventory_item(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $creature = $this->creatureFor($user, ['endurance' => 10]);
        $foreignInventory = $otherUser->ensureInventory();
        $foreignItem = $foreignInventory->addItemInstance(ItemInstance::factory()->create(['owner_user_id' => $otherUser->id]));

        $this->actingAs($user)
            ->post(route('inventory-items.move-to-creature', $foreignItem), [
                'creature_id' => $creature->id,
            ])
            ->assertNotFound();
    }

    public function test_player_can_sell_stored_inventory_item_for_tokens(): void
    {
        $user = User::factory()->create(['tokens' => 5]);
        $item = Item::factory()->create(['price' => 81]);
        $itemInstance = ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
        ]);
        $inventoryItem = $user->ensureInventory()->addItemInstance($itemInstance);

        $this->actingAs($user)
            ->from(route('inventory'))
            ->post(route('inventory-items.sell', $inventoryItem))
            ->assertRedirect(route('inventory', absolute: false))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Предмет продан за 40 токенов.');

        $this->assertSame(45, $user->refresh()->tokens);
        $this->assertDatabaseMissing('inventory_items', ['id' => $inventoryItem->id]);
        $this->assertDatabaseHas('item_instances', [
            'id' => $itemInstance->id,
            'owner_user_id' => $user->id,
            'bound_creature_id' => null,
            'state' => 'sold',
        ]);
    }

    public function test_player_cannot_sell_foreign_or_battle_locked_creature_inventory_item(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignInventoryItem = $otherUser->ensureInventory()->addItemInstance(ItemInstance::factory()->create([
            'owner_user_id' => $otherUser->id,
        ]));

        $this->actingAs($user)
            ->post(route('inventory-items.sell', $foreignInventoryItem))
            ->assertNotFound();

        $creature = $this->creatureFor($user, [
            'endurance' => 10,
            'is_available_for_battle' => false,
        ]);
        $creatureInventory = $creature->ensureInventory();
        $inventoryItem = $creatureInventory->addItemInstance(ItemInstance::factory()->create([
            'owner_user_id' => $user->id,
        ]));

        $this->actingAs($user)
            ->from(route('inventory'))
            ->post(route('inventory-items.sell', $inventoryItem))
            ->assertRedirect(route('inventory', absolute: false))
            ->assertSessionHasErrors('inventory');

        $this->assertDatabaseHas('inventory_items', ['id' => $inventoryItem->id]);
        $this->assertSame('stored', $inventoryItem->itemInstance->refresh()->state);
    }

    public function test_player_cannot_move_items_from_creature_in_battle(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, [
            'endurance' => 10,
            'is_available_for_battle' => false,
        ]);
        $creatureInventory = $creature->ensureInventory();
        $inventoryItem = $creatureInventory->addItemInstance(ItemInstance::factory()->create(['owner_user_id' => $user->id]));

        $this->actingAs($user)
            ->from(route('entities.show', $creature))
            ->post(route('inventory-items.move-to-player', $inventoryItem))
            ->assertRedirect(route('entities.show', $creature, absolute: false))
            ->assertSessionHasErrors('inventory');

        $this->assertSame($creatureInventory->id, $inventoryItem->refresh()->inventory_id);
    }

    public function test_inventory_page_displays_player_and_creature_items(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, ['name' => 'Scout Carrier', 'endurance' => 10]);
        $playerInventory = $user->ensureInventory();
        $creatureInventory = $creature->ensureInventory();
        $playerInventory->addItemInstance(ItemInstance::factory()->create(['owner_user_id' => $user->id]));
        $creatureInventory->addItemInstance(ItemInstance::factory()->create(['owner_user_id' => $user->id]));

        $this->actingAs($user)
            ->get(route('inventory'))
            ->assertOk()
            ->assertSee('Общий инвентарь игрока')
            ->assertSee('Scout Carrier')
            ->assertSee('Передать')
            ->assertSee('Забрать')
            ->assertSee('Продать за');
    }

    public function test_item_interfaces_show_description_bonuses_and_duration(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, ['name' => 'Effect Reader']);
        $item = Item::factory()->potion()->create([
            'name' => 'Тактическая сыворотка',
            'description' => 'Восстанавливает бойца и ускоряет реакцию.',
            'bonuses' => ['heal' => 25, 'agility' => 2],
            'duration_type' => 'consumable',
            'uses_count' => 3,
        ]);
        $user->ensureInventory()->addItemInstance(ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
            'durability' => 3,
        ]));

        foreach ([route('inventory'), route('entities.show', $creature)] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertSeeText('Тактическая сыворотка')
                ->assertSeeText('Восстанавливает бойца и ускоряет реакцию.')
                ->assertSeeText('Лечение')
                ->assertSeeText('+25')
                ->assertSeeText('Ловкость')
                ->assertSeeText('+2')
                ->assertSeeText('Расходуется')
                ->assertSeeText('3 исп.');
        }
    }

    public function test_inventory_page_filters_items_by_catalog_fields_and_location(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, ['name' => 'Tonic Carrier', 'endurance' => 10]);
        $playerInventory = $user->ensureInventory();
        $creatureInventory = $creature->ensureInventory();
        $playerItem = Item::factory()->create([
            'name' => 'Rust Plate',
            'item_type' => 'equipment',
            'rarity' => 'common',
        ]);
        $creatureItem = Item::factory()->potion()->create([
            'name' => 'Rare Tonic',
            'item_type' => 'potion',
            'rarity' => 'rare',
        ]);

        $playerInventory->addItemInstance(ItemInstance::factory()->create([
            'item_id' => $playerItem->id,
            'owner_user_id' => $user->id,
        ]));
        $creatureInventory->addItemInstance(ItemInstance::factory()->create([
            'item_id' => $creatureItem->id,
            'owner_user_id' => $user->id,
        ]));

        $this->actingAs($user)
            ->get(route('inventory', [
                'q' => 'Tonic',
                'item_type' => 'potion',
                'rarity' => 'rare',
                'location' => 'creatures',
            ]))
            ->assertOk()
            ->assertSee('Rare Tonic')
            ->assertSee('Tonic Carrier')
            ->assertDontSee('Rust Plate');
    }

    public function test_player_can_use_healing_consumable_from_player_inventory(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, ['endurance' => 8]);
        $creature->forceFill(['current_hp' => $creature->max_hp - 40])->save();
        $item = Item::factory()->potion()->create([
            'name' => 'Healing Draft',
            'bonuses' => ['heal' => 25],
            'uses_count' => 1,
        ]);
        $itemInstance = ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
            'durability' => 100,
        ]);
        $inventoryItem = $user->ensureInventory()->addItemInstance($itemInstance);

        $this->actingAs($user)
            ->from(route('inventory'))
            ->post(route('inventory-items.use', $inventoryItem), [
                'creature_id' => $creature->id,
            ])
            ->assertRedirect(route('inventory', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame($creature->max_hp - 15, $creature->refresh()->current_hp);
        $this->assertDatabaseMissing('inventory_items', ['id' => $inventoryItem->id]);
        $this->assertDatabaseHas('item_instances', [
            'id' => $itemInstance->id,
            'durability' => 0,
            'state' => 'used',
        ]);
    }

    public function test_multi_use_consumable_stays_in_inventory_until_last_charge(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, ['endurance' => 8]);
        $creature->forceFill(['current_hp' => $creature->max_hp - 60])->save();
        $item = Item::factory()->potion()->create([
            'name' => 'Two Dose Serum',
            'bonuses' => ['heal' => 20],
            'uses_count' => 2,
        ]);
        $itemInstance = ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
            'durability' => 2,
        ]);
        $inventoryItem = $user->ensureInventory()->addItemInstance($itemInstance);

        $this->actingAs($user)
            ->post(route('inventory-items.use', $inventoryItem), [
                'creature_id' => $creature->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($creature->max_hp - 40, $creature->refresh()->current_hp);
        $this->assertDatabaseHas('inventory_items', ['id' => $inventoryItem->id]);
        $this->assertSame(1, $itemInstance->refresh()->durability);

        $this->actingAs($user)
            ->post(route('inventory-items.use', $inventoryItem), [
                'creature_id' => $creature->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($creature->max_hp - 20, $creature->refresh()->current_hp);
        $this->assertDatabaseMissing('inventory_items', ['id' => $inventoryItem->id]);
        $this->assertSame('used', $itemInstance->refresh()->state);
    }

    public function test_consumable_can_increase_special_and_max_hp(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, [
            'strength' => 4,
            'endurance' => 5,
        ]);
        $creature->forceFill(['current_hp' => $creature->max_hp - 20])->save();
        $oldMaxHp = $creature->max_hp;
        $item = Item::factory()->potion()->create([
            'name' => 'Mutagen Tonic',
            'bonuses' => ['strength' => 2, 'endurance' => 1, 'hp' => 10],
            'uses_count' => 1,
        ]);
        $inventoryItem = $user->ensureInventory()->addItemInstance(ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
            'durability' => 1,
        ]));

        $this->actingAs($user)
            ->post(route('inventory-items.use', $inventoryItem), [
                'creature_id' => $creature->id,
            ])
            ->assertSessionHasNoErrors();

        $creature->refresh();

        $this->assertSame(6, $creature->strength);
        $this->assertSame(6, $creature->endurance);
        $this->assertSame($oldMaxHp + 20, $creature->max_hp);
        $this->assertSame($oldMaxHp, $creature->current_hp);
    }

    public function test_player_cannot_use_creature_inventory_consumable_on_another_creature(): void
    {
        $user = User::factory()->create();
        $sourceCreature = $this->creatureFor($user, ['name' => 'Source Carrier']);
        $targetCreature = $this->creatureFor($user, ['name' => 'Target Carrier']);
        $targetCreature->forceFill(['current_hp' => $targetCreature->max_hp - 30])->save();
        $item = Item::factory()->potion()->create(['bonuses' => ['heal' => 20]]);
        $inventoryItem = $sourceCreature->ensureInventory()->addItemInstance(ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
            'durability' => 1,
        ]));

        $this->actingAs($user)
            ->from(route('entities.show', $sourceCreature))
            ->post(route('inventory-items.use', $inventoryItem), [
                'creature_id' => $targetCreature->id,
            ])
            ->assertRedirect(route('entities.show', $sourceCreature, absolute: false))
            ->assertSessionHasErrors('inventory');

        $this->assertSame($targetCreature->max_hp - 30, $targetCreature->refresh()->current_hp);
        $this->assertDatabaseHas('inventory_items', ['id' => $inventoryItem->id]);
    }

    public function test_player_cannot_use_foreign_consumable(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $creature = $this->creatureFor($user, ['endurance' => 8]);
        $item = Item::factory()->potion()->create();
        $foreignInventoryItem = $otherUser->ensureInventory()->addItemInstance(ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $otherUser->id,
        ]));

        $this->actingAs($user)
            ->post(route('inventory-items.use', $foreignInventoryItem), [
                'creature_id' => $creature->id,
            ])
            ->assertNotFound();
    }

    public function test_player_cannot_use_non_consumable_item(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, ['endurance' => 8]);
        $item = Item::factory()->create(['item_type' => 'equipment']);
        $inventoryItem = $user->ensureInventory()->addItemInstance(ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
        ]));

        $this->actingAs($user)
            ->from(route('inventory'))
            ->post(route('inventory-items.use', $inventoryItem), [
                'creature_id' => $creature->id,
            ])
            ->assertRedirect(route('inventory', absolute: false))
            ->assertSessionHasErrors('item');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function creatureFor(User $user, array $attributes = []): Creature
    {
        $type = CreatureType::factory()->create();
        $species = CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'base_strength' => 1,
            'base_perception' => 1,
            'base_endurance' => 1,
            'base_charisma' => 1,
            'base_intelligence' => 1,
            'base_agility' => 1,
            'base_luck' => 1,
        ]);
        $endurance = $attributes['endurance'] ?? 7;
        $maxHp = Creature::maxHpForEndurance($endurance);

        return Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $type->id,
            'creature_species_id' => $species->id,
            'endurance' => $endurance,
            'current_hp' => $maxHp,
            'max_hp' => $maxHp,
            ...$attributes,
        ]);
    }

    private function starterSpecies(): CreatureSpecies
    {
        $type = CreatureType::factory()->create();

        return CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'base_strength' => 5,
            'base_perception' => 5,
            'base_endurance' => 5,
            'base_charisma' => 5,
            'base_intelligence' => 5,
            'base_agility' => 5,
            'base_luck' => 5,
            'is_active' => true,
            'is_starter_available' => true,
        ]);
    }
}
