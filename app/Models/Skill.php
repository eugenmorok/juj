<?php

namespace App\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name',
    'code',
    'description',
    'skill_type',
    'cost',
    'required_level',
    'required_creature_type_id',
    'required_creature_species_id',
    'required_strength',
    'required_perception',
    'required_endurance',
    'required_charisma',
    'required_intelligence',
    'required_agility',
    'required_luck',
    'effect',
    'cooldown_turns',
    'is_starter_available',
    'is_active',
])]
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    public const TYPES = [
        'passive' => 'Пассивный',
        'active' => 'Активный',
        'conditional' => 'Условно-автоматический',
    ];

    public const REQUIREMENT_FIELDS = [
        'strength' => 'required_strength',
        'perception' => 'required_perception',
        'endurance' => 'required_endurance',
        'charisma' => 'required_charisma',
        'intelligence' => 'required_intelligence',
        'agility' => 'required_agility',
        'luck' => 'required_luck',
    ];

    /**
     * @return BelongsTo<CreatureType, $this>
     */
    public function requiredType(): BelongsTo
    {
        return $this->belongsTo(CreatureType::class, 'required_creature_type_id');
    }

    /**
     * @return BelongsTo<CreatureSpecies, $this>
     */
    public function requiredSpecies(): BelongsTo
    {
        return $this->belongsTo(CreatureSpecies::class, 'required_creature_species_id');
    }

    /**
     * @return BelongsToMany<Creature, $this>
     */
    public function creatures(): BelongsToMany
    {
        return $this->belongsToMany(Creature::class, 'creature_skills')
            ->withPivot(['cost_paid', 'source'])
            ->withTimestamps();
    }

    public function isAvailableFor(Creature $creature, bool $duringCreation = false): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($duringCreation && ! $this->is_starter_available) {
            return false;
        }

        if ($creature->level < $this->required_level) {
            return false;
        }

        if ($this->required_creature_type_id !== null && $creature->creature_type_id !== $this->required_creature_type_id) {
            return false;
        }

        if ($this->required_creature_species_id !== null && $creature->creature_species_id !== $this->required_creature_species_id) {
            return false;
        }

        foreach (self::REQUIREMENT_FIELDS as $attribute => $requirement) {
            if ((int) $creature->{$attribute} < (int) $this->{$requirement}) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, int>
     */
    public function specialRequirements(): array
    {
        $requirements = [];

        foreach (self::REQUIREMENT_FIELDS as $attribute => $requirement) {
            $value = (int) $this->{$requirement};

            if ($value > 0) {
                $requirements[$attribute] = $value;
            }
        }

        return $requirements;
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'integer',
            'required_level' => 'integer',
            'required_creature_type_id' => 'integer',
            'required_creature_species_id' => 'integer',
            'required_strength' => 'integer',
            'required_perception' => 'integer',
            'required_endurance' => 'integer',
            'required_charisma' => 'integer',
            'required_intelligence' => 'integer',
            'required_agility' => 'integer',
            'required_luck' => 'integer',
            'cooldown_turns' => 'integer',
            'is_starter_available' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
