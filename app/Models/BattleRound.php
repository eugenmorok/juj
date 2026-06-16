<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'battle_id',
    'round_number',
    'status',
    'first_actor_creature_id',
    'started_at',
    'deadline_at',
    'resolved_at',
    'payload',
])]
class BattleRound extends Model
{
    use HasFactory;

    public const STATUS_COLLECTING = 'collecting';

    public const STATUS_RESOLVED = 'resolved';

    /**
     * @return BelongsTo<Battle, $this>
     */
    public function battle(): BelongsTo
    {
        return $this->belongsTo(Battle::class);
    }

    /**
     * @return BelongsTo<Creature, $this>
     */
    public function firstActor(): BelongsTo
    {
        return $this->belongsTo(Creature::class, 'first_actor_creature_id');
    }

    /**
     * @return HasMany<BattleAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(BattleAction::class);
    }

    public function isCollecting(): bool
    {
        return $this->status === self::STATUS_COLLECTING;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'battle_id' => 'integer',
            'round_number' => 'integer',
            'first_actor_creature_id' => 'integer',
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'resolved_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
