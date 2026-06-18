<?php

namespace App\Services;

use App\Models\BattleArena;

class BattleArenaService
{
    public function selectForSeed(int $seed): BattleArena
    {
        $arenas = BattleArena::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($arenas->isEmpty()) {
            return BattleArena::query()->create([
                'name' => 'Стальная цитадель',
                'code' => 'steel-citadel',
                'description' => 'Универсальная индустриальная арена.',
                'background_image' => 'game-assets/arena/industrial-fantasy-arena.webp',
                'special_effects' => [],
                'is_active' => true,
            ]);
        }

        $index = abs(crc32('battle-arena|'.$seed)) % $arenas->count();

        return $arenas->get($index);
    }

    /**
     * @return array<string, int>
     */
    public function normalizedEffects(?array $effects): array
    {
        return collect(BattleArena::specialOptions())
            ->keys()
            ->mapWithKeys(function (string $attribute) use ($effects): array {
                $value = $effects[$attribute] ?? 0;

                return [$attribute => is_numeric($value) ? max(-10, min(10, (int) $value)) : 0];
            })
            ->filter(fn (int $value): bool => $value !== 0)
            ->all();
    }

    /**
     * @param  array<string, int>  $values
     * @param  array<string, int>|null  $effects
     * @return array<string, int>
     */
    public function applyEffects(array $values, ?array $effects): array
    {
        foreach ($this->normalizedEffects($effects) as $attribute => $modifier) {
            $values[$attribute] = max(1, (int) ($values[$attribute] ?? 1) + $modifier);
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(BattleArena $arena): array
    {
        return [
            'battle_arena_id' => $arena->id,
            'arena_name' => $arena->name,
            'arena_background_image' => $arena->background_image,
            'arena_effects' => $this->normalizedEffects($arena->special_effects),
        ];
    }
}
