<?php

namespace App\Http\Controllers;

use App\Models\Creature;
use App\Models\Item;
use App\Services\ShopService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'item_type' => ['nullable', 'string', Rule::in(array_keys(Item::TYPES))],
            'rarity' => ['nullable', 'string', Rule::in(array_keys(Item::RARITIES))],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'level' => ['nullable', 'integer', 'min:1'],
            'available' => ['nullable', Rule::in(['1'])],
        ]);

        $user = $request->user();
        $playerInventory = $user->ensureInventory()->load('inventoryItems.itemInstance.item');
        $ownedUniqueItemIds = $this->ownedUniqueItemIds($user->id);

        $items = Item::query()
            ->active()
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $needle = '%'.mb_strtolower($search).'%';

                $query->where(fn ($items) => $items
                    ->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$needle]));
            })
            ->when($filters['item_type'] ?? null, fn ($query, string $type) => $query->where('item_type', $type))
            ->when($filters['rarity'] ?? null, fn ($query, string $rarity) => $query->where('rarity', $rarity))
            ->when($filters['max_price'] ?? null, fn ($query, int $price) => $query->where('price', '<=', $price))
            ->when($filters['level'] ?? null, fn ($query, int $level) => $query->where('required_level', '<=', $level))
            ->when(($filters['available'] ?? null) === '1', fn ($query) => $query
                ->where('required_level', '<=', $user->level)
                ->where('price', '<=', $user->tokens))
            ->orderBy('price')
            ->orderBy('required_level')
            ->orderBy('name')
            ->get();

        return view('game.shop', [
            'user' => $user,
            'items' => $items,
            'playerInventory' => $playerInventory,
            'ownedUniqueItemIds' => $ownedUniqueItemIds,
            'filters' => $filters,
            'inventorySlotCost' => ShopService::inventorySlotCost($user),
            'servicePrices' => ShopService::SERVICE_PRICES,
            'creatures' => $user->creatures()
                ->with(['type', 'species', 'skills'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function buyItem(Request $request, Item $item, ShopService $shop): RedirectResponse
    {
        $shop->buyItem($request->user(), $item);

        return back()->with('status', 'Предмет куплен и помещен в общий инвентарь.');
    }

    public function buyInventorySlot(Request $request, ShopService $shop): RedirectResponse
    {
        $cost = $shop->buyInventorySlot($request->user());

        return back()->with('status', 'Инвентарь расширен. Списано токенов: '.$cost.'.');
    }

    public function renameCreature(Request $request, ShopService $shop): RedirectResponse
    {
        $attributes = $request->validate([
            'creature_id' => ['required', 'integer', 'exists:creatures,id'],
            'name' => ['required', 'string', 'min:2', 'max:64'],
        ]);

        $creature = $this->ownedCreature($request, (int) $attributes['creature_id']);
        $shop->renameCreature($request->user(), $creature, $attributes['name']);

        return back()->with('status', 'Имя сущности изменено.');
    }

    public function resetSkills(Request $request, ShopService $shop): RedirectResponse
    {
        $attributes = $request->validate([
            'creature_id' => ['required', 'integer', 'exists:creatures,id'],
        ]);

        $creature = $this->ownedCreature($request, (int) $attributes['creature_id']);
        $refund = $shop->resetSkills($request->user(), $creature);

        return back()->with('status', 'Навыки сброшены. Возвращено очков развития: '.$refund.'.');
    }

    public function resetSpecial(Request $request, ShopService $shop): RedirectResponse
    {
        $attributes = $request->validate([
            'creature_id' => ['required', 'integer', 'exists:creatures,id'],
        ]);

        $creature = $this->ownedCreature($request, (int) $attributes['creature_id']);
        $refund = $shop->resetSpecial($request->user(), $creature);

        return back()->with('status', 'Характеристики сброшены до базы вида. Возвращено очков развития: '.$refund.'.');
    }

    /**
     * @return list<int>
     */
    private function ownedUniqueItemIds(int $userId): array
    {
        return Item::query()
            ->where('is_unique', true)
            ->whereHas('instances', fn ($query) => $query
                ->where('owner_user_id', $userId)
                ->whereNotIn('state', ['deleted', 'used']))
            ->pluck('id')
            ->map(fn (mixed $itemId): int => (int) $itemId)
            ->all();
    }

    private function ownedCreature(Request $request, int $creatureId): Creature
    {
        return Creature::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($creatureId);
    }
}
