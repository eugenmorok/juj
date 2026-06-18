<?php

namespace Tests\Feature;

use App\Models\BattleArena;
use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\User;
use App\Services\BattleArenaService;
use App\Services\InteractiveBattleService;
use Database\Seeders\BattleArenaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleArenaEffectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_six_seeded_arenas_are_available_for_random_selection(): void
    {
        $this->seed(BattleArenaSeeder::class);

        $this->assertSame(6, BattleArena::query()->active()->count());

        $selectedCodes = collect(range(1, 60))
            ->map(fn (int $seed): string => app(BattleArenaService::class)->selectForSeed($seed)->code)
            ->unique();

        $this->assertGreaterThan(1, $selectedCodes->count());
    }

    public function test_arena_effect_is_applied_equally_and_snapshotted_for_both_creatures(): void
    {
        BattleArena::query()->create([
            'name' => 'Испытательная арена',
            'code' => 'test-arena',
            'background_image' => 'game-assets/arena/storm-platform.webp',
            'special_effects' => ['endurance' => 2, 'agility' => -1],
            'is_active' => true,
        ]);

        $type = CreatureType::factory()->create(['code' => 'arena-effect-type']);
        $species = CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'code' => 'arena-effect-species',
        ]);
        $leftUser = User::factory()->create();
        $rightUser = User::factory()->create();
        $left = $this->creature($leftUser, $type, $species, 'Левый');
        $right = $this->creature($rightUser, $type, $species, 'Правый');

        $battle = app(InteractiveBattleService::class)->start($left, $right, $leftUser);
        $participants = $battle->participants()->orderBy('id')->get();
        $payload = app(InteractiveBattleService::class)->statePayload($battle, $leftUser);

        $this->assertSame('Испытательная арена', $battle->arena_name);
        $this->assertSame(['endurance' => 2, 'agility' => -1], $battle->arena_effects);
        $this->assertSame($participants[0]->hp_before, $participants[1]->hp_before);
        $this->assertSame(125, $participants[0]->hp_before);
        $this->assertSame(7, $payload['participants'][0]['special']['endurance']);
        $this->assertSame(4, $payload['participants'][0]['special']['agility']);
        $this->assertSame(
            $payload['participants'][0]['special'],
            $payload['participants'][1]['special'],
        );
        $this->assertSame('Испытательная арена', $payload['scene']['name']);
    }

    private function creature(
        User $user,
        CreatureType $type,
        CreatureSpecies $species,
        string $name,
    ): Creature {
        return Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $type->id,
            'creature_species_id' => $species->id,
            'name' => $name,
            'strength' => 5,
            'perception' => 5,
            'endurance' => 5,
            'charisma' => 5,
            'intelligence' => 5,
            'agility' => 5,
            'luck' => 5,
            'max_hp' => 100,
            'current_hp' => 100,
        ]);
    }
}
