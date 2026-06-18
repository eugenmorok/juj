<?php

namespace Tests\Feature;

use App\Models\BattleEvent;
use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\User;
use App\Services\InteractiveBattleService;
use App\Support\MediaUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleMediaPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_battle_page_contains_pixi_stage_and_media_configuration(): void
    {
        [$user, $battle] = $this->battle();

        $this->actingAs($user)
            ->get(route('arena.battles.show', $battle))
            ->assertOk()
            ->assertSee('data-battle-visualizer', false)
            ->assertSee('data-battle-scene-config', false)
            ->assertSee('game-assets/arena/industrial-fantasy-arena.webp', false)
            ->assertSee('game-assets/creatures/animal-wolf.webp', false);
    }

    public function test_battle_state_returns_only_new_structured_events(): void
    {
        [$user, $battle] = $this->battle();
        $lastKnownEventId = (int) $battle->events()->max('id');
        $participants = $battle->participants()->orderBy('id')->get();

        $event = BattleEvent::query()->create([
            'battle_id' => $battle->id,
            'round' => 1,
            'event_type' => 'interactive_hit',
            'actor_creature_id' => $participants[0]->creature_id,
            'target_creature_id' => $participants[1]->creature_id,
            'payload' => [
                'damage' => 17,
                'attack_zone' => 'body',
                'target_hp' => 83,
            ],
            'text_log' => 'Тестовый удар.',
        ]);

        $this->actingAs($user)
            ->getJson(route('arena.battles.state', [
                'battle' => $battle,
                'after_event_id' => $lastKnownEventId,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.id', $event->id)
            ->assertJsonPath('events.0.event_type', 'interactive_hit')
            ->assertJsonPath('events.0.payload.damage', 17)
            ->assertJsonPath('participants.0.image_url', url('game-assets/creatures/animal-wolf.webp'));
    }

    public function test_media_url_supports_bundled_and_uploaded_assets(): void
    {
        $this->assertSame(
            url('game-assets/creatures/animal-wolf.webp'),
            MediaUrl::resolve('game-assets/creatures/animal-wolf.webp'),
        );
        $this->assertSame(
            url('storage/media/species/battle/example.webp'),
            MediaUrl::resolve('media/species/battle/example.webp'),
        );
    }

    /**
     * @return array{0: User, 1: \App\Models\Battle}
     */
    private function battle(): array
    {
        $type = CreatureType::factory()->create(['code' => 'animals']);
        $species = CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'code' => 'media-wolf',
            'portrait_image' => 'game-assets/creatures/animal-wolf.webp',
            'battle_image' => 'game-assets/creatures/animal-wolf.webp',
        ]);
        $user = User::factory()->create();
        $opponent = User::factory()->create();
        $left = Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $type->id,
            'creature_species_id' => $species->id,
            'name' => 'Медиа-волк',
        ]);
        $right = Creature::factory()->create([
            'user_id' => $opponent->id,
            'creature_type_id' => $type->id,
            'creature_species_id' => $species->id,
            'name' => 'Соперник',
        ]);
        $battle = app(InteractiveBattleService::class)->start($left, $right, $user);

        return [$user, $battle];
    }
}
