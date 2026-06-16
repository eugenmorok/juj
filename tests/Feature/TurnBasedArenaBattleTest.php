<?php

namespace Tests\Feature;

use App\Models\ArenaChallenge;
use App\Models\Battle;
use App\Models\BattleAction;
use App\Models\BattleParticipant;
use App\Models\BattleRound;
use App\Models\BotProfile;
use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\User;
use App\Services\InteractiveBattleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnBasedArenaBattleTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_tactic_resolves_round_against_bot(): void
    {
        [$type, $species] = $this->catalog();
        $user = User::factory()->create();
        $botProfile = BotProfile::factory()->create(['style' => 'balanced']);
        $creature = $this->creatureFor($user, $type, $species, ['name' => 'Pilot']);
        $botCreature = $this->creatureFor($botProfile->user, $type, $species, ['name' => 'Arena Bot']);

        $this->actingAs($user)
            ->post(route('arena.challenges.store'), [
                'challenger_creature_id' => $creature->id,
                'defender_creature_id' => $botCreature->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $battle = Battle::query()->firstOrFail();

        $this->assertSame(Battle::MODE_INTERACTIVE, $battle->mode);
        $this->assertSame(Battle::STATUS_RUNNING, $battle->status);
        $this->assertSame(1, BattleAction::query()->where('battle_id', $battle->id)->where('is_auto', true)->count());

        $this->actingAs($user)
            ->post(route('arena.battles.actions.store', $battle), [
                'attack_zone' => 'body',
                'defense_zone' => 'head',
            ])
            ->assertRedirect(route('arena.battles.show', $battle, absolute: false))
            ->assertSessionHasNoErrors();

        $battle->refresh();

        $this->assertSame(Battle::STATUS_RUNNING, $battle->status);
        $this->assertSame(2, $battle->current_round);
        $this->assertDatabaseHas('battle_rounds', [
            'battle_id' => $battle->id,
            'round_number' => 1,
            'status' => BattleRound::STATUS_RESOLVED,
        ]);
        $this->assertSame(2, BattleAction::query()->where('battle_round_id', BattleRound::query()->where('battle_id', $battle->id)->where('round_number', 1)->value('id'))->count());
        $this->assertTrue($battle->events()->whereIn('event_type', ['interactive_hit', 'interactive_miss', 'interactive_critical_hit'])->exists());
    }

    public function test_interactive_battle_page_uses_readable_russian_text(): void
    {
        [$type, $species] = $this->catalog();
        $user = User::factory()->create();
        $botProfile = BotProfile::factory()->create(['style' => 'balanced']);
        $creature = $this->creatureFor($user, $type, $species, ['name' => 'Reader']);
        $botCreature = $this->creatureFor($botProfile->user, $type, $species, ['name' => 'Readable Bot']);
        $battle = app(InteractiveBattleService::class)->start($creature, $botCreature, $user);

        $eventText = $battle->events()->where('event_type', 'interactive_battle_started')->value('text_log');

        $this->assertStringContainsString('Бой начинается', $eventText);
        $this->assertStringNotContainsString('Рџ', $eventText);

        $this->actingAs($user)
            ->get(route('arena.battles.show', $battle))
            ->assertOk()
            ->assertSee('Пошаговый бой')
            ->assertSee('Выбор тактики')
            ->assertSee('Подтвердить шаг')
            ->assertSee('Голова')
            ->assertSee('Тело')
            ->assertSee('Лог боя')
            ->assertDontSee('Рџ', false)
            ->assertDontSee('Р ', false)
            ->assertDontSee('РЎ', false);
    }

    public function test_real_players_submit_actions_before_round_is_resolved(): void
    {
        [$type, $species] = $this->catalog();
        $challenger = User::factory()->create();
        $defender = User::factory()->create();
        $challengerCreature = $this->creatureFor($challenger, $type, $species, ['name' => 'Caller']);
        $defenderCreature = $this->creatureFor($defender, $type, $species, ['name' => 'Receiver']);

        $this->actingAs($challenger)
            ->post(route('arena.challenges.store'), [
                'challenger_creature_id' => $challengerCreature->id,
                'defender_creature_id' => $defenderCreature->id,
            ])
            ->assertRedirect();

        $challenge = ArenaChallenge::query()->firstOrFail();

        $this->actingAs($defender)
            ->post(route('arena.challenges.accept', $challenge))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $battle = $challenge->refresh()->battle;
        $round = BattleRound::query()->where('battle_id', $battle->id)->where('round_number', 1)->firstOrFail();

        $this->actingAs($challenger)
            ->post(route('arena.battles.actions.store', $battle), [
                'attack_zone' => 'arms',
                'defense_zone' => 'body',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(BattleRound::STATUS_COLLECTING, $round->refresh()->status);
        $this->assertSame(1, BattleAction::query()->where('battle_round_id', $round->id)->count());

        $this->actingAs($defender)
            ->post(route('arena.battles.actions.store', $battle), [
                'attack_zone' => 'legs',
                'defense_zone' => 'arms',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(BattleRound::STATUS_RESOLVED, $round->refresh()->status);
        $this->assertSame(2, $battle->refresh()->current_round);
    }

    public function test_missing_player_actions_are_auto_submitted_after_deadline(): void
    {
        [$type, $species] = $this->catalog();
        $challenger = User::factory()->create();
        $defender = User::factory()->create();
        $challengerCreature = $this->creatureFor($challenger, $type, $species);
        $defenderCreature = $this->creatureFor($defender, $type, $species);

        $this->actingAs($challenger)
            ->post(route('arena.challenges.store'), [
                'challenger_creature_id' => $challengerCreature->id,
                'defender_creature_id' => $defenderCreature->id,
            ]);

        $challenge = ArenaChallenge::query()->firstOrFail();

        $this->actingAs($defender)
            ->post(route('arena.challenges.accept', $challenge));

        $battle = $challenge->refresh()->battle;
        $round = BattleRound::query()->where('battle_id', $battle->id)->where('round_number', 1)->firstOrFail();

        $this->travel(7)->seconds();

        app(InteractiveBattleService::class)->processBattle($battle);

        $this->assertSame(BattleRound::STATUS_RESOLVED, $round->refresh()->status);
        $this->assertSame(2, BattleAction::query()->where('battle_round_id', $round->id)->where('is_auto', true)->count());
        $this->assertSame(2, $battle->refresh()->current_round);
    }

    public function test_consumable_can_be_used_as_part_of_battle_action(): void
    {
        [$type, $species] = $this->catalog();
        $user = User::factory()->create();
        $botProfile = BotProfile::factory()->create(['style' => 'defensive']);
        $creature = $this->creatureFor($user, $type, $species, ['name' => 'Alchemist']);
        $botCreature = $this->creatureFor($botProfile->user, $type, $species, ['name' => 'Training Bot']);
        $battle = app(InteractiveBattleService::class)->start($botCreature, $creature, $botProfile->user);

        $participant = BattleParticipant::query()
            ->where('battle_id', $battle->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $participant->forceFill(['hp_after' => $participant->hp_after - 30])->save();

        $item = Item::factory()->potion()->create([
            'name' => 'Battle Tonic',
            'bonuses' => ['heal' => 25, 'strength' => 3],
            'uses_count' => 1,
        ]);
        $itemInstance = ItemInstance::factory()->create([
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
            'durability' => 1,
            'state' => 'stored',
        ]);
        $inventoryItem = Inventory::forUser($user)->addItemInstance($itemInstance);

        $this->actingAs($user)
            ->post(route('arena.battles.actions.store', $battle), [
                'attack_zone' => 'head',
                'defense_zone' => 'body',
                'inventory_item_id' => $inventoryItem->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('battle_events', [
            'battle_id' => $battle->id,
            'event_type' => 'interactive_item_used',
        ]);
        $this->assertDatabaseMissing('inventory_items', ['id' => $inventoryItem->id]);
        $this->assertSame('used', $itemInstance->refresh()->state);
    }

    /**
     * @return array{0: CreatureType, 1: CreatureSpecies}
     */
    private function catalog(): array
    {
        $type = CreatureType::factory()->create(['code' => 'turn-arena-test-type']);
        $species = CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'code' => 'turn-arena-test-species',
            'base_strength' => 5,
            'base_perception' => 5,
            'base_endurance' => 5,
            'base_charisma' => 5,
            'base_intelligence' => 5,
            'base_agility' => 5,
            'base_luck' => 5,
        ]);

        return [$type, $species];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function creatureFor(User $user, CreatureType $type, CreatureSpecies $species, array $attributes = []): Creature
    {
        $endurance = $attributes['endurance'] ?? 20;
        $maxHp = Creature::maxHpForEndurance($endurance);

        return Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $type->id,
            'creature_species_id' => $species->id,
            'level' => 1,
            'strength' => 9,
            'perception' => 11,
            'endurance' => $endurance,
            'charisma' => 8,
            'intelligence' => 9,
            'agility' => 10,
            'luck' => 8,
            'current_hp' => $maxHp,
            'max_hp' => $maxHp,
            'is_available_for_battle' => true,
            ...$attributes,
        ]);
    }
}
