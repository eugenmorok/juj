<?php

namespace App\Models;

use Database\Factories\CreatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'creature_type_id',
    'creature_species_id',
    'name',
    'level',
    'xp',
    'development_points',
    'strength',
    'perception',
    'endurance',
    'charisma',
    'intelligence',
    'agility',
    'luck',
    'current_hp',
    'max_hp',
    'inventory_slots',
    'wins',
    'losses',
    'draws',
    'is_available_for_battle',
])]
class Creature extends Model
{
    /** @use HasFactory<CreatureFactory> */
    use HasFactory;

    public const CREATION_POINTS = 100;

    public const STARTER_SPECIAL_CAP = 20;

    public const STARTER_INVENTORY_SLOTS = 5;

    public const BASE_SKILL_LIMIT = 2;

    public const SPECIAL_ATTRIBUTES = [
        'strength',
        'perception',
        'endurance',
        'charisma',
        'intelligence',
        'agility',
        'luck',
    ];

    public const SPECIAL_LABELS = [
        'strength' => 'S',
        'perception' => 'P',
        'endurance' => 'E',
        'charisma' => 'C',
        'intelligence' => 'I',
        'agility' => 'A',
        'luck' => 'L',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CreatureType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(CreatureType::class, 'creature_type_id');
    }

    /**
     * @return BelongsTo<CreatureSpecies, $this>
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(CreatureSpecies::class, 'creature_species_id');
    }

    /**
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'creature_skills')
            ->withPivot(['cost_paid', 'source'])
            ->withTimestamps();
    }

    /**
     * @return array<string, int>
     */
    public function specialValues(): array
    {
        $values = [];

        foreach (self::SPECIAL_ATTRIBUTES as $attribute) {
            $values[$attribute] = (int) $this->{$attribute};
        }

        return $values;
    }

    public function spentCreationPoints(CreatureSpecies $species): int
    {
        return collect(self::SPECIAL_ATTRIBUTES)
            ->sum(fn (string $attribute): int => max(0, (int) $this->{$attribute} - $species->baseSpecialValue($attribute)));
    }

    public function maxSkills(): int
    {
        return self::BASE_SKILL_LIMIT + max(0, $this->level - 1);
    }

    public function hasSkillCapacity(): bool
    {
        return $this->skills()->count() < $this->maxSkills();
    }

    public function inventoryCapacity(): int
    {
        return 3 + intdiv($this->endurance, 10) + intdiv($this->level, 3);
    }

    /**
     * @return HasOne<Inventory, $this>
     */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function ensureInventory(): Inventory
    {
        return Inventory::forCreature($this);
    }

    public static function maxHpForEndurance(int $endurance): int
    {
        return 50 + ($endurance * 10);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'xp' => 'integer',
            'development_points' => 'integer',
            'strength' => 'integer',
            'perception' => 'integer',
            'endurance' => 'integer',
            'charisma' => 'integer',
            'intelligence' => 'integer',
            'agility' => 'integer',
            'luck' => 'integer',
            'current_hp' => 'integer',
            'max_hp' => 'integer',
            'inventory_slots' => 'integer',
            'wins' => 'integer',
            'losses' => 'integer',
            'draws' => 'integer',
            'is_available_for_battle' => 'boolean',
        ];
    }
}
