<?php

namespace Tests\Feature;

use App\Models\Creature;
use App\Models\CreatureEquipment;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\EquipmentSlot;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\User;
use Database\Seeders\EquipmentSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_open_equipment_page_and_equip_item_from_player_inventory(): void
    {
        $this->seed(EquipmentSlotSeeder::class);
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, ['strength' => 7]);
        $slot = EquipmentSlot::query()->where('code', 'body')->firstOrFail();
        $item = $this->equipmentItem($slot, [
            'name' => 'Reinforced Plate',
            'bonuses' => ['strength' => 2, 'hp' => 10],
        ]);
        $itemInstance = ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
        ]);
        $inventoryItem = $user->ensureInventory()->addItemInstance($itemInstance);

        $this->actingAs($user)
            ->get(route('entities.equipment', $creature))
            ->assertOk()
            ->assertSee('10 слотов экипировки')
            ->assertSee($slot->name)
            ->assertSee('Reinforced Plate');

        $this->actingAs($user)
            ->from(route('entities.equipment', $creature))
            ->post(route('entities.equipment.equip', [$creature, $inventoryItem]))
            ->assertRedirect(route('entities.equipment', $creature, absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('creature_equipment', [
            'creature_id' => $creature->id,
            'item_instance_id' => $itemInstance->id,
            'slot_key' => 'body',
        ]);
        $this->assertDatabaseMissing('inventory_items', [
            'id' => $inventoryItem->id,
        ]);
        $this->assertSame('equipped', $itemInstance->refresh()->state);
        $this->assertSame($creature->id, $itemInstance->bound_creature_id);
        $this->assertSame(9, $creature->refresh()->effectiveSpecialValues()['strength']);
        $this->assertSame($creature->max_hp + 10, $creature->effectiveMaxHp());
    }

    public function test_player_can_equip_item_from_creature_inventory_and_unequip_it_back(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, ['endurance' => 10]);
        $slot = EquipmentSlot::factory()->create(['code' => 'primary-weapon']);
        $item = $this->equipmentItem($slot);
        $itemInstance = ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
        ]);
        $creatureInventory = $creature->ensureInventory();
        $inventoryItem = $creatureInventory->addItemInstance($itemInstance);

        $this->actingAs($user)
            ->post(route('entities.equipment.equip', [$creature, $inventoryItem]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CreatureEquipment::query()->where('item_instance_id', $itemInstance->id)->count());

        $this->actingAs($user)
            ->from(route('entities.equipment', $creature))
            ->post(route('entities.equipment.unequip', [$creature, $itemInstance]))
            ->assertRedirect(route('entities.equipment', $creature, absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('creature_equipment', [
            'item_instance_id' => $itemInstance->id,
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'inventory_id' => $creatureInventory->id,
            'item_instance_id' => $itemInstance->id,
        ]);
        $this->assertSame('stored', $itemInstance->refresh()->state);
    }

    public function test_multi_slot_item_occupies_every_required_slot(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user);
        EquipmentSlot::factory()->create(['code' => 'body']);
        EquipmentSlot::factory()->create(['code' => 'defense']);
        $item = Item::factory()->create([
            'item_type' => 'equipment',
            'slot_key' => 'body',
            'slots_required' => ['body', 'defense'],
        ]);
        $itemInstance = ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
        ]);
        $inventoryItem = $user->ensureInventory()->addItemInstance($itemInstance);

        $this->actingAs($user)
            ->post(route('entities.equipment.equip', [$creature, $inventoryItem]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('creature_equipment', [
            'creature_id' => $creature->id,
            'slot_key' => 'body',
        ]);
        $this->assertDatabaseHas('creature_equipment', [
            'creature_id' => $creature->id,
            'slot_key' => 'defense',
        ]);
        $this->assertSame(2, CreatureEquipment::query()->where('item_instance_id', $itemInstance->id)->count());
    }

    public function test_player_cannot_equip_item_when_required_slot_is_occupied(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user);
        $slot = EquipmentSlot::factory()->create(['code' => 'body']);
        $firstItem = $this->equipmentItem($slot);
        $secondItem = $this->equipmentItem($slot);
        $playerInventory = $user->ensureInventory();
        $firstInventoryItem = $playerInventory->addItemInstance(ItemInstance::factory()->create([
            'item_id' => $firstItem->id,
            'owner_user_id' => $user->id,
        ]));
        $secondInventoryItem = $playerInventory->addItemInstance(ItemInstance::factory()->create([
            'item_id' => $secondItem->id,
            'owner_user_id' => $user->id,
        ]));

        $this->actingAs($user)
            ->post(route('entities.equipment.equip', [$creature, $firstInventoryItem]))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->from(route('entities.equipment', $creature))
            ->post(route('entities.equipment.equip', [$creature, $secondInventoryItem]))
            ->assertRedirect(route('entities.equipment', $creature, absolute: false))
            ->assertSessionHasErrors('equipment');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $secondInventoryItem->id,
        ]);
    }

    public function test_player_cannot_equip_incompatible_or_low_level_item(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, ['level' => 1]);
        $otherType = CreatureType::factory()->create();
        $slot = EquipmentSlot::factory()->create(['code' => 'neural']);
        $item = $this->equipmentItem($slot, [
            'required_level' => 3,
            'allowed_types' => [$otherType->id],
        ]);
        $inventoryItem = $user->ensureInventory()->addItemInstance(ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
        ]));

        $this->actingAs($user)
            ->from(route('entities.equipment', $creature))
            ->post(route('entities.equipment.equip', [$creature, $inventoryItem]))
            ->assertRedirect(route('entities.equipment', $creature, absolute: false))
            ->assertSessionHasErrors('equipment');

        $this->assertDatabaseMissing('creature_equipment', [
            'item_instance_id' => $inventoryItem->item_instance_id,
        ]);
    }

    public function test_player_cannot_equip_foreign_inventory_item(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $creature = $this->creatureFor($user);
        $slot = EquipmentSlot::factory()->create(['code' => 'artifact']);
        $item = $this->equipmentItem($slot);
        $foreignInventoryItem = $otherUser->ensureInventory()->addItemInstance(ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $otherUser->id,
        ]));

        $this->actingAs($user)
            ->post(route('entities.equipment.equip', [$creature, $foreignInventoryItem]))
            ->assertNotFound();
    }

    public function test_player_cannot_unequip_when_creature_inventory_is_full(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, ['endurance' => 1, 'level' => 1]);
        $slot = EquipmentSlot::factory()->create(['code' => 'accessory']);
        $item = $this->equipmentItem($slot);
        $itemInstance = ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
            'bound_creature_id' => $creature->id,
            'state' => 'equipped',
        ]);
        CreatureEquipment::factory()->create([
            'creature_id' => $creature->id,
            'item_instance_id' => $itemInstance->id,
            'slot_key' => 'accessory',
        ]);
        $creatureInventory = $creature->ensureInventory();

        for ($i = 0; $i < $creatureInventory->capacity(); $i++) {
            $creatureInventory->addItemInstance(ItemInstance::factory()->create(['owner_user_id' => $user->id]));
        }

        $this->actingAs($user)
            ->from(route('entities.equipment', $creature))
            ->post(route('entities.equipment.unequip', [$creature, $itemInstance]))
            ->assertRedirect(route('entities.equipment', $creature, absolute: false))
            ->assertSessionHasErrors('equipment');

        $this->assertDatabaseHas('creature_equipment', [
            'item_instance_id' => $itemInstance->id,
        ]);
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
            'strength' => 7,
            'perception' => 7,
            'endurance' => $endurance,
            'charisma' => 5,
            'intelligence' => 7,
            'agility' => 7,
            'luck' => 7,
            'current_hp' => $maxHp,
            'max_hp' => $maxHp,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function equipmentItem(EquipmentSlot $slot, array $attributes = []): Item
    {
        return Item::factory()->create([
            'item_type' => 'equipment',
            'slot_key' => $slot->code,
            'slots_required' => [$slot->code],
            'required_level' => 1,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
