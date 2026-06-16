<?php

namespace App\Models;

use Database\Factories\ItemInstanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'item_id',
    'owner_user_id',
    'bound_creature_id',
    'durability',
    'state',
])]
class ItemInstance extends Model
{
    /** @use HasFactory<ItemInstanceFactory> */
    use HasFactory;

    public const STATES = [
        'stored' => 'В инвентаре',
        'equipped' => 'Экипирован',
        'locked' => 'Заблокирован в бою',
        'used' => 'Использован',
        'sold' => 'Продан',
        'deleted' => 'Удален',
    ];

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<Creature, $this>
     */
    public function boundCreature(): BelongsTo
    {
        return $this->belongsTo(Creature::class, 'bound_creature_id');
    }

    /**
     * @return HasOne<InventoryItem, $this>
     */
    public function inventoryItem(): HasOne
    {
        return $this->hasOne(InventoryItem::class);
    }

    /**
     * @return HasMany<CreatureEquipment, $this>
     */
    public function equipmentRows(): HasMany
    {
        return $this->hasMany(CreatureEquipment::class);
    }

    public function remainingUses(): int
    {
        $this->loadMissing('item');

        if (! $this->item?->isConsumable()) {
            return 0;
        }

        return min($this->item->initialUses(), max(0, (int) $this->durability));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'durability' => 'integer',
        ];
    }
}
