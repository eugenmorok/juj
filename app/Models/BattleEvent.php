<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'battle_id',
    'round',
    'event_type',
    'actor_creature_id',
    'target_creature_id',
    'payload',
    'text_log',
])]
class BattleEvent extends Model
{
    use HasFactory;

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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Creature::class, 'actor_creature_id');
    }

    /**
     * @return BelongsTo<Creature, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Creature::class, 'target_creature_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'battle_id' => 'integer',
            'round' => 'integer',
            'actor_creature_id' => 'integer',
            'target_creature_id' => 'integer',
            'payload' => 'array',
        ];
    }
}
