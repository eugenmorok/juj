<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'initiator_user_id',
    'winner_creature_id',
    'battle_type',
    'status',
    'is_draw',
    'seed',
    'started_at',
    'finished_at',
])]
class Battle extends Model
{
    use HasFactory;

    public const TYPE_RANKED = 'ranked';

    public const STATUS_RUNNING = 'running';

    public const STATUS_FINISHED = 'finished';

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_user_id');
    }

    /**
     * @return BelongsTo<Creature, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Creature::class, 'winner_creature_id');
    }

    /**
     * @return HasMany<BattleParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(BattleParticipant::class);
    }

    /**
     * @return HasMany<BattleEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(BattleEvent::class)->orderBy('round')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initiator_user_id' => 'integer',
            'winner_creature_id' => 'integer',
            'is_draw' => 'boolean',
            'seed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
