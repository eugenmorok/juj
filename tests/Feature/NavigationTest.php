<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_protected_pages(): void
    {
        foreach (['dashboard', 'profile', 'entities.index', 'arena', 'shop', 'inventory', 'help'] as $route) {
            $this->get(route($route))->assertRedirect(route('login', absolute: false));
        }
    }

    public function test_authenticated_user_can_open_basic_pages(): void
    {
        $user = User::factory()->create();

        foreach ([
            'dashboard' => 'Личный кабинет',
            'profile' => 'Профиль игрока',
            'entities.index' => 'Сущности',
            'arena' => 'Арена',
            'shop' => 'Магазин',
            'inventory' => 'Инвентарь',
            'help' => 'Справка по арене',
        ] as $route => $text) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertOk()
                ->assertSee($text);
        }
    }

    public function test_dashboard_contains_basic_navigation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Профиль')
            ->assertSee('Сущности')
            ->assertSee('Арена')
            ->assertSee('Магазин')
            ->assertSee('Инвентарь')
            ->assertSee('Справка');
    }
}
