<?php

namespace App\Models;

use Database\Factories\CreatureEquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'creature_id',
    'item_instance_id',
    'slot_key',
])]
class CreatureEquipment extends Model
{
    /** @use HasFactory<CreatureEquipmentFactory> */
    use HasFactory;

    protected $table = 'creature_equipment';

    /**
     * @return BelongsTo<Creature, $this>
     */
    public function creature(): BelongsTo
    {
        return $this->belongsTo(Creature::class);
    }

    /**
     * @return BelongsTo<ItemInstance, $this>
     */
    public function itemInstance(): BelongsTo
    {
        return $this->belongsTo(ItemInstance::class);
    }

    /**
     * @return BelongsTo<EquipmentSlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(EquipmentSlot::class, 'slot_key', 'code');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'creature_id' => 'integer',
            'item_instance_id' => 'integer',
        ];
    }
}
