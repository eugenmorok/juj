<?php

namespace App\Services;

use App\Models\Creature;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\ItemInstance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumableService
{
    /**
     * @return array{message: string, heal: int, max_hp: int, special: array<string, int>, uses_remaining: int}
     */
    public function useOnCreature(User $user, Creature $creature, InventoryItem $inventoryItem): array
    {
        return DB::transaction(function () use ($user, $creature, $inventoryItem): array {
            $lockedCreature = Creature::query()
                ->whereKey($creature->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedCreature->user_id === $user->id, 404);

            $lockedInventoryItem = InventoryItem::query()
                ->whereKey($inventoryItem->id)
                ->lockForUpdate()
                ->with([
                    'inventory.creature',
                    'itemInstance.item',
                ])
                ->firstOrFail();

            $inventory = $lockedInventoryItem->inventory;
            $itemInstance = $lockedInventoryItem->itemInstance;
            $item = $itemInstance?->item;

            abort_unless($inventory && $itemInstance && $item, 404);
            abort_unless($inventory->owner_user_id === $user->id, 404);
            abort_unless($itemInstance->owner_user_id === $user->id, 404);

            if ($inventory->inventory_type === Inventory::TYPE_CREATURE && $inventory->creature_id !== $lockedCreature->id) {
                throw ValidationException::withMessages([
                    'inventory' => 'Предмет из инвентаря сущности можно применить только к этой сущности.',
                ]);
            }

            $sourceCreature = $inventory->creature;
            if ($sourceCreature && ! $sourceCreature->is_available_for_battle) {
                throw ValidationException::withMessages([
                    'inventory' => 'Нельзя применять предметы сущности, которая находится в бою.',
                ]);
            }

            if (! $lockedCreature->is_available_for_battle) {
                throw ValidationException::withMessages([
                    'inventory' => 'Нельзя применять предметы к сущности, которая находится в бою.',
                ]);
            }

            if (! $item->isConsumable()) {
                throw ValidationException::withMessages([
                    'item' => 'Этот предмет нельзя применить как расходник.',
                ]);
            }

            if ($itemInstance->state !== 'stored' || $itemInstance->remainingUses() <= 0) {
                throw ValidationException::withMessages([
                    'item' => 'У предмета не осталось применений.',
                ]);
            }

            if (! $item->canBeUsedBy($lockedCreature)) {
                throw ValidationException::withMessages([
                    'item' => 'Предмет недоступен для этой сущности.',
                ]);
            }

            $effect = $this->applyEffects($lockedCreature, $item->bonuses ?? []);

            if (! $effect['changed']) {
                throw ValidationException::withMessages([
                    'item' => 'Предмет сейчас не даст эффекта.',
                ]);
            }

            $usesRemaining = $this->consumeUse($itemInstance, $lockedInventoryItem);

            return [
                'message' => $this->messageFor($item->name, $effect),
                'heal' => $effect['heal'],
                'max_hp' => $effect['max_hp'],
                'special' => $effect['special'],
                'uses_remaining' => $usesRemaining,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $bonuses
     * @return array{changed: bool, heal: int, max_hp: int, special: array<string, int>}
     */
    private function applyEffects(Creature $creature, array $bonuses): array
    {
        $specialChanges = [];
        $maxHpIncrease = $this->positiveInt($bonuses['hp'] ?? 0)
            + $this->positiveInt($bonuses['max_hp'] ?? 0)
            + $this->positiveInt($bonuses['hp_max'] ?? 0);

        $oldEndurance = (int) $creature->endurance;

        foreach (Creature::SPECIAL_ATTRIBUTES as $attribute) {
            $increase = $this->positiveInt($bonuses[$attribute] ?? 0);

            if ($increase <= 0) {
                continue;
            }

            $creature->{$attribute} = (int) $creature->{$attribute} + $increase;
            $specialChanges[$attribute] = $increase;
        }

        if (isset($specialChanges['endurance'])) {
            $newEndurance = (int) $creature->endurance;
            $maxHpIncrease += max(
                0,
                Creature::maxHpForEndurance($newEndurance) - Creature::maxHpForEndurance($oldEndurance)
            );
        }

        if ($maxHpIncrease > 0) {
            $creature->max_hp = (int) $creature->max_hp + $maxHpIncrease;
            $creature->current_hp = (int) $creature->current_hp + $maxHpIncrease;
        }

        $heal = $this->positiveInt($bonuses['heal'] ?? 0)
            + $this->positiveInt($bonuses['hp_restore'] ?? 0);

        $healed = 0;
        if ($heal > 0) {
            $beforeHp = (int) $creature->current_hp;
            $creature->current_hp = min($creature->effectiveMaxHp(), $beforeHp + $heal);
            $healed = max(0, (int) $creature->current_hp - $beforeHp);
        }

        $changed = $healed > 0 || $maxHpIncrease > 0 || $specialChanges !== [];

        if ($changed) {
            $creature->current_hp = min((int) $creature->current_hp, $creature->effectiveMaxHp());
            $creature->save();
        }

        return [
            'changed' => $changed,
            'heal' => $healed,
            'max_hp' => $maxHpIncrease,
            'special' => $specialChanges,
        ];
    }

    private function consumeUse(ItemInstance $itemInstance, InventoryItem $inventoryItem): int
    {
        $usesRemaining = max(0, $itemInstance->remainingUses() - 1);

        if ($usesRemaining === 0) {
            $inventoryItem->delete();
            $itemInstance->forceFill([
                'bound_creature_id' => null,
                'durability' => 0,
                'state' => 'used',
            ])->save();

            return 0;
        }

        $itemInstance->forceFill([
            'durability' => $usesRemaining,
        ])->save();

        return $usesRemaining;
    }

    /**
     * @param  array{heal: int, max_hp: int, special: array<string, int>}  $effect
     */
    private function messageFor(string $itemName, array $effect): string
    {
        $parts = [];

        if ($effect['heal'] > 0) {
            $parts[] = "восстановлено {$effect['heal']} HP";
        }

        if ($effect['max_hp'] > 0) {
            $parts[] = "+{$effect['max_hp']} max HP";
        }

        foreach ($effect['special'] as $attribute => $increase) {
            $label = Creature::SPECIAL_LABELS[$attribute] ?? $attribute;
            $parts[] = "+{$increase} {$label}";
        }

        return $itemName.' применен: '.implode(', ', $parts).'.';
    }

    private function positiveInt(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }
}
