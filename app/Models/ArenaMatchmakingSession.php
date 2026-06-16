<?php

namespace App\Models;

use Database\Factories\ArenaMatchmakingSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'creature_id',
    'power_score',
    'status',
    'expires_at',
])]
class ArenaMatchmakingSession extends Model
{
    /** @use HasFactory<ArenaMatchmakingSessionFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const TTL_MINUTES = 10;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Creature, $this>
     */
    public function creature(): BelongsTo
    {
        return $this->belongsTo(Creature::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function markExpired(): void
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return;
        }

        $this->forceFill(['status' => self::STATUS_EXPIRED])->save();
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query
            ->where('status', self::STATUS_ACTIVE)
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
            'user_id' => 'integer',
            'creature_id' => 'integer',
            'power_score' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
