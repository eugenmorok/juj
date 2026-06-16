<?php

namespace Tests\Feature;

use App\Models\Creature;
use App\Models\CreatureEquipment;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\EquipmentSlot;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\Skill;
use App\Models\User;
use App\Services\BattleEngine;
use App\Services\PowerScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_battle_engine_finishes_battle_and_saves_log(): void
    {
        $attacker = $this->creatureFor(User::factory()->create(), [
            'name' => 'Alpha',
            'strength' => 28,
            'perception' => 18,
            'agility' => 18,
            'luck' => 12,
        ]);
        $defender = $this->creatureFor(User::factory()->create(), [
            'name' => 'Beta',
            'strength' => 6,
            'perception' => 6,
            'endurance' => 6,
            'agility' => 5,
            'luck' => 5,
        ]);

        $battle = app(BattleEngine::class)->run($attacker, $defender, seed: 12345);

        $this->assertTrue($battle->refresh()->is_draw || $battle->winner_creature_id !== null);
        $this->assertSame('finished', $battle->status);
        $this->assertSame(2, $battle->participants()->count());
        $this->assertGreaterThan(3, $battle->events()->count());
        $this->assertDatabaseHas('battle_events', [
            'battle_id' => $battle->id,
            'event_type' => 'battle_started',
        ]);
    }

    public function test_battle_engine_is_deterministic_for_same_seed(): void
    {
        $attacker = $this->creatureFor(User::factory()->create(), ['name' => 'Seed One', 'strength' => 22]);
        $defender = $this->creatureFor(User::factory()->create(), ['name' => 'Seed Two', 'strength' => 18]);

        $firstBattle = app(BattleEngine::class)->run($attacker, $defender, seed: 9876);
        $secondBattle = app(BattleEngine::class)->run($attacker, $defender, seed: 9876);

        $this->assertSame($firstBattle->winner_creature_id, $secondBattle->winner_creature_id);
        $this->assertSame($firstBattle->is_draw, $secondBattle->is_draw);
        $this->assertSame(
            $firstBattle->events()->pluck('text_log')->all(),
            $secondBattle->events()->pluck('text_log')->all(),
        );
    }

    public function test_power_score_includes_skills_and_equipment(): void
    {
        $creature = $this->creatureFor(User::factory()->create());
        $powerScore = app(PowerScoreService::class);
        $baseScore = $powerScore->calculate($creature);

        $skill = Skill::factory()->create(['cost' => 25]);
        $creature->skills()->attach($skill->id, ['cost_paid' => 25, 'source' => 'development']);

        $slot = EquipmentSlot::factory()->create(['code' => 'test-slot']);
        $item = Item::factory()->create([
            'price' => 200,
            'slot_key' => $slot->code,
            'slots_required' => [$slot->code],
            'bonuses' => ['strength' => 5, 'damage' => 8],
        ]);
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

        $this->assertGreaterThan($baseScore + 25, $powerScore->calculate($creature->refresh()));
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
            'strength' => 14,
            'perception' => 12,
            'endurance' => $endurance,
            'charisma' => 5,
            'intelligence' => 8,
            'agility' => 12,
            'luck' => 8,
            'current_hp' => $maxHp,
            'max_hp' => $maxHp,
            ...$attributes,
        ]);
    }
}
