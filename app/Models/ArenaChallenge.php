<?php

namespace App\Models;

use Database\Factories\ArenaChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'challenger_user_id',
    'challenger_creature_id',
    'defender_user_id',
    'defender_creature_id',
    'defender_is_bot',
    'status',
    'expires_at',
    'accepted_at',
    'declined_at',
    'battle_id',
])]
class ArenaChallenge extends Model
{
    /** @use HasFactory<ArenaChallengeFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_BATTLE_STARTED = 'battle_started';

    public const ACCEPTANCE_SECONDS = 120;

    /**
     * @return BelongsTo<User, $this>
     */
    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_user_id');
    }

    /**
     * @return BelongsTo<Creature, $this>
     */
    public function challengerCreature(): BelongsTo
    {
        return $this->belongsTo(Creature::class, 'challenger_creature_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function defender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'defender_user_id');
    }

    /**
     * @return BelongsTo<Creature, $this>
     */
    public function defenderCreature(): BelongsTo
    {
        return $this->belongsTo(Creature::class, 'defender_creature_id');
    }

    /**
     * @return BelongsTo<Battle, $this>
     */
    public function battle(): BelongsTo
    {
        return $this->belongsTo(Battle::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->isPending()
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function markExpired(): void
    {
        if (! $this->isExpired()) {
            return;
        }

        $this->forceFill(['status' => self::STATUS_EXPIRED])->save();
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query
            ->where('status', self::STATUS_PENDING)
            ->where(fn (Builder $builder) => $builder
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'challenger_user_id' => 'integer',
            'challenger_creature_id' => 'integer',
            'defender_user_id' => 'integer',
            'defender_creature_id' => 'integer',
            'defender_is_bot' => 'boolean',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'battle_id' => 'integer',
        ];
    }
}
