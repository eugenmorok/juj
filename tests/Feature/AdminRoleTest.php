<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_can_open_admin_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('filament.admin.pages.dashboard'))
            ->assertOk()
            ->assertSee('Инфопанель')
            ->assertSee('Типы сущностей')
            ->assertSee('Навыки')
            ->assertSee('Открыть');
    }

    public function test_regular_user_cannot_open_admin_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('filament.admin.pages.dashboard'))
            ->assertForbidden();
    }

    public function test_database_seeder_creates_admin_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@rpg-arena.test',
            'is_admin' => true,
        ]);
    }
}
