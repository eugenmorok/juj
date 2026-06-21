<?php

namespace Tests\Feature;

use App\Models\BattleParticipant;
use App\Models\Creature;
use App\Models\CreatureEquipment;
use App\Models\EquipmentSlot;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\User;
use App\Services\PlayerProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_level_up_grants_doctrine_points(): void
    {
        $user = User::factory()->create([
            'level' => 1,
            'xp' => 140,
            'doctrine_points' => 0,
        ]);

        $result = app(PlayerProgressService::class)->applyBattleProgress(
            user: $user,
            result: BattleParticipant::RESULT_WIN,
            opponentLevel: 1,
            rewardMultiplier: 1,
            battleSeed: 123,
            participantId: 77,
        );

        $user->refresh();

        $this->assertSame(1, $result['doctrine_points']);
        $this->assertSame(2, $user->level);
        $this->assertSame(1, $user->doctrine_points);
    }

    public function test_player_level_milestone_grants_perk_points(): void
    {
        $user = User::factory()->create([
            'level' => 2,
            'xp' => 450,
            'doctrine_points' => 0,
            'perk_points' => 0,
        ]);

        $result = app(PlayerProgressService::class)->applyBattleProgress(
            user: $user,
            result: BattleParticipant::RESULT_WIN,
            opponentLevel: 1,
            rewardMultiplier: 1,
            battleSeed: 456,
            participantId: 88,
        );

        $user->refresh();

        $this->assertSame(1, $result['doctrine_points']);
        $this->assertSame(1, $result['perk_points']);
        $this->assertSame(3, $user->level);
        $this->assertSame(1, $user->doctrine_points);
        $this->assertSame(1, $user->perk_points);
    }

    public function test_player_can_spend_doctrine_points_from_profile(): void
    {
        $user = User::factory()->create([
            'doctrine_points' => 2,
        ]);

        $this->actingAs($user)
            ->from(route('profile'))
            ->post(route('profile.doctrine.increase', 'tactic'))
            ->assertRedirect(route('profile', absolute: false))
            ->assertSessionHasNoErrors();

        $this->actingAs($user->refresh())
            ->from(route('profile'))
            ->post(route('profile.doctrine.increase', 'tactic'))
            ->assertRedirect(route('profile', absolute: false))
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame(0, $user->doctrine_points);
        $this->assertSame(2, $user->doctrine_tactic);
        $this->assertSame(1, $user->battleSupportBonus()['perception']);
    }

    public function test_player_can_buy_available_perk_from_profile(): void
    {
        $user = User::factory()->create([
            'level' => 3,
            'perk_points' => 1,
            'doctrine_tactic' => 2,
            'player_perks' => [],
        ]);

        $this->actingAs($user)
            ->from(route('profile'))
            ->post(route('profile.perks.buy', 'battle-drill'))
            ->assertRedirect(route('profile', absolute: false))
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame(0, $user->perk_points);
        $this->assertTrue($user->hasPlayerPerk('battle-drill'));
        $this->assertSame(2, $user->battleSupportBonus()['perception']);
    }

    public function test_doctrine_affects_economy_inventory_and_equipment_combat_bonuses(): void
    {
        $user = User::factory()->create([
            'level' => 1,
            'doctrine_engineering' => 10,
            'doctrine_breeding' => 6,
            'doctrine_trade' => 5,
        ]);
        $creature = Creature::factory()->create(['user_id' => $user->id]);
        $slot = EquipmentSlot::factory()->create(['code' => 'doctrine-kit']);
        $item = Item::factory()->create([
            'slot_key' => $slot->code,
            'slots_required' => [$slot->code],
            'bonuses' => ['damage' => 10, 'defense' => 5],
        ]);
        $itemInstance = ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
            'bound_creature_id' => $creature->id,
            'state' => 'equipped',
        ]);
        CreatureEquipment::query()->create([
            'creature_id' => $creature->id,
            'item_instance_id' => $itemInstance->id,
            'slot_key' => $slot->code,
        ]);

        $stats = $creature->refresh()->effectiveCombatStats();

        $this->assertSame(12, $user->inventoryCapacity());
        $this->assertSame(5, $user->shopDiscountPercent());
        $this->assertSame(18, $user->creationPointRewardBonusPercent());
        $this->assertSame(15, $user->tokenRewardBonusPercent());
        $this->assertSame(12, $stats['damage']['equipment']);
        $this->assertSame(6, $stats['defense']['equipment']);
    }

    public function test_player_perks_apply_their_passive_effects(): void
    {
        $user = User::factory()->create([
            'doctrine_engineering' => 10,
            'doctrine_breeding' => 6,
            'doctrine_trade' => 5,
            'player_perks' => ['equipment-tuning', 'breeder', 'trophy-hunter'],
        ]);
        $creature = Creature::factory()->create(['user_id' => $user->id]);
        $slot = EquipmentSlot::factory()->create(['code' => 'perk-kit']);
        $item = Item::factory()->create([
            'slot_key' => $slot->code,
            'slots_required' => [$slot->code],
            'bonuses' => ['damage' => 10],
        ]);
        $itemInstance = ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
            'bound_creature_id' => $creature->id,
            'state' => 'equipped',
        ]);
        CreatureEquipment::query()->create([
            'creature_id' => $creature->id,
            'item_instance_id' => $itemInstance->id,
            'slot_key' => $slot->code,
        ]);

        $stats = $creature->refresh()->effectiveCombatStats();

        $this->assertSame(25, $user->equipmentCombatBonusPercent());
        $this->assertSame(28, $user->creationPointRewardBonusPercent());
        $this->assertSame(23, $user->tokenRewardBonusPercent());
        $this->assertSame(13, $stats['damage']['equipment']);
    }

    public function test_player_can_convert_xp_to_creature_creation_points(): void
    {
        $user = User::factory()->create([
            'xp' => 250,
            'creature_creation_points' => 20,
        ]);

        $this->actingAs($user)
            ->from(route('profile'))
            ->post(route('profile.creation-points.convert'), [
                'points' => 10,
            ])
            ->assertRedirect(route('profile', absolute: false))
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame(240, $user->xp);
        $this->assertSame(30, $user->creature_creation_points);
    }

    public function test_player_cannot_convert_more_xp_than_available(): void
    {
        $user = User::factory()->create([
            'xp' => 5,
            'creature_creation_points' => 0,
        ]);

        $this->actingAs($user)
            ->from(route('profile'))
            ->post(route('profile.creation-points.convert'), [
                'points' => 10,
            ])
            ->assertRedirect(route('profile', absolute: false))
            ->assertSessionHasErrors('points');

        $user->refresh();

        $this->assertSame(5, $user->xp);
        $this->assertSame(0, $user->creature_creation_points);
    }
}
