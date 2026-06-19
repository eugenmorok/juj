<?php

namespace Tests\Feature;

use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatureCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_open_creature_creation_page(): void
    {
        $user = User::factory()->create();
        $species = $this->starterSpecies([
            'name' => 'Волк',
        ]);
        $skill = Skill::factory()->create([
            'name' => 'Быстрый удар',
            'cost' => 12,
        ]);

        $this->actingAs($user)
            ->get(route('entities.create'))
            ->assertOk()
            ->assertSee('Новая сущность')
            ->assertSee($species->name)
            ->assertSee($skill->name);
    }

    public function test_player_can_create_creature_with_valid_special_and_starter_skill(): void
    {
        $user = User::factory()->create();
        $species = $this->starterSpecies([
            'base_strength' => 5,
            'base_perception' => 5,
            'base_endurance' => 5,
            'base_charisma' => 5,
            'base_intelligence' => 5,
            'base_agility' => 5,
            'base_luck' => 5,
        ]);
        $skill = Skill::factory()->create([
            'name' => 'Критическое чутье',
            'cost' => 18,
            'required_perception' => 6,
            'required_luck' => 6,
            'is_starter_available' => true,
        ]);

        $response = $this->actingAs($user)->post(route('entities.store'), [
            'name' => 'Северный клинок',
            'creature_species_id' => $species->id,
            'strength' => 12,
            'perception' => 7,
            'endurance' => 8,
            'charisma' => 5,
            'intelligence' => 5,
            'agility' => 8,
            'luck' => 7,
            'skills' => [$skill->id],
        ]);

        $creature = Creature::query()->firstOrFail();

        $response->assertRedirect(route('entities.show', $creature, absolute: false));

        $this->assertSame($user->id, $creature->user_id);
        $this->assertSame($species->creature_type_id, $creature->creature_type_id);
        $this->assertSame($species->id, $creature->creature_species_id);
        $this->assertSame(12, $creature->strength);
        $this->assertSame(7, $creature->perception);
        $this->assertSame(8, $creature->endurance);
        $this->assertSame(Creature::maxHpForEndurance(8), $creature->max_hp);
        $this->assertSame(0, $user->refresh()->creature_creation_points);

        $this->assertDatabaseHas('creature_skills', [
            'creature_id' => $creature->id,
            'skill_id' => $skill->id,
            'cost_paid' => 18,
            'source' => 'creation',
        ]);
    }

    public function test_player_cannot_create_second_creature_without_creation_points(): void
    {
        $user = User::factory()->create();
        $species = $this->starterSpecies();

        $this->actingAs($user)
            ->post(route('entities.store'), $this->creationPayload($species))
            ->assertRedirect();

        $this->actingAs($user)
            ->from(route('entities.create'))
            ->post(route('entities.store'), $this->creationPayload($species, [
                'name' => 'Вторая сущность',
            ]))
            ->assertRedirect(route('entities.create', absolute: false))
            ->assertSessionHasErrors('creation_points');

        $this->assertDatabaseCount('creatures', 1);
    }

    public function test_player_can_spend_development_points_to_improve_special(): void
    {
        $user = User::factory()->create();
        $species = $this->starterSpecies();
        $creature = Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $species->creature_type_id,
            'creature_species_id' => $species->id,
            'strength' => 10,
            'endurance' => 10,
            'current_hp' => Creature::maxHpForEndurance(10),
            'max_hp' => Creature::maxHpForEndurance(10),
            'development_points' => Creature::SPECIAL_DEVELOPMENT_COST,
            'is_available_for_battle' => true,
        ]);

        $this->actingAs($user)
            ->from(route('entities.show', $creature))
            ->post(route('entities.special.increase', $creature), [
                'attribute' => 'strength',
            ])
            ->assertRedirect(route('entities.show', $creature, absolute: false))
            ->assertSessionHasNoErrors();

        $creature->refresh();

        $this->assertSame(11, $creature->strength);
        $this->assertSame(0, $creature->development_points);
    }

    public function test_endurance_improvement_increases_creature_hp(): void
    {
        $user = User::factory()->create();
        $species = $this->starterSpecies();
        $creature = Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $species->creature_type_id,
            'creature_species_id' => $species->id,
            'strength' => 10,
            'endurance' => 10,
            'current_hp' => 150,
            'max_hp' => 150,
            'development_points' => Creature::SPECIAL_DEVELOPMENT_COST,
            'is_available_for_battle' => true,
        ]);

        $this->actingAs($user)
            ->post(route('entities.special.increase', $creature), [
                'attribute' => 'endurance',
            ])
            ->assertRedirect();

        $creature->refresh();

        $this->assertSame(11, $creature->endurance);
        $this->assertSame(160, $creature->max_hp);
        $this->assertSame(160, $creature->current_hp);
    }

    public function test_player_cannot_create_creature_below_species_base(): void
    {
        $user = User::factory()->create();
        $species = $this->starterSpecies([
            'base_strength' => 7,
        ]);

        $this->actingAs($user)
            ->from(route('entities.create'))
            ->post(route('entities.store'), $this->creationPayload($species, [
                'strength' => 6,
            ]))
            ->assertRedirect(route('entities.create', absolute: false))
            ->assertSessionHasErrors('strength');

        $this->assertDatabaseCount('creatures', 0);
    }

    public function test_player_cannot_spend_more_than_creation_budget(): void
    {
        $user = User::factory()->create();
        $species = $this->starterSpecies([
            'base_strength' => 1,
            'base_perception' => 1,
            'base_endurance' => 1,
            'base_charisma' => 1,
            'base_intelligence' => 1,
            'base_agility' => 1,
            'base_luck' => 1,
        ]);

        $this->actingAs($user)
            ->from(route('entities.create'))
            ->post(route('entities.store'), $this->creationPayload($species, [
                'strength' => 20,
                'perception' => 20,
                'endurance' => 20,
                'charisma' => 20,
                'intelligence' => 20,
                'agility' => 20,
                'luck' => 20,
            ]))
            ->assertRedirect(route('entities.create', absolute: false))
            ->assertSessionHasErrors('points');
    }

    public function test_species_base_is_free_and_player_can_spend_full_hundred_points_above_it(): void
    {
        $user = User::factory()->create();
        $species = $this->starterSpecies();

        $this->actingAs($user)
            ->post(route('entities.store'), $this->creationPayload($species, [
                'strength' => 20,
                'perception' => 20,
                'endurance' => 20,
                'charisma' => 20,
                'intelligence' => 20,
                'agility' => 20,
                'luck' => 15,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $creature = Creature::query()->firstOrFail();

        $this->assertSame(100, $creature->spentCreationPoints($species));
        $this->assertSame(0, $user->refresh()->creature_creation_points);
    }

    public function test_player_cannot_exceed_starter_special_cap(): void
    {
        $user = User::factory()->create();
        $species = $this->starterSpecies();

        $this->actingAs($user)
            ->from(route('entities.create'))
            ->post(route('entities.store'), $this->creationPayload($species, [
                'strength' => Creature::STARTER_SPECIAL_CAP + 1,
            ]))
            ->assertRedirect(route('entities.create', absolute: false))
            ->assertSessionHasErrors('strength');
    }

    public function test_player_cannot_create_creature_from_unavailable_species(): void
    {
        $user = User::factory()->create();
        $species = $this->starterSpecies([
            'is_starter_available' => false,
        ]);

        $this->actingAs($user)
            ->from(route('entities.create'))
            ->post(route('entities.store'), $this->creationPayload($species))
            ->assertRedirect(route('entities.create', absolute: false))
            ->assertSessionHasErrors('creature_species_id');
    }

    public function test_player_cannot_buy_nonstarter_skill_during_creation(): void
    {
        $user = User::factory()->create();
        $species = $this->starterSpecies();
        $skill = Skill::factory()->notStarter()->create([
            'cost' => 5,
        ]);

        $this->actingAs($user)
            ->from(route('entities.create'))
            ->post(route('entities.store'), $this->creationPayload($species, [
                'skills' => [$skill->id],
            ]))
            ->assertRedirect(route('entities.create', absolute: false))
            ->assertSessionHasErrors('skills');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function starterSpecies(array $attributes = []): CreatureSpecies
    {
        return CreatureSpecies::factory()->create([
            'creature_type_id' => CreatureType::factory()->create([
                'is_active' => true,
            ])->id,
            'base_strength' => 5,
            'base_perception' => 5,
            'base_endurance' => 5,
            'base_charisma' => 5,
            'base_intelligence' => 5,
            'base_agility' => 5,
            'base_luck' => 5,
            'is_active' => true,
            'is_starter_available' => true,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function creationPayload(CreatureSpecies $species, array $overrides = []): array
    {
        return [
            'name' => 'Тестовая сущность',
            'creature_species_id' => $species->id,
            'strength' => max(5, $species->base_strength),
            'perception' => max(5, $species->base_perception),
            'endurance' => max(5, $species->base_endurance),
            'charisma' => max(5, $species->base_charisma),
            'intelligence' => max(5, $species->base_intelligence),
            'agility' => max(5, $species->base_agility),
            'luck' => max(5, $species->base_luck),
            ...$overrides,
        ];
    }
}
