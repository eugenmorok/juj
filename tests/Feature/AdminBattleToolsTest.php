<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\User;
use App\Services\BattleEngine;
use App\Services\BattleRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBattleToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_battle_resource(): void
    {
        $admin = User::factory()->admin()->create();
        [$type, $species] = $this->catalog();
        $left = $this->creatureFor(User::factory()->create(), $type, $species, ['name' => 'Admin Alpha']);
        $right = $this->creatureFor(User::factory()->create(), $type, $species, ['name' => 'Admin Beta']);

        app(BattleEngine::class)->run($left, $right, seed: 101);

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.battles.index'))
            ->assertOk()
            ->assertSee('Admin Alpha')
            ->assertSee('Admin Beta');
    }

    public function test_admin_can_view_non_participant_battle_and_regular_user_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $outsider = User::factory()->create();
        [$type, $species] = $this->catalog();
        $left = $this->creatureFor(User::factory()->create(), $type, $species, ['name' => 'Observed Left']);
        $right = $this->creatureFor(User::factory()->create(), $type, $species, ['name' => 'Observed Right']);
        $battle = app(BattleEngine::class)->run($left, $right, seed: 202);

        $this->actingAs($admin)
            ->get(route('arena.battles.show', $battle))
            ->assertOk()
            ->assertSee('Observed Left')
            ->assertSee('Observed Right');

        $this->actingAs($admin)
            ->get(route('arena.battles.replay', $battle))
            ->assertOk()
            ->assertSee('Observed Left')
            ->assertSee('Observed Right');

        $this->actingAs($admin)
            ->getJson(route('arena.battles.state', $battle))
            ->assertOk()
            ->assertJsonPath('battle_id', $battle->id);

        $this->actingAs($outsider)
            ->get(route('arena.battles.show', $battle))
            ->assertNotFound();
    }

    public function test_simulation_battle_does_not_grant_rewards(): void
    {
        $admin = User::factory()->admin()->create();
        $leftUser = User::factory()->create(['tokens' => 0]);
        $rightUser = User::factory()->create(['tokens' => 0]);
        [$type, $species] = $this->catalog();
        $left = $this->creatureFor($leftUser, $type, $species, ['name' => 'Simulation Left']);
        $right = $this->creatureFor($rightUser, $type, $species, ['name' => 'Simulation Right']);

        $battle = app(BattleEngine::class)->run(
            $left,
            $right,
            seed: 303,
            battleType: Battle::TYPE_SIMULATION,
            initiator: $admin,
        );

        app(BattleRewardService::class)->apply($battle);

        $this->assertSame(Battle::TYPE_SIMULATION, $battle->refresh()->battle_type);
        $this->assertSame($admin->id, $battle->initiator_user_id);
        $this->assertSame(0, $leftUser->refresh()->tokens);
        $this->assertSame(0, $rightUser->refresh()->tokens);
        $this->assertSame(0, $left->refresh()->wins + $left->losses + $left->draws);
        $this->assertSame(0, $right->refresh()->wins + $right->losses + $right->draws);

        $battle->participants()->each(function (BattleParticipant $participant): void {
            $this->assertSame(0, $participant->reward_xp);
            $this->assertSame(0, $participant->reward_tokens);
            $this->assertSame(0, $participant->reward_development_points);
        });
    }

    /**
     * @return array{0: CreatureType, 1: CreatureSpecies}
     */
    private function catalog(): array
    {
        $type = CreatureType::factory()->create(['code' => 'admin-battle-tools-type']);
        $species = CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'code' => 'admin-battle-tools-species',
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
        $endurance = $attributes['endurance'] ?? 12;
        $maxHp = Creature::maxHpForEndurance($endurance);

        return Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $type->id,
            'creature_species_id' => $species->id,
            'level' => 1,
            'strength' => 14,
            'perception' => 12,
            'endurance' => $endurance,
            'charisma' => 7,
            'intelligence' => 8,
            'agility' => 11,
            'luck' => 8,
            'current_hp' => $maxHp,
            'max_hp' => $maxHp,
            'is_available_for_battle' => true,
            ...$attributes,
        ]);
    }
}
