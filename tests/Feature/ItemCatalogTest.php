<?php

namespace Tests\Feature;

use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\EquipmentSlot;
use App\Models\Item;
use App\Models\User;
use Database\Seeders\CreatureCatalogSeeder;
use Database\Seeders\EquipmentSlotSeeder;
use Database\Seeders\ItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_seeders_create_slots_and_starter_items(): void
    {
        $this->seed([
            CreatureCatalogSeeder::class,
            EquipmentSlotSeeder::class,
            ItemSeeder::class,
        ]);

        $this->assertSame(10, EquipmentSlot::query()->count());
        $this->assertSame(16, Item::query()->count());

        foreach (['head', 'body', 'primary-weapon', 'neural', 'artifact', 'accessory'] as $code) {
            $this->assertDatabaseHas('equipment_slots', [
                'code' => $code,
                'is_active' => true,
            ]);
        }

        $this->assertDatabaseHas('items', [
            'code' => 'reinforced-hide-plate',
            'rarity' => 'common',
            'slot_key' => 'body',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('items', [
            'code' => 'ancient-core',
            'rarity' => 'unique',
            'is_unique' => true,
        ]);
    }

    public function test_item_availability_respects_type_species_level_and_activity(): void
    {
        $animals = CreatureType::factory()->create(['code' => 'animals']);
        $insects = CreatureType::factory()->create(['code' => 'insects']);
        $wolf = CreatureSpecies::factory()->create([
            'creature_type_id' => $animals->id,
            'code' => 'wolf',
        ]);
        $mantis = CreatureSpecies::factory()->create([
            'creature_type_id' => $insects->id,
            'code' => 'mantis',
        ]);
        $user = User::factory()->create();
        $wolfCreature = $this->creatureFor($user, $animals, $wolf, ['level' => 2]);
        $mantisCreature = $this->creatureFor($user, $insects, $mantis, ['level' => 2]);

        $animalItem = Item::factory()->create([
            'allowed_types' => [(string) $animals->id],
            'allowed_species' => [(string) $wolf->id],
            'required_level' => 2,
        ]);
        $highLevelItem = Item::factory()->create([
            'allowed_types' => [$animals->id],
            'required_level' => 3,
        ]);
        $inactiveItem = Item::factory()->inactive()->create([
            'allowed_types' => [$animals->id],
        ]);

        $this->assertTrue($animalItem->canBeUsedBy($wolfCreature));
        $this->assertFalse($animalItem->canBeUsedBy($mantisCreature));
        $this->assertFalse($highLevelItem->canBeUsedBy($wolfCreature));
        $this->assertFalse($inactiveItem->canBeUsedBy($wolfCreature));
    }

    public function test_admin_can_open_item_catalog_resource_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $slot = EquipmentSlot::factory()->create();
        $item = Item::factory()->create([
            'slot_key' => $slot->code,
        ]);

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.equipment-slots.create'))
            ->assertOk()
            ->assertSee('Название');

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.items.create'))
            ->assertOk()
            ->assertSee('Редкость')
            ->assertSee('Бонусы');

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.equipment-slots.edit', ['record' => $slot]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.items.edit', ['record' => $item]))
            ->assertOk()
            ->assertSee($item->name);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function creatureFor(User $user, CreatureType $type, CreatureSpecies $species, array $attributes = []): Creature
    {
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
}
