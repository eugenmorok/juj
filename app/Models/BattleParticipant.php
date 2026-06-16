<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'battle_id',
    'user_id',
    'creature_id',
    'is_bot',
    'side',
    'result',
    'power_score_before',
    'hp_before',
    'hp_after',
    'level_before',
    'level_after',
    'reward_xp',
    'reward_tokens',
    'reward_development_points',
    'reward_multiplier',
])]
class BattleParticipant extends Model
{
    use HasFactory;

    public const RESULT_WIN = 'win';

    public const RESULT_LOSS = 'loss';

    public const RESULT_DRAW = 'draw';

    /**
     * @return BelongsTo<Battle, $this>
     */
    public function battle(): BelongsTo
    {
        return $this->belongsTo(Battle::class);
    }

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'battle_id' => 'integer',
            'user_id' => 'integer',
            'creature_id' => 'integer',
            'is_bot' => 'boolean',
            'power_score_before' => 'integer',
            'hp_before' => 'integer',
            'hp_after' => 'integer',
            'level_before' => 'integer',
            'level_after' => 'integer',
            'reward_xp' => 'integer',
            'reward_tokens' => 'integer',
            'reward_development_points' => 'integer',
            'reward_multiplier' => 'decimal:2',
        ];
    }
}
