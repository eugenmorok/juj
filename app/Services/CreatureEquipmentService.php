<?php

namespace App\Services;

use App\Models\Creature;
use App\Models\CreatureEquipment;
use App\Models\EquipmentSlot;
use App\Models\InventoryItem;
use App\Models\ItemInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatureEquipmentService
{
    /**
     * @return list<string>
     */
    public function equip(Creature $creature, InventoryItem $inventoryItem): array
    {
        $inventoryItem->loadMissing([
            'inventory.creature',
            'itemInstance.item',
        ]);

        $itemInstance = $inventoryItem->itemInstance;
        $item = $itemInstance->item;

        if ($inventoryItem->inventory->owner_user_id !== $creature->user_id) {
            abort(404);
        }

        if ($inventoryItem->inventory->creature_id !== null && $inventoryItem->inventory->creature_id !== $creature->id) {
            throw ValidationException::withMessages([
                'equipment' => 'Предмет должен быть в общем инвентаре игрока или инвентаре этой сущности.',
            ]);
        }

        $this->ensureCreatureCanChangeEquipment($creature);

        if (! $item->isEquipment()) {
            throw ValidationException::withMessages([
                'equipment' => 'Этот предмет нельзя экипировать.',
            ]);
        }

        if (! $item->canBeUsedBy($creature)) {
            throw ValidationException::withMessages([
                'equipment' => 'Предмет не подходит этой сущности по типу, виду или уровню.',
            ]);
        }

        $slotKeys = $item->equipmentSlotKeys();

        if ($slotKeys === []) {
            throw ValidationException::withMessages([
                'equipment' => 'У предмета не задан слот экипировки.',
            ]);
        }

        $this->ensureSlotsExist($slotKeys);
        $this->ensureSlotsAreFree($creature, $slotKeys);
        $this->ensureItemInstanceIsNotEquipped($itemInstance);
        $this->ensureUniqueItemIsNotDuplicated($creature, $itemInstance);

        DB::transaction(function () use ($creature, $inventoryItem, $itemInstance, $slotKeys): void {
            $inventoryItem->delete();

            foreach ($slotKeys as $slotKey) {
                CreatureEquipment::query()->create([
                    'creature_id' => $creature->id,
                    'item_instance_id' => $itemInstance->id,
                    'slot_key' => $slotKey,
                ]);
            }

            $itemInstance->forceFill([
                'owner_user_id' => $creature->user_id,
                'bound_creature_id' => $creature->id,
                'state' => 'equipped',
            ])->save();
        });

        return $slotKeys;
    }

    public function unequip(Creature $creature, ItemInstance $itemInstance): InventoryItem
    {
        $itemInstance->loadMissing('equipmentRows');

        if ($itemInstance->owner_user_id !== $creature->user_id || $itemInstance->bound_creature_id !== $creature->id) {
            abort(404);
        }

        $this->ensureCreatureCanChangeEquipment($creature);

        if (! $itemInstance->equipmentRows()->where('creature_id', $creature->id)->exists()) {
            throw ValidationException::withMessages([
                'equipment' => 'Предмет не экипирован этой сущностью.',
            ]);
        }

        $targetInventory = $creature->ensureInventory();

        if (! $targetInventory->hasFreeSlot()) {
            throw ValidationException::withMessages([
                'equipment' => 'В инвентаре сущности нет свободной ячейки для снятого предмета.',
            ]);
        }

        return DB::transaction(function () use ($creature, $itemInstance, $targetInventory): InventoryItem {
            CreatureEquipment::query()
                ->where('creature_id', $creature->id)
                ->where('item_instance_id', $itemInstance->id)
                ->delete();

            return $targetInventory->addItemInstance($itemInstance);
        });
    }

    private function ensureCreatureCanChangeEquipment(Creature $creature): void
    {
        if (! $creature->is_available_for_battle) {
            throw ValidationException::withMessages([
                'equipment' => 'Нельзя менять экипировку сущности, которая находится в бою.',
            ]);
        }
    }

    /**
     * @param  list<string>  $slotKeys
     */
    private function ensureSlotsExist(array $slotKeys): void
    {
        $existingCount = EquipmentSlot::query()
            ->active()
            ->whereIn('code', $slotKeys)
            ->count();

        if ($existingCount !== count($slotKeys)) {
            throw ValidationException::withMessages([
                'equipment' => 'Один из слотов экипировки недоступен.',
            ]);
        }
    }

    /**
     * @param  list<string>  $slotKeys
     */
    private function ensureSlotsAreFree(Creature $creature, array $slotKeys): void
    {
        $occupiedSlot = $creature->equipmentRows()
            ->whereIn('slot_key', $slotKeys)
            ->exists();

        if ($occupiedSlot) {
            throw ValidationException::withMessages([
                'equipment' => 'Один из нужных слотов уже занят.',
            ]);
        }
    }

    private function ensureItemInstanceIsNotEquipped(ItemInstance $itemInstance): void
    {
        if ($itemInstance->equipmentRows()->exists() || $itemInstance->state === 'equipped') {
            throw ValidationException::withMessages([
                'equipment' => 'Этот экземпляр предмета уже экипирован.',
            ]);
        }
    }

    private function ensureUniqueItemIsNotDuplicated(Creature $creature, ItemInstance $itemInstance): void
    {
        if (! $itemInstance->item?->is_unique) {
            return;
        }

        $hasSameUniqueItem = $creature->equipmentRows()
            ->whereHas('itemInstance', function ($query) use ($itemInstance): void {
                $query->where('item_id', $itemInstance->item_id);
            })
            ->exists();

        if ($hasSameUniqueItem) {
            throw ValidationException::withMessages([
                'equipment' => 'Уникальный предмет этого типа уже экипирован.',
            ]);
        }
    }
}
