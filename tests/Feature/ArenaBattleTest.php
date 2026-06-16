<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArenaBattleTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_start_battle_and_receive_rewards(): void
    {
        $user = User::factory()->create(['tokens' => 0]);
        $opponentUser = User::factory()->create(['tokens' => 0]);
        $creature = $this->creatureFor($user, [
            'name' => 'Reward Hunter',
            'strength' => 26,
            'perception' => 18,
            'agility' => 18,
            'luck' => 12,
        ]);
        $this->creatureFor($opponentUser, [
            'name' => 'Arena Target',
            'strength' => 5,
            'perception' => 5,
            'endurance' => 5,
            'agility' => 5,
            'luck' => 5,
        ]);

        $this->actingAs($user)
            ->from(route('arena'))
            ->post(route('arena.battles.start'), ['creature_id' => $creature->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $battle = Battle::query()->firstOrFail();
        $participant = BattleParticipant::query()
            ->where('battle_id', $battle->id)
            ->where('creature_id', $creature->id)
            ->firstOrFail();

        $this->assertSame('finished', $battle->status);
        $this->assertGreaterThan(0, $participant->reward_xp);
        $this->assertGreaterThan(0, $participant->reward_tokens);
        $this->assertGreaterThan(0, $user->refresh()->tokens);
        $this->assertGreaterThan(0, $creature->refresh()->wins);
    }

    public function test_arena_page_displays_creatures_and_battle_history(): void
    {
        $user = User::factory()->create();
        $opponentUser = User::factory()->create();
        $creature = $this->creatureFor($user, ['name' => 'History Runner', 'strength' => 24]);
        $this->creatureFor($opponentUser, ['name' => 'History Rival', 'strength' => 8]);

        $this->actingAs($user)
            ->post(route('arena.battles.start'), ['creature_id' => $creature->id])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('arena'))
            ->assertOk()
            ->assertSee('History Runner')
            ->assertSee('История боев')
            ->assertSee('History Rival');
    }

    public function test_player_cannot_start_battle_without_opponent(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user);

        $this->actingAs($user)
            ->from(route('arena'))
            ->post(route('arena.battles.start'), ['creature_id' => $creature->id])
            ->assertRedirect(route('arena', absolute: false))
            ->assertSessionHasErrors('arena');
    }

    public function test_creature_can_level_up_after_battle_reward(): void
    {
        $user = User::factory()->create(['tokens' => 0]);
        $opponentUser = User::factory()->create(['tokens' => 0]);
        $creature = $this->creatureFor($user, [
            'level' => 1,
            'xp' => 90,
            'strength' => 30,
            'perception' => 20,
            'agility' => 20,
        ]);
        $this->creatureFor($opponentUser, [
            'level' => 2,
            'strength' => 5,
            'perception' => 5,
            'endurance' => 5,
            'agility' => 5,
        ]);

        $this->actingAs($user)
            ->post(route('arena.battles.start'), ['creature_id' => $creature->id])
            ->assertRedirect();

        $creature->refresh();

        $this->assertGreaterThan(1, $creature->level);
        $this->assertGreaterThanOrEqual(10, $creature->development_points);
    }

    public function test_rewards_are_reduced_for_much_weaker_opponent(): void
    {
        $user = User::factory()->create();
        $opponentUser = User::factory()->create();
        $creature = $this->creatureFor($user, [
            'strength' => 35,
            'perception' => 30,
            'endurance' => 30,
            'agility' => 30,
            'luck' => 20,
        ]);
        $this->creatureFor($opponentUser, [
            'strength' => 2,
            'perception' => 2,
            'endurance' => 2,
            'agility' => 2,
            'luck' => 2,
        ]);

        $this->actingAs($user)
            ->post(route('arena.battles.start'), ['creature_id' => $creature->id])
            ->assertRedirect();

        $participant = BattleParticipant::query()
            ->where('creature_id', $creature->id)
            ->firstOrFail();

        $this->assertLessThan(1, (float) $participant->reward_multiplier);
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
