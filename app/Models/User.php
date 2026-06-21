<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
    'doctrine_points',
    'doctrine_tactic',
    'doctrine_command',
    'doctrine_engineering',
    'doctrine_breeding',
    'doctrine_trade',
    'perk_points',
    'player_perks',
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

    public const CREATURE_CREATION_POINT_XP_COST = 1;

    public const MAX_DOCTRINE_ATTRIBUTE = 20;

    public const MAX_SHOP_DISCOUNT_PERCENT = 25;

    public const MAX_BATTLE_SUPPORT_BONUS = 8;

    public const MAX_SECONDARY_BATTLE_SUPPORT_BONUS = 4;

    public const DOCTRINE_ATTRIBUTES = [
        'tactic' => [
            'column' => 'doctrine_tactic',
            'label' => 'Тактика',
            'short' => 'Так',
            'description' => 'Точность, чтение противника и скорость боевых решений.',
        ],
        'command' => [
            'column' => 'doctrine_command',
            'label' => 'Командование',
            'short' => 'Ком',
            'description' => 'Мораль, устойчивость и способность держать строй.',
        ],
        'engineering' => [
            'column' => 'doctrine_engineering',
            'label' => 'Инженерия',
            'short' => 'Инж',
            'description' => 'Работа с экипировкой, защитой, модулями и общим инвентарём.',
        ],
        'breeding' => [
            'column' => 'doctrine_breeding',
            'label' => 'Селекция',
            'short' => 'Сел',
            'description' => 'Разведение, подготовка новых сущностей и очки создания.',
        ],
        'trade' => [
            'column' => 'doctrine_trade',
            'label' => 'Торговля',
            'short' => 'Торг',
            'description' => 'Скидки, жетоны и эффективность экономики арены.',
        ],
    ];

    public const PLAYER_PERKS = [
        'battle-drill' => [
            'label' => 'Боевая муштра',
            'branch' => 'tactic',
            'required_level' => 3,
            'required_doctrine' => 2,
            'description' => 'Тактическая подготовка даёт всем сущностям ещё +1 к Perception.',
        ],
        'mentor-novices' => [
            'label' => 'Наставник новичков',
            'branch' => 'command',
            'required_level' => 3,
            'required_doctrine' => 2,
            'description' => 'Сущности ниже уровня игрока получают на 15% больше XP.',
        ],
        'equipment-tuning' => [
            'label' => 'Подгонка снаряжения',
            'branch' => 'engineering',
            'required_level' => 5,
            'required_doctrine' => 3,
            'description' => 'Прямые бонусы экипировки к Урону и Защите дополнительно усиливаются на 5%.',
        ],
        'breeder' => [
            'label' => 'Заводчик',
            'branch' => 'breeding',
            'required_level' => 5,
            'required_doctrine' => 3,
            'description' => 'Очки создания за победы получают ещё +10% к шансу и размеру.',
        ],
        'trophy-hunter' => [
            'label' => 'Охотник за трофеями',
            'branch' => 'trade',
            'required_level' => 5,
            'required_doctrine' => 3,
            'description' => 'Жетоны за бои увеличиваются ещё на 8%.',
        ],
    ];

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
            'doctrine_points' => 'integer',
            'doctrine_tactic' => 'integer',
            'doctrine_command' => 'integer',
            'doctrine_engineering' => 'integer',
            'doctrine_breeding' => 'integer',
            'doctrine_trade' => 'integer',
            'perk_points' => 'integer',
            'player_perks' => 'array',
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
        $baseSlots = 5 + ($this->level * 2) + $this->engineeringInventoryBonus();
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
            : min(self::MAX_SHOP_DISCOUNT_PERCENT, max(0, $this->level - 1) + $this->doctrine_trade);
    }

    /**
     * @return array<string, int>
     */
    public function battleSupportBonus(): array
    {
        if ($this->is_bot) {
            return [
                'agility' => 0,
                'endurance' => 0,
                'perception' => 0,
                'charisma' => 0,
                'intelligence' => 0,
            ];
        }

        return [
            'agility' => min(self::MAX_SECONDARY_BATTLE_SUPPORT_BONUS, intdiv($this->doctrine_tactic, 4)),
            'endurance' => min(self::MAX_SECONDARY_BATTLE_SUPPORT_BONUS, intdiv($this->doctrine_command, 4)),
            'perception' => min(self::MAX_BATTLE_SUPPORT_BONUS, intdiv(max(0, $this->level - 1), 4) + intdiv($this->doctrine_tactic, 2) + ($this->hasPlayerPerk('battle-drill') ? 1 : 0)),
            'charisma' => min(self::MAX_BATTLE_SUPPORT_BONUS, intdiv(max(0, $this->level - 1), 3) + intdiv($this->doctrine_command, 2)),
            'intelligence' => min(self::MAX_BATTLE_SUPPORT_BONUS, intdiv(max(0, $this->level - 1), 5) + intdiv($this->doctrine_engineering, 2)),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function doctrineAttributes(): array
    {
        return collect(self::DOCTRINE_ATTRIBUTES)
            ->mapWithKeys(fn (array $meta, string $attribute): array => [
                $attribute => (int) $this->{$meta['column']},
            ])
            ->all();
    }

    public function doctrinePointsSpent(): int
    {
        return array_sum($this->doctrineAttributes());
    }

    public static function doctrinePointsEarnedForLevel(int $level): int
    {
        $level = max(1, $level);

        return max(0, $level - 1) + intdiv($level, 5);
    }

    public function doctrinePointsEarned(): int
    {
        return self::doctrinePointsEarnedForLevel($this->level);
    }

    public function doctrinePointsTotal(): int
    {
        return $this->doctrine_points + $this->doctrinePointsSpent();
    }

    public static function perkPointsEarnedForLevel(int $level): int
    {
        $level = max(1, $level);
        $milestones = [3, 5, 8, 11, 14, 17, 20];
        $points = collect($milestones)->filter(fn (int $milestone): bool => $level >= $milestone)->count();

        if ($level > 20) {
            $points += intdiv($level - 20, 4);
        }

        return $points;
    }

    public function perkPointsEarned(): int
    {
        return self::perkPointsEarnedForLevel($this->level);
    }

    /**
     * @return list<string>
     */
    public function playerPerks(): array
    {
        return collect($this->player_perks ?? [])
            ->filter(fn (mixed $perk): bool => is_string($perk) && array_key_exists($perk, self::PLAYER_PERKS))
            ->unique()
            ->values()
            ->all();
    }

    public function hasPlayerPerk(string $perk): bool
    {
        return in_array($perk, $this->playerPerks(), true);
    }

    public function canBuyPlayerPerk(string $perk): bool
    {
        if ($this->is_bot || $this->perk_points < 1 || $this->hasPlayerPerk($perk)) {
            return false;
        }

        $meta = self::PLAYER_PERKS[$perk] ?? null;

        if (! $meta || $this->level < $meta['required_level']) {
            return false;
        }

        $branch = $meta['branch'];
        $column = self::DOCTRINE_ATTRIBUTES[$branch]['column'] ?? null;

        return $column !== null && (int) $this->{$column} >= (int) $meta['required_doctrine'];
    }

    public function creationPointRewardBonusPercent(): int
    {
        return $this->is_bot ? 0 : min(40, ($this->doctrine_breeding * 3) + ($this->hasPlayerPerk('breeder') ? 10 : 0));
    }

    public function tokenRewardBonusPercent(): int
    {
        return $this->is_bot ? 0 : min(40, ($this->doctrine_trade * 3) + ($this->hasPlayerPerk('trophy-hunter') ? 8 : 0));
    }

    public function tokenRewardMultiplier(): float
    {
        return 1 + ($this->tokenRewardBonusPercent() / 100);
    }

    public function creatureXpMultiplier(Creature $creature): float
    {
        if ($this->is_bot || ! $this->hasPlayerPerk('mentor-novices')) {
            return 1.0;
        }

        return $creature->level < $this->level ? 1.15 : 1.0;
    }

    public function equipmentCombatBonusPercent(): int
    {
        return $this->is_bot ? 0 : min(25, ($this->doctrine_engineering * 2) + ($this->hasPlayerPerk('equipment-tuning') ? 5 : 0));
    }

    public function equipmentCombatBonusMultiplier(): float
    {
        return 1 + ($this->equipmentCombatBonusPercent() / 100);
    }

    private function engineeringInventoryBonus(): int
    {
        return $this->is_bot ? 0 : intdiv($this->doctrine_engineering, 2);
    }

    public function botStrengthPercent(?ArenaSetting $settings = null): int
    {
        if (! $this->is_bot) {
            return 100;
        }

        $settings ??= ArenaSetting::current();
        $profilePercent = min(150, max(50, (int) ($this->botProfile?->strength_percent ?? 100)));

        return min(225, max(25, (int) round(
            $profilePercent * $settings->botGlobalStrengthPercent() / 100
        )));
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
