<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\BotProfile;
use App\Models\Creature;
use App\Models\CreatureEquipment;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\EquipmentSlot;
use App\Models\Item;
use App\Models\User;
use App\Services\ArenaService;
use App\Services\BotGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_profile_creates_bot_user(): void
    {
        $profile = BotProfile::query()->create([
            'display_name' => 'Arena Automaton',
            'style' => 'balanced',
            'is_active' => true,
            'min_level' => 1,
            'max_level' => 2,
            'spawn_chance' => 100,
        ]);

        $this->assertNotNull($profile->user_id);
        $this->assertTrue($profile->user->is_bot);
        $this->assertSame('Arena Automaton', $profile->user->name);
        $this->assertFalse($profile->user->is_admin);
    }

    public function test_service_generates_batch_with_creatures_and_equipment(): void
    {
        $this->starterCatalog();
        $this->starterItem();

        $profiles = app(BotGenerationService::class)->generateBatch(
            count: 3,
            style: 'aggressive',
            minLevel: 1,
            maxLevel: 2,
            withCreature: true,
            withEquipment: true,
        );

        $this->assertCount(3, $profiles);
        $this->assertSame(3, BotProfile::query()->count());
        $this->assertSame(3, User::query()->where('is_bot', true)->count());
        $this->assertSame(3, Creature::query()->count());
        $this->assertGreaterThanOrEqual(1, CreatureEquipment::query()->count());
    }

    public function test_admin_can_open_bot_resource_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = BotProfile::factory()->create(['display_name' => 'Admin Bot']);

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.bot-profiles.index'))
            ->assertOk()
            ->assertSee('Admin Bot');

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.bot-profiles.create'))
            ->assertOk()
            ->assertSee('Имя бота')
            ->assertSee('Стиль');

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.bot-profiles.edit', ['record' => $profile]))
            ->assertOk()
            ->assertSee('Admin Bot');
    }

    public function test_player_can_fight_active_bot_when_no_real_opponent_exists(): void
    {
        $this->starterCatalog();
        $this->starterItem();

        $player = User::factory()->create();
        $playerCreature = $this->creatureFor($player, ['name' => 'Player Fighter']);
        $botProfile = BotProfile::query()->create([
            'display_name' => 'Arena Bot',
            'style' => 'defensive',
            'is_active' => true,
            'min_level' => 1,
            'max_level' => 1,
            'spawn_chance' => 100,
        ]);
        app(BotGenerationService::class)->generateCreature($botProfile, withEquipment: true);

        $battle = app(ArenaService::class)->startBattle($player, $playerCreature);

        $this->assertSame('finished', $battle->refresh()->status);
        $this->assertTrue($battle->participants()->where('is_bot', true)->exists());
        $this->assertDatabaseHas('battle_events', [
            'battle_id' => $battle->id,
            'event_type' => 'battle_started',
        ]);
    }

    public function test_inactive_bot_is_not_used_in_matchmaking(): void
    {
        $this->starterCatalog();

        $player = User::factory()->create();
        $playerCreature = $this->creatureFor($player);
        $botProfile = BotProfile::query()->create([
            'display_name' => 'Sleeping Bot',
            'style' => 'balanced',
            'is_active' => false,
            'min_level' => 1,
            'max_level' => 1,
            'spawn_chance' => 100,
        ]);
        app(BotGenerationService::class)->generateCreature($botProfile, withEquipment: false);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ArenaService::class)->findOpponent($playerCreature);
    }

    public function test_bot_with_full_spawn_chance_can_override_real_candidate(): void
    {
        $this->starterCatalog();

        $player = User::factory()->create();
        $playerCreature = $this->creatureFor($player);
        $realOpponent = User::factory()->create();
        $this->creatureFor($realOpponent, ['name' => 'Real Opponent']);
        $botProfile = BotProfile::query()->create([
            'display_name' => 'Frequent Bot',
            'style' => 'balanced',
            'is_active' => true,
            'min_level' => 1,
            'max_level' => 1,
            'spawn_chance' => 100,
        ]);
        $botCreature = app(BotGenerationService::class)->generateCreature($botProfile, withEquipment: false);

        $opponent = app(ArenaService::class)->findOpponent($playerCreature);

        $this->assertSame($botCreature->id, $opponent->id);
    }

    private function starterCatalog(): CreatureSpecies
    {
        $type = CreatureType::factory()->create([
            'name' => 'Animals',
            'code' => 'animals-test',
            'is_active' => true,
        ]);

        return CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'name' => 'Wolf Test',
            'code' => 'wolf-test',
            'base_strength' => 5,
            'base_perception' => 5,
            'base_endurance' => 5,
            'base_charisma' => 5,
            'base_intelligence' => 5,
            'base_agility' => 5,
            'base_luck' => 5,
            'is_active' => true,
        ]);
    }

    private function starterItem(): Item
    {
        $slot = EquipmentSlot::factory()->create([
            'name' => 'Body',
            'code' => 'body-test',
            'is_active' => true,
        ]);

        return Item::factory()->create([
            'name' => 'Bot Plate',
            'code' => 'bot-plate',
            'item_type' => 'equipment',
            'rarity' => 'common',
            'price' => 80,
            'required_level' => 1,
            'slot_key' => $slot->code,
            'slots_required' => [$slot->code],
            'bonuses' => ['endurance' => 2, 'armor' => 2],
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function creatureFor(User $user, array $attributes = []): Creature
    {
        $species = CreatureSpecies::query()->first() ?? $this->starterCatalog();
        $endurance = $attributes['endurance'] ?? 12;
        $maxHp = Creature::maxHpForEndurance($endurance);

        return Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $species->creature_type_id,
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
