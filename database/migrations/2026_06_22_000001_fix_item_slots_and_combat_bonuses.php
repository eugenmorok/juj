<?php

use App\Models\EquipmentSlot;
use App\Models\Item;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('items')) {
            return;
        }

        $this->ensureSecondaryWeaponSlot();
        $this->ensureAuxiliaryWeaponItem();
        $this->normalizeItems();
    }

    public function down(): void
    {
        // Data repair migration: intentionally not reversible.
    }

    private function ensureSecondaryWeaponSlot(): void
    {
        if (! Schema::hasTable('equipment_slots')) {
            return;
        }

        EquipmentSlot::query()->updateOrCreate([
            'code' => 'secondary-weapon',
        ], [
            'name' => 'Дополнительное оружие / хвост / модуль',
            'description' => 'Вспомогательное вооружение, хвостовые системы и дополнительные боевые модули.',
            'sort_order' => 60,
            'is_active' => true,
        ]);
    }

    private function ensureAuxiliaryWeaponItem(): void
    {
        Item::query()->updateOrCreate([
            'code' => 'auxiliary-cutter',
        ], [
            'name' => 'Дополнительный резак',
            'icon' => 'game-assets/shop/pulse-cutter.webp',
            'description' => 'Вспомогательное оружие для хвоста, манипулятора или боевого модуля.',
            'item_type' => 'equipment',
            'rarity' => 'common',
            'price' => 90,
            'required_level' => 1,
            'allowed_types' => null,
            'allowed_species' => null,
            'slot_key' => 'secondary-weapon',
            'slots_required' => ['secondary-weapon'],
            'bonuses' => ['agility' => 1, 'damage' => 2],
            'duration_type' => 'permanent',
            'uses_count' => null,
            'is_unique' => false,
            'is_active' => true,
        ]);
    }

    private function normalizeItems(): void
    {
        Item::query()
            ->orderBy('id')
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    $slotKeys = Item::normalizeSlotKeys($item->slots_required ?? []);
                    $slotKey = Item::normalizeSlotKey($item->slot_key);

                    if ($slotKeys === [] && $slotKey) {
                        $slotKeys = [$slotKey];
                    }

                    $primarySlotKey = $slotKey ?: ($slotKeys[0] ?? null);
                    $bonuses = is_array($item->bonuses) ? $item->bonuses : [];

                    if ($this->requiresDamage($slotKeys) && ! $this->hasAnyNumericBonus($bonuses, Item::DAMAGE_BONUS_KEYS)) {
                        $bonuses['damage'] = $this->defaultCombatBonus($item);
                    }

                    if ($this->requiresDefense($slotKeys) && ! $this->hasAnyNumericBonus($bonuses, Item::DEFENSE_BONUS_KEYS)) {
                        $bonuses['defense'] = $this->defaultCombatBonus($item);
                    }

                    if ($item->code === 'reinforced-hide-plate') {
                        $primarySlotKey = 'body';
                        $slotKeys = ['body'];
                        $bonuses['defense'] = max(2, (int) ($bonuses['defense'] ?? 0));
                    }

                    if ($item->code === 'venom-sting') {
                        $primarySlotKey = 'primary-weapon';
                        $slotKeys = ['primary-weapon'];
                        $bonuses['damage'] = max(3, (int) ($bonuses['damage'] ?? 0));
                        $bonuses['poison_damage'] = max(5, (int) ($bonuses['poison_damage'] ?? 0));
                    }

                    $item->forceFill([
                        'slot_key' => $primarySlotKey,
                        'slots_required' => $slotKeys === [] ? null : $slotKeys,
                        'bonuses' => $bonuses === [] ? null : $bonuses,
                    ])->save();
                }
            });
    }

    /**
     * @param  list<string>  $slotKeys
     */
    private function requiresDamage(array $slotKeys): bool
    {
        return array_intersect($slotKeys, Item::DAMAGE_SLOT_KEYS) !== [];
    }

    /**
     * @param  list<string>  $slotKeys
     */
    private function requiresDefense(array $slotKeys): bool
    {
        return array_intersect($slotKeys, Item::DEFENSE_SLOT_KEYS) !== [];
    }

    /**
     * @param  array<string, mixed>  $bonuses
     * @param  list<string>  $keys
     */
    private function hasAnyNumericBonus(array $bonuses, array $keys): bool
    {
        foreach ($keys as $key) {
            if (is_numeric($bonuses[$key] ?? null) && (int) $bonuses[$key] !== 0) {
                return true;
            }
        }

        return false;
    }

    private function defaultCombatBonus(Item $item): int
    {
        return match ($item->rarity) {
            'unique' => 6,
            'elite' => 5,
            'rare' => 4,
            default => 2,
        };
    }
};
