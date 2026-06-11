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
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
