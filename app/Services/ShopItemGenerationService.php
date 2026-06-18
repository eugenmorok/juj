<?php

namespace App\Services;

use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\EquipmentSlot;
use App\Models\Item;
use App\Models\ShopGenerationState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopItemGenerationService
{
    public const COOLDOWN_HOURS = 3;

    public const MIN_ITEMS_PER_BATCH = 2;

    public const MAX_ITEMS_PER_BATCH = 3;

    /**
     * @return Collection<int, Item>
     */
    public function generateIfDue(): Collection
    {
        return DB::transaction(function (): Collection {
            ShopGenerationState::query()->insertOrIgnore([
                'id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $state = ShopGenerationState::query()
                ->whereKey(1)
                ->lockForUpdate()
                ->firstOrFail();

            if ($state->last_generated_at?->isAfter(now()->subHours(self::COOLDOWN_HOURS))) {
                return collect();
            }

            $items = collect();
            $count = random_int(self::MIN_ITEMS_PER_BATCH, self::MAX_ITEMS_PER_BATCH);

            for ($index = 0; $index < $count; $index++) {
                $items->push($this->generateItem());
            }

            $state->forceFill(['last_generated_at' => now()])->save();

            return $items;
        });
    }

    private function generateItem(): Item
    {
        $slot = EquipmentSlot::query()->active()->inRandomOrder()->first();
        $equipmentTypes = $slot ? ['equipment', 'module', 'artifact'] : [];
        $itemType = collect([...$equipmentTypes, 'potion', 'consumable'])->random();
        $rarity = $this->rarity();
        $requiredLevel = match ($rarity) {
            'unique' => random_int(3, 5),
            'elite' => random_int(2, 4),
            'rare' => random_int(1, 3),
            default => 1,
        };
        [$allowedTypes, $allowedSpecies] = $this->restrictions();
        $bonuses = $this->bonuses($itemType, $rarity);
        $isConsumable = in_array($itemType, ['potion', 'consumable'], true);
        $name = $this->name($itemType, $rarity);

        return Item::query()->create([
            'name' => $name,
            'code' => 'generated-'.now()->format('YmdHis').'-'.Str::lower(Str::random(8)),
            'description' => $this->description($itemType, $rarity),
            'item_type' => $itemType,
            'rarity' => $rarity,
            'price' => $this->price($rarity, $bonuses),
            'required_level' => $requiredLevel,
            'allowed_types' => $allowedTypes,
            'allowed_species' => $allowedSpecies,
            'slot_key' => $isConsumable ? null : $slot?->code,
            'slots_required' => $isConsumable || ! $slot ? null : [$slot->code],
            'bonuses' => $bonuses,
            'duration_type' => $isConsumable ? 'consumable' : 'permanent',
            'uses_count' => $isConsumable ? random_int(1, 3) : null,
            'is_unique' => $rarity === 'unique',
            'is_active' => true,
            'is_generated' => true,
            'generated_at' => now(),
        ]);
    }

    /**
     * @return array{0: list<int>|null, 1: list<int>|null}
     */
    private function restrictions(): array
    {
        if (random_int(1, 100) > 55) {
            return [null, null];
        }

        $type = CreatureType::query()->active()->inRandomOrder()->first();

        if (! $type) {
            return [null, null];
        }

        if (random_int(1, 100) <= 30) {
            $species = CreatureSpecies::query()
                ->active()
                ->where('creature_type_id', $type->id)
                ->inRandomOrder()
                ->first();

            if ($species) {
                return [[$type->id], [$species->id]];
            }
        }

        return [[$type->id], null];
    }

    /**
     * @return array<string, int>
     */
    private function bonuses(string $itemType, string $rarity): array
    {
        $value = match ($rarity) {
            'unique' => 4,
            'elite' => 3,
            'rare' => 2,
            default => 1,
        };

        if ($itemType === 'potion') {
            return ['heal' => 20 + ($value * 15)];
        }

        $attributes = ['strength', 'perception', 'endurance', 'charisma', 'intelligence', 'agility', 'luck'];
        $first = $attributes[array_rand($attributes)];
        $bonuses = [$first => $value];

        if (in_array($rarity, ['elite', 'unique'], true)) {
            $second = collect($attributes)->reject(fn (string $attribute): bool => $attribute === $first)->random();
            $bonuses[$second] = max(1, $value - 1);
        }

        return $bonuses;
    }

    private function rarity(): string
    {
        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 55 => 'common',
            $roll <= 82 => 'rare',
            $roll <= 96 => 'elite',
            default => 'unique',
        };
    }

    /**
     * @param  array<string, int>  $bonuses
     */
    private function price(string $rarity, array $bonuses): int
    {
        $base = match ($rarity) {
            'unique' => 420,
            'elite' => 220,
            'rare' => 110,
            default => 45,
        };

        return $base + (array_sum(array_map('abs', $bonuses)) * 10) + random_int(0, 30);
    }

    private function name(string $itemType, string $rarity): string
    {
        $prefix = collect([
            'common' => ['Полевой', 'Укреплённый', 'Надёжный'],
            'rare' => ['Редкий', 'Закалённый', 'Резонансный'],
            'elite' => ['Элитный', 'Штурмовой', 'Сингулярный'],
            'unique' => ['Древний', 'Легендарный', 'Неповторимый'],
        ][$rarity])->random();

        $noun = collect([
            'equipment' => ['панцирь', 'клинок', 'комплект'],
            'module' => ['модуль', 'процессор', 'усилитель'],
            'artifact' => ['артефакт', 'талисман', 'осколок'],
            'potion' => ['эликсир', 'сыворотка', 'настой'],
            'consumable' => ['стимулятор', 'катализатор', 'концентрат'],
        ][$itemType])->random();

        return $prefix.' '.$noun;
    }

    private function description(string $itemType, string $rarity): string
    {
        $type = Item::TYPES[$itemType] ?? $itemType;
        $rarityLabel = Item::RARITIES[$rarity] ?? $rarity;

        return "{$rarityLabel} предмет категории «{$type}», созданный торговой сетью арены.";
    }
}
