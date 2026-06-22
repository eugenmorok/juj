<?php

namespace App\Models;

use Database\Factories\CreatureTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'code',
    'description',
    'icon',
    'type_bonus',
    'type_weakness',
    'creation_required_player_level',
    'is_active',
])]
class CreatureType extends Model
{
    /** @use HasFactory<CreatureTypeFactory> */
    use HasFactory;

    /**
     * @return HasMany<CreatureSpecies, $this>
     */
    public function species(): HasMany
    {
        return $this->hasMany(CreatureSpecies::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type_bonus' => 'array',
            'type_weakness' => 'array',
            'creation_required_player_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function creationRequiredPlayerLevel(): int
    {
        return max(1, (int) $this->creation_required_player_level);
    }

    public function isUnlockedFor(User $user): bool
    {
        return (int) $user->level >= $this->creationRequiredPlayerLevel();
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
