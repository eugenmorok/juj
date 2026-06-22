<?php

namespace App\Models;

use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'code',
    'description',
    'icon',
    'effect_image',
    'effect_sound',
    'item_type',
    'rarity',
    'price',
    'required_level',
    'allowed_types',
    'allowed_species',
    'slot_key',
    'slots_required',
    'bonuses',
    'duration_type',
    'uses_count',
    'is_unique',
    'is_active',
    'is_generated',
    'generated_at',
])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    public const TYPES = [
        'equipment' => 'Экипировка',
        'potion' => 'Зелье',
        'consumable' => 'Расходник',
        'module' => 'Модуль',
        'artifact' => 'Артефакт',
        'service' => 'Услуга',
    ];

    public const RARITIES = [
        'common' => 'Обычный',
        'rare' => 'Редкий',
        'elite' => 'Элитный',
        'unique' => 'Уникальный',
    ];

    public const DURATIONS = [
        'permanent' => 'Постоянный',
        'battle' => 'На один бой',
        'consumable' => 'Расходуется',
        'service' => 'Услуга',
    ];

    public const BONUS_LABELS = [
        'strength' => 'Сила',
        'perception' => 'Восприятие',
        'endurance' => 'Выносливость',
        'charisma' => 'Харизма',
        'intelligence' => 'Интеллект',
        'agility' => 'Ловкость',
        'luck' => 'Удача',
        'attack' => 'Урон',
        'damage' => 'Урон',
        'defense' => 'Защита',
        'armor' => 'Защита',
        'hp' => 'Макс. HP',
        'max_hp' => 'Макс. HP',
        'hp_max' => 'Макс. HP',
        'heal' => 'Лечение',
        'hp_restore' => 'Лечение',
        'crit_chance' => 'Шанс крита',
        'poison_damage' => 'Урон ядом',
    ];

    public const DAMAGE_BONUS_KEYS = ['damage', 'attack'];

    public const DEFENSE_BONUS_KEYS = ['defense', 'armor'];

    public const DAMAGE_SLOT_KEYS = ['primary-weapon', 'secondary-weapon', 'front-limbs'];

    public const DEFENSE_SLOT_KEYS = ['body', 'defense'];

    /**
     * @return BelongsTo<EquipmentSlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(EquipmentSlot::class, 'slot_key', 'code');
    }

    /**
     * @return HasMany<ItemInstance, $this>
     */
    public function instances(): HasMany
    {
        return $this->hasMany(ItemInstance::class);
    }

    public function isEquipment(): bool
    {
        return in_array($this->item_type, ['equipment', 'module', 'artifact'], true);
    }

    public function isConsumable(): bool
    {
        return in_array($this->item_type, ['potion', 'consumable'], true)
            || $this->duration_type === 'consumable';
    }

    public function initialUses(): int
    {
        return max(1, (int) ($this->uses_count ?? 1));
    }

    /**
     * @return list<string>
     */
    public function equipmentSlotKeys(): array
    {
        $slotKeys = collect(self::normalizeSlotKeys($this->slots_required ?? []));

        if ($slotKeys->isEmpty() && $this->slot_key) {
            $slotKeys->push(self::normalizeSlotKey($this->slot_key));
        }

        return $slotKeys
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function normalizeSlotKeys(mixed $slots): array
    {
        $singleSlotKey = self::normalizeSlotKey($slots);

        if ($singleSlotKey) {
            return [$singleSlotKey];
        }

        if (! is_iterable($slots)) {
            return [];
        }

        return collect($slots)
            ->map(fn (mixed $slot): ?string => self::normalizeSlotKey($slot))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function normalizeSlotKey(mixed $slot): ?string
    {
        if ($slot instanceof EquipmentSlot) {
            return $slot->code;
        }

        if (is_array($slot)) {
            $code = $slot['code'] ?? null;

            return is_string($code) && $code !== '' ? $code : null;
        }

        if (is_object($slot)) {
            $code = $slot->code ?? null;

            return is_string($code) && $code !== '' ? $code : null;
        }

        if (! is_string($slot)) {
            return null;
        }

        $slot = trim($slot);

        if ($slot === '') {
            return null;
        }

        $decoded = json_decode($slot, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $decodedSlot = self::normalizeSlotKey($decoded);

            if ($decodedSlot) {
                return $decodedSlot;
            }

            if (is_iterable($decoded)) {
                return collect($decoded)
                    ->map(fn (mixed $nestedSlot): ?string => self::normalizeSlotKey($nestedSlot))
                    ->filter()
                    ->first();
            }

            return null;
        }

        return $slot;
    }

    public function equipmentSlotSummary(): ?string
    {
        $slotKeys = $this->equipmentSlotKeys();

        if ($slotKeys === []) {
            return null;
        }

        $names = EquipmentSlot::query()
            ->whereIn('code', $slotKeys)
            ->pluck('name', 'code');

        return collect($slotKeys)
            ->map(fn (string $slotKey): string => $names[$slotKey] ?? $slotKey)
            ->join(', ');
    }

    public function requiresDamageBonus(): bool
    {
        return collect($this->equipmentSlotKeys())
            ->intersect(self::DAMAGE_SLOT_KEYS)
            ->isNotEmpty();
    }

    public function requiresDefenseBonus(): bool
    {
        return collect($this->equipmentSlotKeys())
            ->intersect(self::DEFENSE_SLOT_KEYS)
            ->isNotEmpty();
    }

    public function canBeUsedBy(Creature $creature): bool
    {
        if (! $this->is_active || $creature->level < $this->required_level) {
            return false;
        }

        $allowedTypes = array_map('intval', $this->allowed_types ?? []);
        $allowedSpecies = array_map('intval', $this->allowed_species ?? []);

        if ($allowedTypes !== [] && ! in_array($creature->creature_type_id, $allowedTypes, true)) {
            return false;
        }

        if ($allowedSpecies !== [] && ! in_array($creature->creature_species_id, $allowedSpecies, true)) {
            return false;
        }

        return true;
    }

    public function canBePurchasedBy(User $user): bool
    {
        return $this->is_active && $user->level >= $this->required_level;
    }

    public static function bonusLabel(string $bonus): string
    {
        return self::BONUS_LABELS[$bonus] ?? str_replace('_', ' ', $bonus);
    }

    /**
     * @return list<string>
     */
    public function bonusSummaries(): array
    {
        return collect($this->bonuses ?? [])
            ->filter(fn (mixed $value): bool => is_numeric($value) && (float) $value !== 0.0)
            ->map(function (mixed $value, string $bonus): string {
                $sign = (float) $value > 0 ? '+' : '';
                $suffix = str_contains($bonus, 'chance') ? '%' : '';

                return self::bonusLabel($bonus).' '.$sign.$value.$suffix;
            })
            ->values()
            ->all();
    }

    public function damageBonus(): int
    {
        return self::combatBonusFromBonuses($this->bonuses ?? [], self::DAMAGE_BONUS_KEYS);
    }

    public function defenseBonus(): int
    {
        return self::combatBonusFromBonuses($this->bonuses ?? [], self::DEFENSE_BONUS_KEYS);
    }

    /**
     * @return list<string>
     */
    public function combatBonusSummaries(): array
    {
        $summaries = [];
        $damage = $this->damageBonus();
        $defense = $this->defenseBonus();

        if ($damage !== 0) {
            $summaries[] = 'Урон '.($damage > 0 ? '+' : '').$damage;
        }

        if ($defense !== 0) {
            $summaries[] = 'Защита '.($defense > 0 ? '+' : '').$defense;
        }

        return $summaries;
    }

    public function applicabilitySummary(): string
    {
        $typeIds = collect($this->allowed_types ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values();
        $speciesIds = collect($this->allowed_species ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values();

        if ($typeIds->isEmpty() && $speciesIds->isEmpty()) {
            return 'Все типы сущностей';
        }

        $parts = [];

        if ($typeIds->isNotEmpty()) {
            $types = CreatureType::query()
                ->whereIn('id', $typeIds)
                ->pluck('name')
                ->filter()
                ->values();

            $parts[] = 'Типы: '.($types->isNotEmpty() ? $types->join(', ') : $typeIds->join(', '));
        }

        if ($speciesIds->isNotEmpty()) {
            $species = CreatureSpecies::query()
                ->whereIn('id', $speciesIds)
                ->pluck('name')
                ->filter()
                ->values();

            $parts[] = 'Виды: '.($species->isNotEmpty() ? $species->join(', ') : $speciesIds->join(', '));
        }

        return implode('; ', $parts);
    }

    public function durationSummary(): ?string
    {
        if (! $this->duration_type) {
            return null;
        }

        $summary = self::DURATIONS[$this->duration_type] ?? $this->duration_type;

        return $this->isConsumable()
            ? $summary.' · '.$this->initialUses().' исп.'
            : $summary;
    }

    public function effectSummary(bool $showDuration = true): string
    {
        $parts = $this->bonusSummaries();

        if ($showDuration && $duration = $this->durationSummary()) {
            $parts[] = $duration;
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $bonuses
     * @param  list<string>  $keys
     */
    private static function combatBonusFromBonuses(array $bonuses, array $keys): int
    {
        return collect($keys)
            ->sum(fn (string $key): int => is_numeric($bonuses[$key] ?? null) ? (int) $bonuses[$key] : 0);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'required_level' => 'integer',
            'allowed_types' => 'array',
            'allowed_species' => 'array',
            'slots_required' => 'array',
            'bonuses' => 'array',
            'uses_count' => 'integer',
            'is_unique' => 'boolean',
            'is_active' => 'boolean',
            'is_generated' => 'boolean',
            'generated_at' => 'datetime',
        ];
    }
}
