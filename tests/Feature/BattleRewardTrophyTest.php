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
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\User;
use App\Services\BattleRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleRewardTrophyTest extends TestCase
{
    use RefreshDatabase;

    public function test_winner_can_receive_loser_item_as_circle_trophy(): void
    {
        ArenaSetting::factory()->create([
            'daily_full_reward_limit' => 0,
            'same_opponent_daily_limit' => 0,
            'weak_opponent_power_ratio' => 0,
        ]);

        $winnerUser = User::factory()->create(['tokens' => 0]);
        $loserUser = User::factory()->create(['tokens' => 0]);
        $winnerCreature = $this->creatureFor($winnerUser, ['name' => 'Winner']);
        $loserCreature = $this->creatureFor($loserUser, ['name' => 'Loser']);
        $slot = EquipmentSlot::factory()->create([
            'code' => 'primary-weapon',
            'name' => 'Основное оружие / клыки / жало',
        ]);
        $item = Item::factory()->create([
            'name' => 'Трофейное жало',
            'slot_key' => $slot->code,
            'slots_required' => [$slot->code],
            'bonuses' => ['damage' => 5],
        ]);
        $itemInstance = $this->equipItem($loserCreature, $slot, $item);
        [$battle, $winnerParticipant, $loserParticipant] = $this->finishedBattle($winnerCreature, $loserCreature);

        $battle->forceFill([
            'seed' => $this->seedThatDropsTrophy($battle, $winnerParticipant, $loserParticipant),
        ])->save();

        app(BattleRewardService::class)->apply($battle);

        $winnerInventory = Inventory::forCreature($winnerCreature->refresh());

        $this->assertDatabaseHas('inventory_items', [
            'inventory_id' => $winnerInventory->id,
            'item_instance_id' => $itemInstance->id,
        ]);
        $this->assertDatabaseMissing('creature_equipment', [
            'creature_id' => $loserCreature->id,
            'item_instance_id' => $itemInstance->id,
        ]);
        $this->assertSame($winnerCreature->id, $itemInstance->refresh()->bound_creature_id);
        $this->assertSame($winnerUser->id, $itemInstance->owner_user_id);
        $this->assertSame('stored', $itemInstance->state);

        $event = $battle->events()->where('event_type', 'rewards_applied')->firstOrFail();

        $this->assertTrue($event->payload['trophy']['awarded']);
        $this->assertSame('Трофейное жало', $event->payload['trophy']['item_name']);
        $this->assertSame([$slot->code], $event->payload['trophy']['source_slot_keys']);
        $this->assertStringContainsString('Трофей круга', $event->text_log);
    }

    public function test_trophy_does_not_remove_loser_item_when_winner_inventory_is_full(): void
    {
        ArenaSetting::factory()->create([
            'daily_full_reward_limit' => 0,
            'same_opponent_daily_limit' => 0,
            'weak_opponent_power_ratio' => 0,
        ]);

        $winnerUser = User::factory()->create(['tokens' => 0]);
        $loserUser = User::factory()->create(['tokens' => 0]);
        $winnerCreature = $this->creatureFor($winnerUser, ['name' => 'Packed Winner']);
        $loserCreature = $this->creatureFor($loserUser, ['name' => 'Still Equipped']);
        $winnerInventory = Inventory::forCreature($winnerCreature);

        for ($slotNumber = 1; $slotNumber <= $winnerInventory->capacity(); $slotNumber++) {
            $winnerInventory->addItemInstance(ItemInstance::factory()->create([
                'owner_user_id' => $winnerUser->id,
            ]));
        }

        $slot = EquipmentSlot::factory()->create([
            'code' => 'defense',
            'name' => 'Защита / панцирь / щит',
        ]);
        $item = Item::factory()->create([
            'name' => 'Неперенесённый щит',
            'slot_key' => $slot->code,
            'slots_required' => [$slot->code],
            'bonuses' => ['defense' => 5],
        ]);
        $itemInstance = $this->equipItem($loserCreature, $slot, $item);
        [$battle, $winnerParticipant, $loserParticipant] = $this->finishedBattle($winnerCreature, $loserCreature);

        $battle->forceFill([
            'seed' => $this->seedThatDropsTrophy($battle, $winnerParticipant, $loserParticipant),
        ])->save();

        app(BattleRewardService::class)->apply($battle);

        $this->assertDatabaseHas('creature_equipment', [
            'creature_id' => $loserCreature->id,
            'item_instance_id' => $itemInstance->id,
            'slot_key' => $slot->code,
        ]);
        $this->assertSame($loserCreature->id, $itemInstance->refresh()->bound_creature_id);
        $this->assertSame('equipped', $itemInstance->state);

        $event = $battle->events()->where('event_type', 'rewards_applied')->firstOrFail();

        $this->assertFalse($event->payload['trophy']['awarded']);
        $this->assertSame('winner_inventory_full', $event->payload['trophy']['reason']);
        $this->assertStringContainsString('инвентарь победителя заполнен', $event->text_log);
    }

    /**
     * @return array{0: Battle, 1: BattleParticipant, 2: BattleParticipant}
     */
    private function finishedBattle(Creature $winner, Creature $loser): array
    {
        $battle = Battle::query()->create([
            'initiator_user_id' => $winner->user_id,
            'winner_creature_id' => $winner->id,
            'battle_type' => Battle::TYPE_RANKED,
            'status' => Battle::STATUS_FINISHED,
            'is_draw' => false,
            'seed' => 1,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $winnerParticipant = $battle->participants()->create([
            'user_id' => $winner->user_id,
            'creature_id' => $winner->id,
            'side' => 'left',
            'result' => BattleParticipant::RESULT_WIN,
            'power_score_before' => 100,
            'hp_before' => 100,
            'hp_after' => 80,
            'level_before' => 1,
        ]);
        $loserParticipant = $battle->participants()->create([
            'user_id' => $loser->user_id,
            'creature_id' => $loser->id,
            'side' => 'right',
            'result' => BattleParticipant::RESULT_LOSS,
            'power_score_before' => 100,
            'hp_before' => 100,
            'hp_after' => 0,
            'level_before' => 1,
        ]);

        return [$battle, $winnerParticipant, $loserParticipant];
    }

    private function seedThatDropsTrophy(Battle $battle, BattleParticipant $winner, BattleParticipant $loser): int
    {
        for ($seed = 1; $seed <= 10000; $seed++) {
            $battle->seed = $seed;

            if ($this->trophyRoll($battle, $winner, $loser) <= 5) {
                return $seed;
            }
        }

        $this->fail('No deterministic trophy seed found.');
    }

    private function trophyRoll(Battle $battle, BattleParticipant $winner, BattleParticipant $loser): int
    {
        $hash = sprintf('%u', crc32(implode('|', [
            $battle->seed,
            $battle->id,
            $winner->id,
            $winner->creature_id,
            $loser->id,
            $loser->creature_id,
            'trophy-roll',
        ])));

        return (((int) $hash) % 100) + 1;
    }

    private function equipItem(Creature $creature, EquipmentSlot $slot, Item $item): ItemInstance
    {
        $itemInstance = ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $creature->user_id,
            'bound_creature_id' => $creature->id,
            'state' => 'equipped',
        ]);

        CreatureEquipment::query()->create([
            'creature_id' => $creature->id,
            'item_instance_id' => $itemInstance->id,
            'slot_key' => $slot->code,
        ]);

        return $itemInstance;
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
