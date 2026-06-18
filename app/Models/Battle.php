<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'initiator_user_id',
    'battle_arena_id',
    'arena_name',
    'arena_background_image',
    'arena_effects',
    'winner_creature_id',
    'battle_type',
    'mode',
    'status',
    'is_draw',
    'current_round',
    'current_actor_creature_id',
    'turn_deadline_at',
    'seed',
    'started_at',
    'finished_at',
])]
class Battle extends Model
{
    use HasFactory;

    public const TYPE_RANKED = 'ranked';

    public const TYPE_SIMULATION = 'simulation';

    public const MODE_INSTANT = 'instant';

    public const MODE_INTERACTIVE = 'interactive';

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
     * @return BelongsTo<BattleArena, $this>
     */
    public function arena(): BelongsTo
    {
        return $this->belongsTo(BattleArena::class, 'battle_arena_id');
    }

    /**
     * @return BelongsTo<Creature, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Creature::class, 'winner_creature_id');
    }

    /**
     * @return BelongsTo<Creature, $this>
     */
    public function currentActor(): BelongsTo
    {
        return $this->belongsTo(Creature::class, 'current_actor_creature_id');
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
     * @return HasMany<BattleMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(BattleMessage::class)->oldest();
    }

    /**
     * @return HasMany<BattleRound, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(BattleRound::class)->orderBy('round_number');
    }

    /**
     * @return HasMany<BattleAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(BattleAction::class);
    }

    public function isInteractive(): bool
    {
        return $this->mode === self::MODE_INTERACTIVE;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initiator_user_id' => 'integer',
            'battle_arena_id' => 'integer',
            'arena_effects' => 'array',
            'winner_creature_id' => 'integer',
            'is_draw' => 'boolean',
            'current_round' => 'integer',
            'current_actor_creature_id' => 'integer',
            'turn_deadline_at' => 'datetime',
            'seed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
