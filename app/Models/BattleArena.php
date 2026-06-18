<?php

namespace App\Models;

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
    'background_image',
    'special_effects',
    'is_active',
    'sort_order',
])]
class BattleArena extends Model
{
    use HasFactory;

    /**
     * @return HasMany<Battle, $this>
     */
    public function battles(): HasMany
    {
        return $this->hasMany(Battle::class);
    }

    /**
     * @return array<string, string>
     */
    public static function specialOptions(): array
    {
        return [
            'strength' => 'S — Сила',
            'perception' => 'P — Восприятие',
            'endurance' => 'E — Выносливость',
            'charisma' => 'C — Харизма',
            'intelligence' => 'I — Интеллект',
            'agility' => 'A — Ловкость',
            'luck' => 'L — Удача',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'special_effects' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
