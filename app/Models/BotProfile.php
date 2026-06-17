<?php

namespace App\Models;

use Database\Factories\BotProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'display_name',
    'style',
    'is_active',
    'min_level',
    'max_level',
    'spawn_chance',
    'generated_creatures_count',
    'last_generated_at',
    'notes',
])]
class BotProfile extends Model
{
    /** @use HasFactory<BotProfileFactory> */
    use HasFactory;

    public const STYLES = [
        'aggressive' => 'Агрессивный',
        'defensive' => 'Защитный',
        'balanced' => 'Сбалансированный',
        'random' => 'Случайный',
        'economical' => 'Экономный',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        static::creating(function (BotProfile $profile): void {
            if ($profile->user_id) {
                return;
            }

            $displayName = $profile->display_name ?: 'Arena Bot';
            $slug = Str::slug($displayName) ?: 'arena-bot';

            $profile->user()->associate(User::query()->create([
                'name' => $displayName,
                'email' => $slug.'-'.Str::lower(Str::random(8)).'@bots.rpg-arena.test',
                'password' => Hash::make(Str::random(32)),
                'level' => max(1, (int) $profile->min_level),
                'xp' => 0,
                'tokens' => 0,
                'creature_creation_points' => 0,
                'inventory_slots' => 5,
                'is_bot' => true,
                'is_admin' => false,
            ]));
        });

        static::saved(function (BotProfile $profile): void {
            $profile->user?->forceFill([
                'name' => $profile->display_name,
                'level' => max(1, (int) $profile->min_level),
                'creature_creation_points' => 0,
                'is_bot' => true,
                'is_admin' => false,
            ])->save();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'is_active' => 'boolean',
            'min_level' => 'integer',
            'max_level' => 'integer',
            'spawn_chance' => 'integer',
            'generated_creatures_count' => 'integer',
            'last_generated_at' => 'datetime',
        ];
    }
}
