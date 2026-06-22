<?php

namespace Tests\Feature;

use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_skill_resource_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $skill = Skill::factory()->create();

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.skills.create'))
            ->assertOk()
            ->assertSee('Стоимость');

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.skills.edit', ['record' => $skill]))
            ->assertOk()
            ->assertSee($skill->name);
    }

    public function test_creature_card_displays_owned_and_available_skills(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, [
            'development_points' => 30,
            'intelligence' => 8,
        ]);
        $ownedSkill = Skill::factory()->create([
            'name' => 'Толстая шкура',
        ]);
        $availableSkill = Skill::factory()->create([
            'name' => 'Анализ слабости',
            'cost' => 20,
            'required_intelligence' => 7,
        ]);

        $creature->skills()->attach($ownedSkill->id, [
            'cost_paid' => $ownedSkill->cost,
            'source' => 'creation',
        ]);

        $this->actingAs($user)
            ->get(route('entities.show', $creature))
            ->assertOk()
            ->assertSee($ownedSkill->name)
            ->assertSee($availableSkill->name)
            ->assertSee('Купить');
    }

    public function test_creature_card_generates_at_least_four_available_skills_when_catalog_is_empty(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, [
            'development_points' => 50,
        ]);

        $this->assertSame(0, Skill::query()->count());

        $this->actingAs($user)
            ->get(route('entities.show', $creature))
            ->assertOk()
            ->assertViewHas('availableSkills', fn ($skills): bool => $skills->count() >= 4)
            ->assertDontSee('Новых навыков для покупки нет.')
            ->assertSee('Купить');

        $this->assertGreaterThanOrEqual(4, Skill::query()->where('code', 'like', 'generated-skill-%')->count());
    }

    public function test_player_can_buy_available_skill_with_development_points(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, [
            'development_points' => 25,
            'intelligence' => 8,
        ]);
        $skill = Skill::factory()->notStarter()->create([
            'cost' => 20,
            'required_intelligence' => 7,
        ]);

        $this->actingAs($user)
            ->from(route('entities.show', $creature))
            ->post(route('entities.skills.purchase', [$creature, $skill]))
            ->assertRedirect(route('entities.show', $creature, absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame(5, $creature->refresh()->development_points);
        $this->assertDatabaseHas('creature_skills', [
            'creature_id' => $creature->id,
            'skill_id' => $skill->id,
            'cost_paid' => 20,
            'source' => 'development',
        ]);
    }

    public function test_player_cannot_buy_unavailable_skill(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, [
            'development_points' => 100,
        ]);
        $otherType = CreatureType::factory()->create();
        $skill = Skill::factory()->create([
            'required_creature_type_id' => $otherType->id,
            'cost' => 20,
        ]);

        $this->actingAs($user)
            ->from(route('entities.show', $creature))
            ->post(route('entities.skills.purchase', [$creature, $skill]))
            ->assertRedirect(route('entities.show', $creature, absolute: false))
            ->assertSessionHasErrors('skill');

        $this->assertDatabaseMissing('creature_skills', [
            'creature_id' => $creature->id,
            'skill_id' => $skill->id,
        ]);
    }

    public function test_player_cannot_buy_skill_without_development_points(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, [
            'development_points' => 5,
            'intelligence' => 8,
        ]);
        $skill = Skill::factory()->create([
            'cost' => 20,
            'required_intelligence' => 7,
        ]);

        $this->actingAs($user)
            ->from(route('entities.show', $creature))
            ->post(route('entities.skills.purchase', [$creature, $skill]))
            ->assertRedirect(route('entities.show', $creature, absolute: false))
            ->assertSessionHasErrors('skill');
    }

    public function test_player_cannot_exceed_skill_limit_for_level(): void
    {
        $user = User::factory()->create();
        $creature = $this->creatureFor($user, [
            'development_points' => 100,
        ]);
        $existingSkills = Skill::factory()->count(Creature::BASE_SKILL_LIMIT)->create();
        $newSkill = Skill::factory()->create([
            'cost' => 10,
        ]);

        foreach ($existingSkills as $skill) {
            $creature->skills()->attach($skill->id, [
                'cost_paid' => $skill->cost,
                'source' => 'creation',
            ]);
        }

        $this->actingAs($user)
            ->from(route('entities.show', $creature))
            ->post(route('entities.skills.purchase', [$creature, $newSkill]))
            ->assertRedirect(route('entities.show', $creature, absolute: false))
            ->assertSessionHasErrors('skill');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function creatureFor(User $user, array $attributes = []): Creature
    {
        $type = CreatureType::factory()->create();
        $species = CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
            'base_strength' => 5,
            'base_perception' => 5,
            'base_endurance' => 5,
            'base_charisma' => 5,
            'base_intelligence' => 5,
            'base_agility' => 5,
            'base_luck' => 5,
        ]);

        $endurance = $attributes['endurance'] ?? 7;
        $maxHp = Creature::maxHpForEndurance($endurance);

        return Creature::factory()->create([
            'user_id' => $user->id,
            'creature_type_id' => $type->id,
            'creature_species_id' => $species->id,
            'strength' => 7,
            'perception' => 7,
            'endurance' => $endurance,
            'charisma' => 5,
            'intelligence' => 7,
            'agility' => 7,
            'luck' => 7,
            'current_hp' => $maxHp,
            'max_hp' => $maxHp,
            ...$attributes,
        ]);
    }
}
