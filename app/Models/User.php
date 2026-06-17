<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'level',
    'xp',
    'tokens',
    'creature_creation_points',
    'inventory_slots',
    'is_bot',
    'is_admin',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const CREATURE_CREATION_COST = 100;

    public const CREATURE_CREATION_POINT_XP_COST = 10;

    public const MAX_SHOP_DISCOUNT_PERCENT = 20;

    public const MAX_BATTLE_SUPPORT_BONUS = 6;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'level' => 'integer',
            'xp' => 'integer',
            'tokens' => 'integer',
            'creature_creation_points' => 'integer',
            'inventory_slots' => 'integer',
            'is_bot' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin;
    }

    public function inventoryCapacity(): int
    {
        $baseSlots = 5 + ($this->level * 2);
        $purchasedSlots = max(0, $this->inventory_slots - 5);

        return $baseSlots + $purchasedSlots;
    }

    public function purchasedInventorySlots(): int
    {
        return max(0, $this->inventory_slots - 5);
    }

    public function shopDiscountPercent(): int
    {
        return $this->is_bot
            ? 0
            : min(self::MAX_SHOP_DISCOUNT_PERCENT, max(0, $this->level - 1));
    }

    /**
     * @return array<string, int>
     */
    public function battleSupportBonus(): array
    {
        if ($this->is_bot) {
            return [
                'perception' => 0,
                'charisma' => 0,
                'intelligence' => 0,
            ];
        }

        return [
            'perception' => min(self::MAX_BATTLE_SUPPORT_BONUS, intdiv(max(0, $this->level - 1), 4)),
            'charisma' => min(self::MAX_BATTLE_SUPPORT_BONUS, intdiv(max(0, $this->level - 1), 3)),
            'intelligence' => min(self::MAX_BATTLE_SUPPORT_BONUS, intdiv(max(0, $this->level - 1), 5)),
        ];
    }

    public function canCreateCreature(): bool
    {
        return $this->creature_creation_points >= self::CREATURE_CREATION_COST;
    }

    /**
     * @return HasMany<Creature, $this>
     */
    public function creatures(): HasMany
    {
        return $this->hasMany(Creature::class);
    }

    /**
     * @return HasMany<BattleParticipant, $this>
     */
    public function battleParticipants(): HasMany
    {
        return $this->hasMany(BattleParticipant::class);
    }

    /**
     * @return HasOne<BotProfile, $this>
     */
    public function botProfile(): HasOne
    {
        return $this->hasOne(BotProfile::class);
    }

    /**
     * @return HasOne<Inventory, $this>
     */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class, 'owner_user_id')
            ->where('inventory_type', Inventory::TYPE_PLAYER)
            ->whereNull('creature_id');
    }

    public function ensureInventory(): Inventory
    {
        return Inventory::forUser($this);
    }
}
