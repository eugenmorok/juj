<?php

namespace Tests\Feature;

use App\Models\ArenaSetting;
use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Creature;
use App\Models\CreatureEquipment;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\EquipmentSlot;
use App\Models\Item;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MvpStabilizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_player_can_complete_core_mvp_loop(): void
    {
        $this->stableArenaSettings();
        [$type, $species] = $this->starterCatalog();
        $slot = EquipmentSlot::factory()->create(['code' => 'body']);
        $freeArmor = Item::factory()->create([
            'name' => 'Starter Plate',
            'item_type' => 'equipment',
            'rarity' => 'common',
            'price' => 0,
            'required_level' => 1,
            'slot_key' => $slot->code,
            'slots_required' => [$slot->code],
            'bonuses' => ['strength' => 2, 'damage' => 3],
            'is_active' => true,
        ]);
        $postBattleShopItem = Item::factory()->potion()->create([
            'name' => 'Reward Tonic',
            'price' => 1,
            'required_level' => 1,
            'is_active' => true,
        ]);
        $postBattleSkill = Skill::factory()->notStarter()->create([
            'name' => 'Battle Focus',
            'cost' => 1,
            'required_level' => 2,
            'required_creature_type_id' => $type->id,
            'required_creature_species_id' => $species->id,
        ]);

        $this->post(route('register'), [
            'name' => 'MVP Runner',
            'email' => 'mvp-runner@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'mvp-runner@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, $user->tokens);
        $this->assertSame(7, $user->ensureInventory()->capacity());

        $this->post(route('entities.store'), [
            'name' => 'Full Loop Entity',
            'creature_species_id' => $species->id,
            'strength' => 20,
            'perception' => 20,
            'endurance' => 20,
            'charisma' => 15,
            'intelligence' => 20,
            'agility' => 20,
            'luck' => 20,
            'skills' => [],
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $creature = Creature::query()
            ->where('user_id', $user->id)
            ->where('name', 'Full Loop Entity')
            ->firstOrFail();

        $this->assertSame(100, $creature->spentCreationPoints($species));
        $this->assertSame(Creature::maxHpForEndurance(20), $creature->max_hp);
        $this->assertSame($creature->inventoryCapacity(), $creature->ensureInventory()->capacity());

        $this->from(route('shop'))
            ->post(route('shop.items.buy', $freeArmor))
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasNoErrors();

        $armorInventoryItem = $user
            ->ensureInventory()
            ->inventoryItems()
            ->whereHas('itemInstance', fn ($query) => $query->where('item_id', $freeArmor->id))
            ->firstOrFail();

        $this->from(route('inventory'))
            ->post(route('inventory-items.move-to-creature', $armorInventoryItem), [
                'creature_id' => $creature->id,
            ])
            ->assertRedirect(route('inventory', absolute: false))
            ->assertSessionHasNoErrors();

        $creatureInventoryItem = $creature
            ->ensureInventory()
            ->inventoryItems()
            ->where('item_instance_id', $armorInventoryItem->item_instance_id)
            ->firstOrFail();

        $this->from(route('entities.equipment', $creature))
            ->post(route('entities.equipment.equip', [$creature, $creatureInventoryItem]))
            ->assertRedirect(route('entities.equipment', $creature, absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('creature_equipment', [
            'creature_id' => $creature->id,
            'item_instance_id' => $creatureInventoryItem->item_instance_id,
            'slot_key' => $slot->code,
        ]);
        $this->assertSame(22, $creature->refresh()->effectiveSpecialValues()['strength']);

        $opponentUser = User::factory()->create();
        $opponent = $this->opponentCreature($opponentUser, $type, $species);

        $tokensBeforeBattle = $user->refresh()->tokens;
        $developmentBeforeBattle = $creature->refresh()->development_points;

        $this->from(route('arena'))
            ->post(route('arena.battles.start'), ['creature_id' => $creature->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $battle = Battle::query()->latest('id')->firstOrFail();
        $participant = BattleParticipant::query()
            ->where('battle_id', $battle->id)
            ->where('creature_id', $creature->id)
            ->firstOrFail();

        $this->assertSame($opponent->id, $battle->participants()->where('creature_id', '!=', $creature->id)->firstOrFail()->creature_id);
        $this->assertGreaterThan(0, $participant->reward_xp);
        $this->assertGreaterThan(0, $participant->reward_tokens);
        $this->assertGreaterThan(0, $participant->reward_development_points);
        $this->assertGreaterThan($tokensBeforeBattle, $user->refresh()->tokens);
        $this->assertGreaterThan($developmentBeforeBattle, $creature->refresh()->development_points);
        $this->assertGreaterThan(1, $creature->level);
        $this->assertDatabaseHas('battle_events', [
            'battle_id' => $battle->id,
            'event_type' => 'rewards_applied',
        ]);

        $developmentBeforeSkill = $creature->development_points;

        $this->post(route('entities.skills.purchase', [$creature, $postBattleSkill]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('creature_skills', [
            'creature_id' => $creature->id,
            'skill_id' => $postBattleSkill->id,
            'cost_paid' => 1,
            'source' => 'development',
        ]);
        $this->assertSame($developmentBeforeSkill - 1, $creature->refresh()->development_points);

        $tokensBeforeShopSpend = $user->refresh()->tokens;

        $this->from(route('shop'))
            ->post(route('shop.items.buy', $postBattleShopItem))
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame($tokensBeforeShopSpend - 1, $user->refresh()->tokens);
        $this->assertDatabaseHas('item_instances', [
            'item_id' => $postBattleShopItem->id,
            'owner_user_id' => $user->id,
            'state' => 'stored',
        ]);
    }

    public function test_player_cannot_operate_on_foreign_creature_through_game_routes(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [$type, $species] = $this->starterCatalog();
        $foreignCreature = $this->opponentCreature($owner, $type, $species);
        $skill = Skill::factory()->create(['cost' => 1]);

        $this->actingAs($intruder)
            ->get(route('entities.show', $foreignCreature))
            ->assertNotFound();

        $this->actingAs($intruder)
            ->get(route('entities.equipment', $foreignCreature))
            ->assertNotFound();

        $this->actingAs($intruder)
            ->post(route('arena.battles.start'), ['creature_id' => $foreignCreature->id])
            ->assertNotFound();

        $this->actingAs($intruder)
            ->post(route('entities.skills.purchase', [$foreignCreature, $skill]))
            ->assertNotFound();

        $this->actingAs($intruder)
            ->post(route('shop.services.rename-creature'), [
                'creature_id' => $foreignCreature->id,
                'name' => 'Stolen Name',
            ])
            ->assertNotFound();

        $this->assertSame($owner->id, $foreignCreature->refresh()->user_id);
        $this->assertNotSame('Stolen Name', $foreignCreature->name);
        $this->assertSame(0, CreatureEquipment::query()->where('creature_id', $foreignCreature->id)->count());
    }

    private function stableArenaSettings(): ArenaSetting
    {
        return ArenaSetting::factory()->create([
            'win_xp_per_level' => 5,
            'draw_xp_per_level' => 5,
            'loss_xp_per_level' => 5,
            'win_development_points_per_level' => 5,
            'draw_development_points_per_level' => 5,
            'loss_development_points_per_level' => 5,
            'win_tokens_per_level' => 20,
            'draw_tokens_per_level' => 20,
            'loss_tokens_per_level' => 20,
            'xp_to_next_level_base' => 5,
            'xp_to_next_level_exponent' => 1,
            'level_up_development_points' => 1,
            'weak_opponent_power_ratio' => 0,
            'weak_opponent_reward_multiplier' => 1,
            'same_opponent_daily_limit' => 0,
            'daily_full_reward_limit' => 0,
            'matchmaking_level_difference' => 99,
        ]);
    }

    /**
     * @return array{0: CreatureType, 1: CreatureSpecies}
     */
    private function starterCatalog(): array
    {
        $type = CreatureType::factory()->create([
            'name' => 'MVP Animals',
            'is_active' => true,
        ]);
        $species = CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'name' => 'MVP Wolf',
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

        return [$type, $species];
    }

    private function opponentCreature(User $user, CreatureType $type, CreatureSpecies $species): Creature
    {
        $maxHp = Creature::maxHpForEndurance(5);

        return Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $type->id,
            'creature_species_id' => $species->id,
            'level' => 1,
            'strength' => 5,
            'perception' => 5,
            'endurance' => 5,
            'charisma' => 5,
            'intelligence' => 5,
            'agility' => 5,
            'luck' => 5,
            'current_hp' => $maxHp,
            'max_hp' => $maxHp,
            'is_available_for_battle' => true,
        ]);
    }
}
