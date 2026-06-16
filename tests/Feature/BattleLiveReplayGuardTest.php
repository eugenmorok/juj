<?php

namespace Tests\Feature;

use App\Models\ArenaChallenge;
use App\Models\Battle;
use App\Models\BattleRound;
use App\Models\BotProfile;
use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\User;
use App\Services\InteractiveBattleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleLiveReplayGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_battle_state_endpoint_returns_live_interactive_state(): void
    {
        [$type, $species] = $this->catalog();
        $user = User::factory()->create();
        $botProfile = BotProfile::factory()->create();
        $creature = $this->creatureFor($user, $type, $species, ['name' => 'Live Scout']);
        $botCreature = $this->creatureFor($botProfile->user, $type, $species, ['name' => 'Live Bot']);
        $battle = app(InteractiveBattleService::class)->start($creature, $botCreature, $user);

        $this->actingAs($user)
            ->getJson(route('arena.battles.state', $battle))
            ->assertOk()
            ->assertJsonPath('battle_id', $battle->id)
            ->assertJsonPath('mode', Battle::MODE_INTERACTIVE)
            ->assertJsonPath('status', Battle::STATUS_RUNNING)
            ->assertJsonPath('current_round', 1)
            ->assertJsonPath('active_round.round_number', 1)
            ->assertJsonPath('active_round.actions_count', 1);
    }

    public function test_battle_state_endpoint_does_not_advance_expired_rounds(): void
    {
        [$type, $species] = $this->catalog();
        $challenger = User::factory()->create();
        $defender = User::factory()->create();
        $challengerCreature = $this->creatureFor($challenger, $type, $species);
        $defenderCreature = $this->creatureFor($defender, $type, $species);
        $battle = app(InteractiveBattleService::class)->start($challengerCreature, $defenderCreature, $challenger);
        $round = BattleRound::query()->where('battle_id', $battle->id)->where('round_number', 1)->firstOrFail();

        $this->travel(7)->seconds();

        $this->actingAs($challenger)
            ->getJson(route('arena.battles.state', $battle))
            ->assertOk()
            ->assertJsonPath('current_round', 1)
            ->assertJsonPath('active_round.round_number', 1);

        $this->assertSame(BattleRound::STATUS_COLLECTING, $round->refresh()->status);

        app(InteractiveBattleService::class)->processBattle($battle);

        $this->assertSame(BattleRound::STATUS_RESOLVED, $round->refresh()->status);
        $this->assertSame(2, $battle->refresh()->current_round);
    }

    public function test_unavailable_broadcaster_does_not_break_interactive_battle_creation(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 9,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);

        [$type, $species] = $this->catalog();
        $user = User::factory()->create();
        $botProfile = BotProfile::factory()->create();
        $creature = $this->creatureFor($user, $type, $species, ['name' => 'Broadcast Guard']);
        $botCreature = $this->creatureFor($botProfile->user, $type, $species, ['name' => 'Offline Reverb Bot']);

        $battle = app(InteractiveBattleService::class)->start($creature, $botCreature, $user);

        $this->assertSame(Battle::MODE_INTERACTIVE, $battle->mode);
        $this->assertSame(Battle::STATUS_RUNNING, $battle->status);
        $this->assertDatabaseHas('battle_events', [
            'battle_id' => $battle->id,
            'event_type' => 'interactive_battle_started',
        ]);
    }

    public function test_replay_page_shows_round_actions_and_events(): void
    {
        [$type, $species] = $this->catalog();
        $user = User::factory()->create();
        $botProfile = BotProfile::factory()->create();
        $creature = $this->creatureFor($user, $type, $species, ['name' => 'Replay Hero']);
        $botCreature = $this->creatureFor($botProfile->user, $type, $species, ['name' => 'Replay Bot']);
        $battle = app(InteractiveBattleService::class)->start($creature, $botCreature, $user);

        $this->actingAs($user)
            ->post(route('arena.battles.actions.store', $battle), [
                'attack_zone' => 'body',
                'defense_zone' => 'head',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('arena.battles.replay', $battle))
            ->assertOk()
            ->assertSee('Replay')
            ->assertSee('Replay Hero')
            ->assertSee('Replay Bot')
            ->assertSee('Таймлайн раундов')
            ->assertSee('Шаг 1');
    }

    public function test_pending_challenge_blocks_second_challenge_for_same_creature(): void
    {
        [$type, $species] = $this->catalog();
        $challenger = User::factory()->create();
        $defender = User::factory()->create();
        $thirdUser = User::factory()->create();
        $challengerCreature = $this->creatureFor($challenger, $type, $species, ['name' => 'Busy Caller']);
        $defenderCreature = $this->creatureFor($defender, $type, $species);
        $thirdCreature = $this->creatureFor($thirdUser, $type, $species);

        $this->actingAs($challenger)
            ->post(route('arena.challenges.store'), [
                'challenger_creature_id' => $challengerCreature->id,
                'defender_creature_id' => $defenderCreature->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($challenger)
            ->from(route('arena'))
            ->post(route('arena.challenges.store'), [
                'challenger_creature_id' => $challengerCreature->id,
                'defender_creature_id' => $thirdCreature->id,
            ])
            ->assertRedirect(route('arena', absolute: false))
            ->assertSessionHasErrors('arena');

        $this->assertSame(1, ArenaChallenge::query()->pending()->count());
    }

    public function test_challenge_cannot_be_accepted_if_creature_entered_another_running_battle(): void
    {
        [$type, $species] = $this->catalog();
        $challenger = User::factory()->create();
        $defender = User::factory()->create();
        $botProfile = BotProfile::factory()->create();
        $challengerCreature = $this->creatureFor($challenger, $type, $species);
        $defenderCreature = $this->creatureFor($defender, $type, $species);
        $botCreature = $this->creatureFor($botProfile->user, $type, $species);

        $this->actingAs($challenger)
            ->post(route('arena.challenges.store'), [
                'challenger_creature_id' => $challengerCreature->id,
                'defender_creature_id' => $defenderCreature->id,
            ])
            ->assertRedirect();

        $challenge = ArenaChallenge::query()->firstOrFail();

        app(InteractiveBattleService::class)->start($defenderCreature, $botCreature, $defender);

        $this->actingAs($defender)
            ->from(route('arena.challenges.show', $challenge))
            ->post(route('arena.challenges.accept', $challenge))
            ->assertRedirect(route('arena.challenges.show', $challenge, absolute: false))
            ->assertSessionHasErrors('arena');

        $this->assertNull($challenge->refresh()->battle_id);
    }

    /**
     * @return array{0: CreatureType, 1: CreatureSpecies}
     */
    private function catalog(): array
    {
        $type = CreatureType::factory()->create(['code' => 'live-replay-test-type']);
        $species = CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'code' => 'live-replay-test-species',
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
        $endurance = $attributes['endurance'] ?? 16;
        $maxHp = Creature::maxHpForEndurance($endurance);

        return Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $type->id,
            'creature_species_id' => $species->id,
            'level' => 1,
            'strength' => 11,
            'perception' => 12,
            'endurance' => $endurance,
            'charisma' => 8,
            'intelligence' => 9,
            'agility' => 11,
            'luck' => 9,
            'current_hp' => $maxHp,
            'max_hp' => $maxHp,
            'is_available_for_battle' => true,
            ...$attributes,
        ]);
    }
}
