<?php

namespace App\Http\Controllers;

use App\Models\Creature;
use App\Models\EquipmentSlot;
use App\Models\InventoryItem;
use App\Models\ItemInstance;
use App\Services\CreatureEquipmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    public function show(Request $request, Creature $creature): View
    {
        $this->authorizeCreatureOwner($request, $creature);

        $creature->ensureInventory();
        $creature->load([
            'type',
            'species',
            'inventory.inventoryItems.itemInstance.item',
            'equipmentRows.slot',
            'equipmentRows.itemInstance.item',
        ]);

        return view('game.creatures.equipment', [
            'creature' => $creature,
            'equipmentSlots' => EquipmentSlot::query()
                ->active()
                ->orderBy('sort_order')
                ->get(),
            'playerInventory' => $request->user()
                ->ensureInventory()
                ->load('inventoryItems.itemInstance.item'),
        ]);
    }

    public function equip(
        Request $request,
        Creature $creature,
        InventoryItem $inventoryItem,
        CreatureEquipmentService $equipmentService
    ): RedirectResponse {
        $this->authorizeCreatureOwner($request, $creature);

        $equipmentService->equip($creature, $inventoryItem);

        return back()->with('status', 'Предмет экипирован.');
    }

    public function unequip(
        Request $request,
        Creature $creature,
        ItemInstance $itemInstance,
        CreatureEquipmentService $equipmentService
    ): RedirectResponse {
        $this->authorizeCreatureOwner($request, $creature);

        $equipmentService->unequip($creature, $itemInstance);

        return back()->with('status', 'Предмет снят в инвентарь сущности.');
    }

    private function authorizeCreatureOwner(Request $request, Creature $creature): void
    {
        abort_unless($creature->user_id === $request->user()->id, 404);
    }
}
