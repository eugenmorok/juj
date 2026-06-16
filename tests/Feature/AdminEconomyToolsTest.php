<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\User;
use App\Services\ShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminEconomyToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_user_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create([
            'name' => 'Grant Target',
            'email' => 'grant-target@example.com',
            'tokens' => 45,
        ]);
        $player->ensureInventory();

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.users.index'))
            ->assertOk()
            ->assertSee('Grant Target')
            ->assertSee('grant-target@example.com')
            ->assertSee('45');
    }

    public function test_shop_service_grants_tokens_and_items_to_player_inventory(): void
    {
        $user = User::factory()->create(['tokens' => 10]);
        $item = Item::factory()->potion()->create([
            'name' => 'Admin Serum',
            'price' => 70,
            'uses_count' => 3,
        ]);

        app(ShopService::class)->grantTokens($user, 75);
        app(ShopService::class)->grantItem($user, $item, 2);

        $this->assertSame(85, $user->refresh()->tokens);
        $this->assertDatabaseCount('item_instances', 2);
        $this->assertDatabaseCount('inventory_items', 2);
        $this->assertDatabaseHas('item_instances', [
            'item_id' => $item->id,
            'owner_user_id' => $user->id,
            'bound_creature_id' => null,
            'durability' => 3,
            'state' => 'stored',
        ]);
    }

    public function test_unique_item_cannot_be_granted_twice_while_active(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->unique()->create([
            'price' => 100,
            'required_level' => 1,
        ]);

        app(ShopService::class)->grantItem($user, $item);

        try {
            app(ShopService::class)->grantItem($user, $item);
            $this->fail('Duplicate unique item grant was not blocked.');
        } catch (ValidationException) {
            $this->assertSame(1, ItemInstance::query()->where('item_id', $item->id)->count());
        }

    }
}
