<?php

namespace App\Models;

use Database\Factories\EquipmentSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'code',
    'description',
    'sort_order',
    'is_active',
])]
class EquipmentSlot extends Model
{
    /** @use HasFactory<EquipmentSlotFactory> */
    use HasFactory;

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'slot_key', 'code');
    }

    /**
     * @return HasMany<CreatureEquipment, $this>
     */
    public function equipmentRows(): HasMany
    {
        return $this->hasMany(CreatureEquipment::class, 'slot_key', 'code');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
