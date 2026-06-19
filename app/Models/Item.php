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
        'damage' => 'Урон',
        'armor' => 'Броня',
        'hp' => 'Макс. HP',
        'max_hp' => 'Макс. HP',
        'hp_max' => 'Макс. HP',
        'heal' => 'Лечение',
        'hp_restore' => 'Лечение',
        'crit_chance' => 'Шанс крита',
        'poison_damage' => 'Урон ядом',
    ];

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
        $slotKeys = collect($this->slots_required ?? [])
            ->filter(fn (mixed $slotKey): bool => is_string($slotKey) && $slotKey !== '')
            ->values();

        if ($slotKeys->isEmpty() && $this->slot_key) {
            $slotKeys->push($this->slot_key);
        }

        return $slotKeys
            ->unique()
            ->values()
            ->all();
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
