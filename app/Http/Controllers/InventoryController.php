<?php

namespace App\Http\Controllers;

use App\Models\Creature;
use App\Models\Inventory;
use App\Models\InventoryItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $playerInventory = $user
            ->ensureInventory()
            ->load('inventoryItems.itemInstance.item');

        $creatures = $user->creatures()
            ->with(['type', 'species'])
            ->orderBy('name')
            ->get();

        $creatures->each(function (Creature $creature): void {
            $creature->ensureInventory();
        });

        $creatures->load('inventory.inventoryItems.itemInstance.item');

        return view('game.inventory', [
            'user' => $user,
            'playerInventory' => $playerInventory,
            'creatures' => $creatures,
        ]);
    }

    public function moveToCreature(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $attributes = $request->validate([
            'creature_id' => ['required', 'integer', 'exists:creatures,id'],
        ]);

        $sourceInventory = $this->sourceInventory($request, $inventoryItem);

        if ($sourceInventory->inventory_type !== Inventory::TYPE_PLAYER) {
            throw ValidationException::withMessages([
                'inventory' => 'Сначала переместите предмет в общий инвентарь игрока.',
            ]);
        }

        $creature = Creature::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail((int) $attributes['creature_id']);

        $this->ensureCreatureCanMoveItems($creature);

        $targetInventory = $creature->ensureInventory();
        $this->ensureHasFreeSlot($targetInventory);

        $this->moveInventoryItem($inventoryItem, $targetInventory);

        return back()->with('status', 'Предмет перемещен в инвентарь сущности.');
    }

    public function moveToPlayer(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $sourceInventory = $this->sourceInventory($request, $inventoryItem);

        if ($sourceInventory->creature) {
            $this->ensureCreatureCanMoveItems($sourceInventory->creature);
        }

        $targetInventory = $request->user()->ensureInventory();
        $this->ensureHasFreeSlot($targetInventory);

        $this->moveInventoryItem($inventoryItem, $targetInventory);

        return back()->with('status', 'Предмет перемещен в общий инвентарь.');
    }

    private function sourceInventory(Request $request, InventoryItem $inventoryItem): Inventory
    {
        $inventoryItem->loadMissing([
            'inventory.creature',
            'inventory.owner',
            'itemInstance',
        ]);

        $inventory = $inventoryItem->inventory;

        abort_unless($inventory->owner_user_id === $request->user()->id, 404);
        abort_unless($inventoryItem->itemInstance?->owner_user_id === $request->user()->id, 404);

        if ($inventory->creature) {
            $this->ensureCreatureCanMoveItems($inventory->creature);
        }

        return $inventory;
    }

    private function ensureCreatureCanMoveItems(Creature $creature): void
    {
        if (! $creature->is_available_for_battle) {
            throw ValidationException::withMessages([
                'inventory' => 'Нельзя переносить предметы сущности, которая находится в бою.',
            ]);
        }
    }

    private function ensureHasFreeSlot(Inventory $inventory): void
    {
        if (! $inventory->hasFreeSlot()) {
            throw ValidationException::withMessages([
                'inventory' => 'В целевом инвентаре нет свободных ячеек.',
            ]);
        }
    }

    private function moveInventoryItem(InventoryItem $inventoryItem, Inventory $targetInventory): void
    {
        DB::transaction(function () use ($inventoryItem, $targetInventory): void {
            $slotNumber = $targetInventory->nextSlotNumber();

            if ($slotNumber === null) {
                throw ValidationException::withMessages([
                    'inventory' => 'В целевом инвентаре нет свободных ячеек.',
                ]);
            }

            $inventoryItem->forceFill([
                'inventory_id' => $targetInventory->id,
                'slot_number' => $slotNumber,
            ])->save();

            $inventoryItem->itemInstance->forceFill([
                'owner_user_id' => $targetInventory->owner_user_id,
                'bound_creature_id' => $targetInventory->creature_id,
                'state' => 'stored',
            ])->save();
        });
    }
}
