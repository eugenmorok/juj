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
        ];
    }
}
