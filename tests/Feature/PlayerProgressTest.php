<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_convert_xp_to_creature_creation_points(): void
    {
        $user = User::factory()->create([
            'xp' => 250,
            'creature_creation_points' => 20,
        ]);

        $this->actingAs($user)
            ->from(route('profile'))
            ->post(route('profile.creation-points.convert'), [
                'points' => 10,
            ])
            ->assertRedirect(route('profile', absolute: false))
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame(150, $user->xp);
        $this->assertSame(30, $user->creature_creation_points);
    }

    public function test_player_cannot_convert_more_xp_than_available(): void
    {
        $user = User::factory()->create([
            'xp' => 50,
            'creature_creation_points' => 0,
        ]);

        $this->actingAs($user)
            ->from(route('profile'))
            ->post(route('profile.creation-points.convert'), [
                'points' => 10,
            ])
            ->assertRedirect(route('profile', absolute: false))
            ->assertSessionHasErrors('points');

        $user->refresh();

        $this->assertSame(50, $user->xp);
        $this->assertSame(0, $user->creature_creation_points);
    }
}
