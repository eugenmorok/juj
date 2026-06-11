<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_and_get_default_player_fields(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Arena Player',
            'email' => 'player@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'player@example.com')->firstOrFail();

        $this->assertSame(1, $user->level);
        $this->assertSame(0, $user->xp);
        $this->assertSame(0, $user->tokens);
        $this->assertSame(5, $user->inventory_slots);
        $this->assertFalse($user->is_bot);
        $this->assertFalse($user->is_admin);
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
        ]);

        $this->post(route('login'), [
            'email' => 'login@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('home', absolute: false));

        $this->assertGuest();
    }
}
