<?php

namespace Tests\Feature;

use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\User;
use Database\Seeders\CreatureCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatureCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeder_creates_starter_types_and_species(): void
    {
        $this->seed(CreatureCatalogSeeder::class);

        $this->assertSame(3, CreatureType::query()->count());
        $this->assertSame(15, CreatureSpecies::query()->count());

        foreach (['Животные', 'Механоиды', 'Инсекты'] as $name) {
            $this->assertDatabaseHas('creature_types', [
                'name' => $name,
                'is_active' => true,
            ]);
        }

        foreach (['Волк', 'Медведь', 'Крыса-мутант', 'Дрон-разведчик', 'Паук-охотник'] as $name) {
            $this->assertDatabaseHas('creature_species', [
                'name' => $name,
                'is_active' => true,
                'is_starter_available' => true,
            ]);
        }

        foreach ([
            'bear' => 'game-assets/creatures/animal-bear.webp',
            'boar' => 'game-assets/creatures/animal-boar.webp',
            'lynx' => 'game-assets/creatures/animal-lynx.webp',
            'mutant-rat' => 'game-assets/creatures/animal-mutant-rat.webp',
        ] as $code => $image) {
            $this->assertDatabaseHas('creature_species', [
                'code' => $code,
                'portrait_image' => $image,
                'battle_image' => $image,
            ]);
        }
    }

    public function test_player_entities_page_shows_only_active_types_and_species(): void
    {
        $activeType = CreatureType::factory()->create([
            'name' => 'Активный тип',
            'is_active' => true,
        ]);
        $inactiveType = CreatureType::factory()->inactive()->create([
            'name' => 'Скрытый тип',
        ]);

        CreatureSpecies::factory()->create([
            'creature_type_id' => $activeType->id,
            'name' => 'Активный вид',
            'is_active' => true,
        ]);
        CreatureSpecies::factory()->inactive()->create([
            'creature_type_id' => $activeType->id,
            'name' => 'Скрытый вид',
        ]);
        CreatureSpecies::factory()->create([
            'creature_type_id' => $inactiveType->id,
            'name' => 'Вид скрытого типа',
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('entities.index'))
            ->assertOk()
            ->assertSee('Активный тип')
            ->assertSee('Активный вид')
            ->assertDontSee('Скрытый тип')
            ->assertDontSee('Скрытый вид')
            ->assertDontSee('Вид скрытого типа');
    }

    public function test_admin_can_open_creature_catalog_resource_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $type = CreatureType::factory()->create();
        $species = CreatureSpecies::factory()->create([
            'creature_type_id' => $type->id,
        ]);

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.creature-types.create'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.creature-species.create'))
            ->assertOk()
            ->assertSee('Strength')
            ->assertSee('Perception')
            ->assertSee('Luck');

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.creature-types.edit', ['record' => $type]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.creature-species.edit', ['record' => $species]))
            ->assertOk();
    }
}
