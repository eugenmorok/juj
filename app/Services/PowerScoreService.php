<?php

namespace App\Services;

use App\Models\ArenaSetting;
use App\Models\Creature;
use App\Models\ItemInstance;

class PowerScoreService
{
    public function calculate(Creature $creature, ?ArenaSetting $settings = null): int
    {
        $settings ??= ArenaSetting::current();
        $creature->loadMissing([
            'user',
            'skills',
            'equipmentRows.itemInstance.item',
        ]);

        $specialScore = array_sum($creature->effectiveSpecialValues($settings))
            + (array_sum($creature->user?->battleSupportBonus() ?? []) * 1.5);
        $levelScore = $creature->level * $settings->power_score_level_weight;
        $skillScore = $creature->skills->sum(
            fn ($skill): int => (int) ($skill->pivot?->cost_paid ?: $skill->cost)
        ) * $settings->power_score_skill_weight;
        $equipmentMasteryMultiplier = $creature->user?->equipmentCombatBonusMultiplier() ?? 1.0;
        $equipmentScore = $creature->equipmentRows
            ->pluck('itemInstance')
            ->filter()
            ->unique('id')
            ->sum(fn (ItemInstance $itemInstance): int => $this->itemInstanceScore($itemInstance)) * $equipmentMasteryMultiplier * $settings->power_score_equipment_weight;

        return max(1, (int) round($specialScore + $levelScore + $skillScore + $equipmentScore));
    }

    private function itemInstanceScore(ItemInstance $itemInstance): int
    {
        $item = $itemInstance->item;

        if (! $item) {
            return 0;
        }

        $rarityScore = match ($item->rarity) {
            'rare' => 8,
            'elite' => 15,
            'unique' => 25,
            default => 3,
        };

        $bonusScore = collect($item->bonuses ?? [])
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->sum(fn (mixed $value): int => abs((int) $value) * 4);

        return $rarityScore + $bonusScore + intdiv((int) $item->price, 20);
    }
}
