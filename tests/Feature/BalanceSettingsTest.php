<?php

namespace Tests\Feature;

use App\Models\ArenaSetting;
use App\Models\BalanceChangeLog;
use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\User;
use App\Services\BattleRewardService;
use App\Services\PowerScoreService;
use App\Services\ShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_balance_settings_and_log_pages(): void
    {
        $admin = User::factory()->admin()->create();
        ArenaSetting::factory()->create(['name' => 'MVP balance']);

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.arena-settings.index'))
            ->assertOk()
            ->assertSee('MVP balance');

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.balance-change-logs.index'))
            ->assertOk();
    }

    public function test_balance_setting_changes_are_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $setting = ArenaSetting::factory()->create([
            'win_tokens_per_level' => 50,
            'matchmaking_level_difference' => 2,
        ]);

        $this->actingAs($admin);

        $setting->update([
            'win_tokens_per_level' => 70,
            'matchmaking_level_difference' => 4,
        ]);

        $log = BalanceChangeLog::query()->firstOrFail();

        $this->assertSame($setting->id, $log->arena_setting_id);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertContains('win_tokens_per_level', $log->changed_fields);
        $this->assertContains('matchmaking_level_difference', $log->changed_fields);
        $this->assertSame(50, $log->before_values['win_tokens_per_level']);
        $this->assertSame(70, $log->after_values['win_tokens_per_level']);
    }

    public function test_battle_rewards_use_active_balance_settings(): void
    {
        ArenaSetting::factory()->create([
            'win_xp_per_level' => 11,
            'win_development_points_per_level' => 5,
            'win_tokens_per_level' => 7,
            'weak_opponent_power_ratio' => 0,
            'daily_full_reward_limit' => 0,
            'same_opponent_daily_limit' => 0,
        ]);

        $user = User::factory()->create(['tokens' => 0]);
        $opponentUser = User::factory()->create(['tokens' => 0]);
        $creature = $this->creatureFor($user);
        $opponent = $this->creatureFor($opponentUser, ['level' => 3]);
        $battle = Battle::query()->create([
            'initiator_user_id' => $user->id,
            'winner_creature_id' => $creature->id,
            'battle_type' => Battle::TYPE_RANKED,
            'status' => Battle::STATUS_FINISHED,
            'is_draw' => false,
            'seed' => 123,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $battle->participants()->create([
            'user_id' => $user->id,
            'creature_id' => $creature->id,
            'side' => 'left',
            'result' => BattleParticipant::RESULT_WIN,
            'power_score_before' => 100,
            'hp_before' => 100,
            'hp_after' => 80,
            'level_before' => 1,
        ]);
        $battle->participants()->create([
            'user_id' => $opponentUser->id,
            'creature_id' => $opponent->id,
            'side' => 'right',
            'result' => BattleParticipant::RESULT_LOSS,
            'power_score_before' => 100,
            'hp_before' => 100,
            'hp_after' => 0,
            'level_before' => 3,
        ]);

        app(BattleRewardService::class)->apply($battle);

        $participant = BattleParticipant::query()
            ->where('creature_id', $creature->id)
            ->firstOrFail();

        $this->assertSame(33, $participant->reward_xp);
        $this->assertSame(15, $participant->reward_development_points);
        $this->assertSame(21, $participant->reward_tokens);
        $this->assertSame(21, $user->refresh()->tokens);
    }

    public function test_shop_inventory_expansion_uses_balance_settings(): void
    {
        ArenaSetting::factory()->create([
            'inventory_slot_base_cost' => 30,
            'inventory_slot_step_cost' => 7,
            'max_purchased_inventory_slots' => 1,
        ]);

        $user = User::factory()->create(['tokens' => 100, 'inventory_slots' => 5]);

        $this->assertSame(30, ShopService::inventorySlotCost($user));

        $this->actingAs($user)
            ->from(route('shop'))
            ->post(route('shop.inventory-slots.buy'))
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame(70, $user->refresh()->tokens);
        $this->assertSame(6, $user->inventory_slots);

        $this->actingAs($user)
            ->from(route('shop'))
            ->post(route('shop.inventory-slots.buy'))
            ->assertRedirect(route('shop', absolute: false))
            ->assertSessionHasErrors('inventory');
    }

    public function test_daily_battle_limit_blocks_new_battle(): void
    {
        ArenaSetting::factory()->create(['daily_battle_limit' => 1]);

        $user = User::factory()->create();
        $opponentUser = User::factory()->create();
        $creature = $this->creatureFor($user);
        $this->creatureFor($opponentUser);
        $battle = Battle::query()->create([
            'initiator_user_id' => $user->id,
            'battle_type' => Battle::TYPE_RANKED,
            'status' => Battle::STATUS_FINISHED,
            'is_draw' => false,
            'seed' => 456,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        $battle->participants()->create([
            'user_id' => $user->id,
            'creature_id' => $creature->id,
            'side' => 'left',
            'result' => BattleParticipant::RESULT_WIN,
        ]);

        $this->actingAs($user)
            ->from(route('arena'))
            ->post(route('arena.battles.start'), ['creature_id' => $creature->id])
            ->assertRedirect(route('arena', absolute: false))
            ->assertSessionHasErrors('arena');
    }

    public function test_power_score_uses_balance_weights(): void
    {
        ArenaSetting::factory()->create([
            'power_score_level_weight' => 20,
            'power_score_skill_weight' => 0,
            'power_score_equipment_weight' => 0,
        ]);

        $creature = $this->creatureFor(User::factory()->create(), [
            'level' => 2,
            'strength' => 1,
            'perception' => 1,
            'endurance' => 1,
            'charisma' => 1,
            'intelligence' => 1,
            'agility' => 1,
            'luck' => 1,
        ]);

        $this->assertSame(47, app(PowerScoreService::class)->calculate($creature));
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
        $endurance = $attributes['endurance'] ?? 10;
        $maxHp = Creature::maxHpForEndurance($endurance);

        return Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $type->id,
            'creature_species_id' => $species->id,
            'strength' => 12,
            'perception' => 10,
            'endurance' => $endurance,
            'charisma' => 5,
            'intelligence' => 8,
            'agility' => 10,
            'luck' => 8,
            'current_hp' => $maxHp,
            'max_hp' => $maxHp,
            ...$attributes,
        ]);
    }
}
