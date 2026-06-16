<?php

namespace App\Models;

use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

#[Fillable([
    'owner_user_id',
    'creature_id',
    'inventory_type',
    'slots',
])]
class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    public const TYPE_PLAYER = 'player';

    public const TYPE_CREATURE = 'creature';

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
    public function creature(): BelongsTo
    {
        return $this->belongsTo(Creature::class);
    }

    /**
     * @return HasMany<InventoryItem, $this>
     */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public static function forUser(User $user): self
    {
        $inventory = self::query()->firstOrCreate([
            'owner_user_id' => $user->id,
            'creature_id' => null,
            'inventory_type' => self::TYPE_PLAYER,
        ], [
            'slots' => $user->inventoryCapacity(),
        ]);

        return $inventory->syncSlots();
    }

    public static function forCreature(Creature $creature): self
    {
        $inventory = self::query()->firstOrCreate([
            'owner_user_id' => $creature->user_id,
            'creature_id' => $creature->id,
            'inventory_type' => self::TYPE_CREATURE,
        ], [
            'slots' => $creature->inventoryCapacity(),
        ]);

        return $inventory->syncSlots();
    }

    public function syncSlots(): self
    {
        $capacity = $this->capacity();

        if ((int) $this->slots !== $capacity) {
            $this->forceFill(['slots' => $capacity])->save();
        }

        return $this;
    }

    public function capacity(): int
    {
        if ($this->inventory_type === self::TYPE_PLAYER && $this->owner) {
            return $this->owner->inventoryCapacity();
        }

        if ($this->inventory_type === self::TYPE_CREATURE && $this->creature) {
            return $this->creature->inventoryCapacity();
        }

        return max(0, (int) $this->slots);
    }

    public function usedSlots(): int
    {
        if ($this->relationLoaded('inventoryItems')) {
            return $this->inventoryItems->count();
        }

        return $this->inventoryItems()->count();
    }

    public function freeSlots(): int
    {
        return max(0, $this->capacity() - $this->usedSlots());
    }

    public function hasFreeSlot(): bool
    {
        return $this->freeSlots() > 0;
    }

    public function nextSlotNumber(): ?int
    {
        $usedSlots = $this->inventoryItems()
            ->pluck('slot_number')
            ->map(fn (mixed $slot): int => (int) $slot)
            ->all();

        for ($slot = 1; $slot <= $this->capacity(); $slot++) {
            if (! in_array($slot, $usedSlots, true)) {
                return $slot;
            }
        }

        return null;
    }

    public function addItemInstance(ItemInstance $itemInstance): InventoryItem
    {
        $slotNumber = $this->nextSlotNumber();

        if ($slotNumber === null) {
            throw new RuntimeException('Inventory capacity exceeded.');
        }

        $inventoryItem = $this->inventoryItems()->create([
            'item_instance_id' => $itemInstance->id,
            'slot_number' => $slotNumber,
        ]);

        $itemInstance->forceFill([
            'owner_user_id' => $this->owner_user_id,
            'bound_creature_id' => $this->creature_id,
            'state' => 'stored',
        ])->save();

        return $inventoryItem;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_user_id' => 'integer',
            'creature_id' => 'integer',
            'slots' => 'integer',
        ];
    }
}
