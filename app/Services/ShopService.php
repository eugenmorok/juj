<?php

namespace App\Services;

use App\Models\ArenaSetting;
use App\Models\Creature;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopService
{
    public const INVENTORY_SLOT_BASE_COST = 100;

    public const INVENTORY_SLOT_STEP_COST = 25;

    public const MAX_PURCHASED_INVENTORY_SLOTS = 50;

    public const ITEM_SELL_RATE = 0.5;

    public const SERVICE_PRICES = [
        'rename_creature' => 25,
        'reset_skills' => 120,
        'reset_special' => 180,
    ];

    public static function inventorySlotCost(User $user): int
    {
        $settings = ArenaSetting::current();

        return $settings->inventory_slot_base_cost + ($user->purchasedInventorySlots() * $settings->inventory_slot_step_cost);
    }

    public function buyItem(User $user, Item $item): ItemInstance
    {
        return DB::transaction(function () use ($user, $item): ItemInstance {
            $lockedUser = $this->lockedUser($user);
            $item->refresh();
            $inventory = Inventory::forUser($lockedUser);

            $this->ensureItemCanBePurchased($lockedUser, $item);
            $this->ensureEnoughTokens($lockedUser, $item->price);
            $this->ensureInventoryHasSpace($inventory);
            $this->ensureUniqueItemLimit($lockedUser, $item);

            $lockedUser->forceFill([
                'tokens' => $lockedUser->tokens - $item->price,
            ])->save();

            $itemInstance = ItemInstance::query()->create([
                'item_id' => $item->id,
                'owner_user_id' => $lockedUser->id,
                'bound_creature_id' => null,
                'durability' => $item->isConsumable() ? $item->initialUses() : 100,
                'state' => 'stored',
            ]);

            $inventory->addItemInstance($itemInstance);

            return $itemInstance;
        });
    }

    public function sellInventoryItem(User $user, InventoryItem $inventoryItem): int
    {
        return DB::transaction(function () use ($user, $inventoryItem): int {
            $lockedUser = $this->lockedUser($user);
            $storedInventoryItem = InventoryItem::query()
                ->whereKey($inventoryItem->id)
                ->lockForUpdate()
                ->firstOrFail()
                ->load([
                    'inventory.creature',
                    'itemInstance.item',
                    'itemInstance.equipmentRows',
                ]);

            $this->ensureInventoryItemBelongsToUser($lockedUser, $storedInventoryItem);
            $this->ensureInventoryItemCanBeSold($storedInventoryItem);

            $sellPrice = self::sellPrice($storedInventoryItem->itemInstance->item);

            $storedInventoryItem->delete();
            $storedInventoryItem->itemInstance->forceFill([
                'bound_creature_id' => null,
                'state' => 'sold',
            ])->save();

            $lockedUser->forceFill([
                'tokens' => $lockedUser->tokens + $sellPrice,
            ])->save();

            return $sellPrice;
        });
    }

    public function grantTokens(User $user, int $amount): User
    {
        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => 'Сумма выдачи должна быть больше нуля.',
            ]);
        }

        return DB::transaction(function () use ($user, $amount): User {
            $lockedUser = $this->lockedUser($user);
            $lockedUser->forceFill([
                'tokens' => $lockedUser->tokens + $amount,
            ])->save();

            return $lockedUser->refresh();
        });
    }

    /**
     * @return Collection<int, ItemInstance>
     */
    public function grantItem(User $user, Item $item, int $quantity = 1): Collection
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Количество предметов должно быть больше нуля.',
            ]);
        }

        return DB::transaction(function () use ($user, $item, $quantity): Collection {
            $lockedUser = $this->lockedUser($user);
            $item->refresh();
            $inventory = Inventory::forUser($lockedUser);

            $this->ensureInventoryHasSlots($inventory, $quantity);

            if ($item->is_unique && $quantity > 1) {
                throw ValidationException::withMessages([
                    'item' => 'Уникальный предмет можно выдать только в одном экземпляре.',
                ]);
            }

            $this->ensureUniqueItemLimit($lockedUser, $item);

            $instances = collect();

            for ($i = 0; $i < $quantity; $i++) {
                $itemInstance = ItemInstance::query()->create([
                    'item_id' => $item->id,
                    'owner_user_id' => $lockedUser->id,
                    'bound_creature_id' => null,
                    'durability' => $item->isConsumable() ? $item->initialUses() : 100,
                    'state' => 'stored',
                ]);

                $inventory->addItemInstance($itemInstance);
                $instances->push($itemInstance);
            }

            return $instances;
        });
    }

    public function buyInventorySlot(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            $lockedUser = $this->lockedUser($user);
            $settings = ArenaSetting::current();
            $purchasedSlots = $lockedUser->purchasedInventorySlots();

            if ($purchasedSlots >= $settings->max_purchased_inventory_slots) {
                throw ValidationException::withMessages([
                    'inventory' => 'Достигнут лимит купленных ячеек инвентаря.',
                ]);
            }

            $cost = self::inventorySlotCost($lockedUser);
            $this->ensureEnoughTokens($lockedUser, $cost);

            $lockedUser->forceFill([
                'tokens' => $lockedUser->tokens - $cost,
                'inventory_slots' => $lockedUser->inventory_slots + 1,
            ])->save();

            Inventory::forUser($lockedUser->refresh())->syncSlots();

            return $cost;
        });
    }

    public static function sellPrice(Item $item): int
    {
        return max(0, (int) floor($item->price * self::ITEM_SELL_RATE));
    }

    public function renameCreature(User $user, Creature $creature, string $name): void
    {
        DB::transaction(function () use ($user, $creature, $name): void {
            $lockedUser = $this->lockedUser($user);
            $this->ensureCreatureOwner($lockedUser, $creature);
            $this->ensureEnoughTokens($lockedUser, self::SERVICE_PRICES['rename_creature']);

            $lockedUser->forceFill([
                'tokens' => $lockedUser->tokens - self::SERVICE_PRICES['rename_creature'],
            ])->save();

            $creature->forceFill(['name' => $name])->save();
        });
    }

    public function resetSkills(User $user, Creature $creature): int
    {
        return DB::transaction(function () use ($user, $creature): int {
            $lockedUser = $this->lockedUser($user);
            $this->ensureCreatureOwner($lockedUser, $creature);
            $this->ensureCreatureIdle($creature);
            $this->ensureEnoughTokens($lockedUser, self::SERVICE_PRICES['reset_skills']);

            $creature->load('skills');

            if ($creature->skills->isEmpty()) {
                throw ValidationException::withMessages([
                    'service' => 'У сущности нет навыков для сброса.',
                ]);
            }

            $refund = $creature->skills->sum(fn ($skill): int => (int) $skill->pivot->cost_paid);

            $lockedUser->forceFill([
                'tokens' => $lockedUser->tokens - self::SERVICE_PRICES['reset_skills'],
            ])->save();

            $creature->skills()->detach();
            $creature->increment('development_points', $refund);

            return $refund;
        });
    }

    public function resetSpecial(User $user, Creature $creature): int
    {
        return DB::transaction(function () use ($user, $creature): int {
            $lockedUser = $this->lockedUser($user);
            $this->ensureCreatureOwner($lockedUser, $creature);
            $this->ensureCreatureIdle($creature);
            $this->ensureEnoughTokens($lockedUser, self::SERVICE_PRICES['reset_special']);

            $creature->load('species');
            $refund = $creature->spentCreationPoints($creature->species);

            if ($refund <= 0) {
                throw ValidationException::withMessages([
                    'service' => 'У сущности нет распределенных очков для сброса.',
                ]);
            }

            $special = [];
            foreach (Creature::SPECIAL_ATTRIBUTES as $attribute) {
                $special[$attribute] = $creature->species->baseSpecialValue($attribute);
            }

            $maxHp = Creature::maxHpForEndurance($special['endurance']);

            $lockedUser->forceFill([
                'tokens' => $lockedUser->tokens - self::SERVICE_PRICES['reset_special'],
            ])->save();

            $creature->forceFill([
                ...$special,
                'max_hp' => $maxHp,
                'current_hp' => min($creature->current_hp, $maxHp),
                'development_points' => $creature->development_points + $refund,
            ])->save();

            return $refund;
        });
    }

    private function lockedUser(User $user): User
    {
        return User::query()
            ->whereKey($user->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureItemCanBePurchased(User $user, Item $item): void
    {
        if (! $item->canBePurchasedBy($user)) {
            throw ValidationException::withMessages([
                'item' => 'Предмет недоступен для покупки на текущем уровне игрока.',
            ]);
        }
    }

    private function ensureEnoughTokens(User $user, int $cost): void
    {
        if ($user->tokens < $cost) {
            throw ValidationException::withMessages([
                'tokens' => 'Недостаточно токенов.',
            ]);
        }
    }

    private function ensureInventoryHasSpace(Inventory $inventory): void
    {
        if (! $inventory->hasFreeSlot()) {
            throw ValidationException::withMessages([
                'inventory' => 'В общем инвентаре нет свободных ячеек.',
            ]);
        }
    }

    private function ensureInventoryHasSlots(Inventory $inventory, int $quantity): void
    {
        if ($inventory->freeSlots() < $quantity) {
            throw ValidationException::withMessages([
                'inventory' => 'В общем инвентаре нет нужного количества свободных ячеек.',
            ]);
        }
    }

    private function ensureUniqueItemLimit(User $user, Item $item): void
    {
        if (! $item->is_unique) {
            return;
        }

        $alreadyOwned = ItemInstance::query()
            ->where('owner_user_id', $user->id)
            ->where('item_id', $item->id)
            ->whereNotIn('state', ['deleted', 'sold', 'used'])
            ->exists();

        if ($alreadyOwned) {
            throw ValidationException::withMessages([
                'item' => 'Уникальный предмет уже есть у игрока.',
            ]);
        }
    }

    private function ensureCreatureOwner(User $user, Creature $creature): void
    {
        abort_unless($creature->user_id === $user->id, 404);
    }

    private function ensureCreatureIdle(Creature $creature): void
    {
        if (! $creature->is_available_for_battle) {
            throw ValidationException::withMessages([
                'service' => 'Услуга недоступна, пока сущность находится в бою.',
            ]);
        }
    }

    private function ensureInventoryItemBelongsToUser(User $user, InventoryItem $inventoryItem): void
    {
        abort_unless($inventoryItem->inventory?->owner_user_id === $user->id, 404);
        abort_unless($inventoryItem->itemInstance?->owner_user_id === $user->id, 404);
    }

    private function ensureInventoryItemCanBeSold(InventoryItem $inventoryItem): void
    {
        $inventory = $inventoryItem->inventory;
        $itemInstance = $inventoryItem->itemInstance;

        if (! $itemInstance?->item) {
            throw ValidationException::withMessages([
                'item' => 'Предмет не найден в каталоге.',
            ]);
        }

        if ($inventory?->creature && ! $inventory->creature->is_available_for_battle) {
            throw ValidationException::withMessages([
                'inventory' => 'Нельзя продавать предметы сущности, которая находится в бою.',
            ]);
        }

        if ($itemInstance->state !== 'stored' || $itemInstance->equipmentRows->isNotEmpty()) {
            throw ValidationException::withMessages([
                'item' => 'Можно продавать только предметы, лежащие в инвентаре.',
            ]);
        }
    }
}
