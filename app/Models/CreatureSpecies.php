<?php

namespace App\Models;

use Database\Factories\CreatureSpeciesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'creature_type_id',
    'name',
    'code',
    'description',
    'icon',
    'rarity',
    'base_strength',
    'base_perception',
    'base_endurance',
    'base_charisma',
    'base_intelligence',
    'base_agility',
    'base_luck',
    'is_starter_available',
    'is_active',
])]
class CreatureSpecies extends Model
{
    /** @use HasFactory<CreatureSpeciesFactory> */
    use HasFactory;

    public const SPECIAL_FIELDS = [
        'base_strength',
        'base_perception',
        'base_endurance',
        'base_charisma',
        'base_intelligence',
        'base_agility',
        'base_luck',
    ];

    public const RARITIES = [
        'common' => 'Обычный',
        'rare' => 'Редкий',
        'elite' => 'Элитный',
        'unique' => 'Уникальный',
    ];

    public const BASE_TO_CREATURE_ATTRIBUTES = [
        'strength' => 'base_strength',
        'perception' => 'base_perception',
        'endurance' => 'base_endurance',
        'charisma' => 'base_charisma',
        'intelligence' => 'base_intelligence',
        'agility' => 'base_agility',
        'luck' => 'base_luck',
    ];

    /**
     * @return BelongsTo<CreatureType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(CreatureType::class, 'creature_type_id');
    }

    public function baseSpecialValue(string $attribute): int
    {
        $baseAttribute = self::BASE_TO_CREATURE_ATTRIBUTES[$attribute] ?? null;

        return $baseAttribute === null ? 0 : (int) $this->{$baseAttribute};
    }

    /**
     * @return array<string, int>
     */
    public function baseSpecialValues(): array
    {
        $values = [];

        foreach (self::BASE_TO_CREATURE_ATTRIBUTES as $attribute => $baseAttribute) {
            $values[$attribute] = (int) $this->{$baseAttribute};
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_strength' => 'integer',
            'base_perception' => 'integer',
            'base_endurance' => 'integer',
            'base_charisma' => 'integer',
            'base_intelligence' => 'integer',
            'base_agility' => 'integer',
            'base_luck' => 'integer',
            'is_starter_available' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function starterAvailable(Builder $query): void
    {
        $query->where('is_starter_available', true);
    }
}
