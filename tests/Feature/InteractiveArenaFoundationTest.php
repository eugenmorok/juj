<?php

namespace Tests\Feature;

use App\Models\ArenaChallenge;
use App\Models\ArenaMatchmakingSession;
use App\Models\ArenaSetting;
use App\Models\Battle;
use App\Models\BotProfile;
use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteractiveArenaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_start_matchmaking_and_system_generates_bot_candidates(): void
    {
        ArenaSetting::factory()->create([
            'matchmaking_level_difference' => 2,
            'matchmaking_power_score_difference' => 60,
        ]);
        [$type, $species] = $this->catalog();
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, $type, $species, ['name' => 'Seeker']);

        $response = $this->actingAs($user)
            ->from(route('arena'))
            ->post(route('arena.search.store'), [
                'creature_id' => $creature->id,
            ]);

        $session = ArenaMatchmakingSession::query()->firstOrFail();

        $response
            ->assertRedirect(route('arena.search.show', $session, absolute: false))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('arena.search.show', $session))
            ->assertOk()
            ->assertSee('Подбор соперников')
            ->assertSee('Бросить вызов')
            ->assertSee('Бот');

        $this->assertGreaterThanOrEqual(5, User::query()->where('is_bot', true)->count());
    }

    public function test_matchmaking_page_shows_suitable_real_players(): void
    {
        ArenaSetting::factory()->create([
            'matchmaking_level_difference' => 2,
            'matchmaking_power_score_difference' => 80,
        ]);
        [$type, $species] = $this->catalog();
        $user = User::factory()->create();
        $opponentUser = User::factory()->create(['name' => 'Human Rival']);
        $creature = $this->creatureFor($user, $type, $species, ['name' => 'Arena Scout']);
        $this->creatureFor($opponentUser, $type, $species, ['name' => 'Real Opponent']);
        $session = ArenaMatchmakingSession::query()->create([
            'user_id' => $user->id,
            'creature_id' => $creature->id,
            'power_score' => 143,
            'status' => ArenaMatchmakingSession::STATUS_ACTIVE,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->actingAs($user)
            ->get(route('arena.search.show', $session))
            ->assertOk()
            ->assertSee('Real Opponent')
            ->assertSee('Human Rival')
            ->assertSee('Игрок');
    }

    public function test_player_can_challenge_bot_and_battle_starts_immediately(): void
    {
        [$type, $species] = $this->catalog();
        $user = User::factory()->create();
        $botProfile = BotProfile::factory()->create(['display_name' => 'Instant Bot']);
        $creature = $this->creatureFor($user, $type, $species, ['name' => 'Bot Challenger']);
        $botCreature = $this->creatureFor($botProfile->user, $type, $species, ['name' => 'Instant Bot Creature']);

        $this->actingAs($user)
            ->from(route('arena'))
            ->post(route('arena.challenges.store'), [
                'challenger_creature_id' => $creature->id,
                'defender_creature_id' => $botCreature->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $challenge = ArenaChallenge::query()->firstOrFail();
        $battle = Battle::query()->firstOrFail();

        $this->assertSame(ArenaChallenge::STATUS_BATTLE_STARTED, $challenge->status);
        $this->assertSame($battle->id, $challenge->battle_id);
        $this->assertSame('finished', $battle->status);
    }

    public function test_real_player_challenge_waits_for_acceptance_and_then_starts_battle(): void
    {
        [$type, $species] = $this->catalog();
        $challenger = User::factory()->create();
        $defender = User::factory()->create();
        $challengerCreature = $this->creatureFor($challenger, $type, $species, ['name' => 'Caller']);
        $defenderCreature = $this->creatureFor($defender, $type, $species, ['name' => 'Receiver']);

        $this->actingAs($challenger)
            ->from(route('arena'))
            ->post(route('arena.challenges.store'), [
                'challenger_creature_id' => $challengerCreature->id,
                'defender_creature_id' => $defenderCreature->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $challenge = ArenaChallenge::query()->firstOrFail();

        $this->assertSame(ArenaChallenge::STATUS_PENDING, $challenge->status);
        $this->assertNull($challenge->battle_id);

        $this->actingAs($defender)
            ->get(route('arena'))
            ->assertOk()
            ->assertSee('Входящие вызовы')
            ->assertSee('Caller');

        $this->actingAs($defender)
            ->post(route('arena.challenges.accept', $challenge))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $challenge->refresh();

        $this->assertSame(ArenaChallenge::STATUS_BATTLE_STARTED, $challenge->status);
        $this->assertNotNull($challenge->battle_id);
        $this->assertSame('finished', $challenge->battle->status);
    }

    public function test_defender_can_decline_real_player_challenge(): void
    {
        [$type, $species] = $this->catalog();
        $challenger = User::factory()->create();
        $defender = User::factory()->create();
        $challengerCreature = $this->creatureFor($challenger, $type, $species);
        $defenderCreature = $this->creatureFor($defender, $type, $species);
        $challenge = ArenaChallenge::query()->create([
            'challenger_user_id' => $challenger->id,
            'challenger_creature_id' => $challengerCreature->id,
            'defender_user_id' => $defender->id,
            'defender_creature_id' => $defenderCreature->id,
            'defender_is_bot' => false,
            'status' => ArenaChallenge::STATUS_PENDING,
            'expires_at' => now()->addSeconds(ArenaChallenge::ACCEPTANCE_SECONDS),
        ]);

        $this->actingAs($defender)
            ->from(route('arena.challenges.show', $challenge))
            ->post(route('arena.challenges.decline', $challenge))
            ->assertRedirect(route('arena.challenges.show', $challenge, absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame(ArenaChallenge::STATUS_DECLINED, $challenge->refresh()->status);
        $this->assertNull($challenge->battle_id);
    }

    public function test_expired_challenge_cannot_be_accepted(): void
    {
        [$type, $species] = $this->catalog();
        $challenger = User::factory()->create();
        $defender = User::factory()->create();
        $challengerCreature = $this->creatureFor($challenger, $type, $species);
        $defenderCreature = $this->creatureFor($defender, $type, $species);
        $challenge = ArenaChallenge::query()->create([
            'challenger_user_id' => $challenger->id,
            'challenger_creature_id' => $challengerCreature->id,
            'defender_user_id' => $defender->id,
            'defender_creature_id' => $defenderCreature->id,
            'defender_is_bot' => false,
            'status' => ArenaChallenge::STATUS_PENDING,
            'expires_at' => now()->subSecond(),
        ]);

        $this->actingAs($defender)
            ->from(route('arena.challenges.show', $challenge))
            ->post(route('arena.challenges.accept', $challenge))
            ->assertRedirect(route('arena.challenges.show', $challenge, absolute: false))
            ->assertSessionHasErrors('challenge');

        $this->assertSame(ArenaChallenge::STATUS_EXPIRED, $challenge->refresh()->status);
        $this->assertSame(0, Battle::query()->count());
    }

    /**
     * @return array{0: CreatureType, 1: CreatureSpecies}
     */
    private function catalog(): array
    {
        $type = CreatureType::factory()->create(['code' => 'arena-test-type']);
        $species = CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'code' => 'arena-test-species',
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
        $endurance = $attributes['endurance'] ?? 19;
        $maxHp = Creature::maxHpForEndurance($endurance);

        return Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $type->id,
            'creature_species_id' => $species->id,
            'level' => 1,
            'strength' => 19,
            'perception' => 19,
            'endurance' => $endurance,
            'charisma' => 19,
            'intelligence' => 19,
            'agility' => 19,
            'luck' => 19,
            'current_hp' => $maxHp,
            'max_hp' => $maxHp,
            'is_available_for_battle' => true,
            ...$attributes,
        ]);
    }
}
