<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'battle_id',
    'battle_round_id',
    'user_id',
    'creature_id',
    'action_type',
    'attack_zone',
    'defense_zone',
    'inventory_item_id',
    'is_auto',
    'payload',
    'submitted_at',
])]
class BattleAction extends Model
{
    use HasFactory;

    public const TYPE_STRIKE = 'strike';

    public const TYPE_ITEM = 'item';

    public const ZONES = [
        'head' => 'Голова',
        'body' => 'Тело',
        'arms' => 'Руки',
        'legs' => 'Ноги',
    ];

    /**
     * @return BelongsTo<Battle, $this>
     */
    public function battle(): BelongsTo
    {
        return $this->belongsTo(Battle::class);
    }

    /**
     * @return BelongsTo<BattleRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(BattleRound::class, 'battle_round_id');
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
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'battle_id' => 'integer',
            'battle_round_id' => 'integer',
            'user_id' => 'integer',
            'creature_id' => 'integer',
            'inventory_item_id' => 'integer',
            'is_auto' => 'boolean',
            'payload' => 'array',
            'submitted_at' => 'datetime',
        ];
    }
}
