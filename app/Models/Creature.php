<?php

namespace App\Models;

use Database\Factories\CreatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'creature_type_id',
    'creature_species_id',
    'name',
    'level',
    'xp',
    'development_points',
    'strength',
    'perception',
    'endurance',
    'charisma',
    'intelligence',
    'agility',
    'luck',
    'current_hp',
    'max_hp',
    'inventory_slots',
    'wins',
    'losses',
    'draws',
    'is_available_for_battle',
])]
class Creature extends Model
{
    /** @use HasFactory<CreatureFactory> */
    use HasFactory;

    public const CREATION_POINTS = 100;

    public const STARTER_SPECIAL_CAP = 20;

    public const DEVELOPMENT_SPECIAL_CAP = 50;

    public const SPECIAL_DEVELOPMENT_COST = 25;

    public const STARTER_INVENTORY_SLOTS = 5;

    public const BASE_SKILL_LIMIT = 2;

    public const SPECIAL_ATTRIBUTES = [
        'strength',
        'perception',
        'endurance',
        'charisma',
        'intelligence',
        'agility',
        'luck',
    ];

    public const SPECIAL_LABELS = [
        'strength' => 'S',
        'perception' => 'P',
        'endurance' => 'E',
        'charisma' => 'C',
        'intelligence' => 'I',
        'agility' => 'A',
        'luck' => 'L',
    ];

    public const DAMAGE_BONUS_KEYS = ['damage', 'attack'];

    public const DEFENSE_BONUS_KEYS = ['defense', 'armor'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CreatureType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(CreatureType::class, 'creature_type_id');
    }

    /**
     * @return BelongsTo<CreatureSpecies, $this>
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(CreatureSpecies::class, 'creature_species_id');
    }

    /**
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'creature_skills')
            ->withPivot(['cost_paid', 'source'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<CreatureEquipment, $this>
     */
    public function equipmentRows(): HasMany
    {
        return $this->hasMany(CreatureEquipment::class);
    }

    /**
     * @return HasMany<BattleParticipant, $this>
     */
    public function battleParticipants(): HasMany
    {
        return $this->hasMany(BattleParticipant::class);
    }

    /**
     * @return array<string, int>
     */
    public function specialValues(): array
    {
        $values = [];

        foreach (self::SPECIAL_ATTRIBUTES as $attribute) {
            $values[$attribute] = (int) $this->{$attribute};
        }

        return $values;
    }

    /**
     * @return array<string, int>
     */
    public function equipmentBonuses(): array
    {
        $bonuses = [];
        $equipmentRows = $this->relationLoaded('equipmentRows')
            ? $this->equipmentRows
            : $this->equipmentRows()->with('itemInstance.item')->get();

        $equipmentRows
            ->pluck('itemInstance')
            ->filter()
            ->unique('id')
            ->each(function (ItemInstance $itemInstance) use (&$bonuses): void {
                foreach (($itemInstance->item?->bonuses ?? []) as $attribute => $value) {
                    if (! is_numeric($value)) {
                        continue;
                    }

                    $bonuses[$attribute] = ($bonuses[$attribute] ?? 0) + (int) $value;
                }
            });

        return $bonuses;
    }

    /**
     * @return array<string, int>
     */
    public function effectiveSpecialValues(?ArenaSetting $settings = null): array
    {
        $bonuses = $this->equipmentBonuses();
        $strengthPercent = $this->user?->botStrengthPercent($settings) ?? 100;

        return collect(self::SPECIAL_ATTRIBUTES)
            ->mapWithKeys(fn (string $attribute): array => [
                $attribute => max(1, (int) round(
                    ((int) $this->{$attribute} + (int) ($bonuses[$attribute] ?? 0))
                    * $strengthPercent
                    / 100
                )),
            ])
            ->all();
    }

    public function effectiveMaxHp(?ArenaSetting $settings = null): int
    {
        return max(1, (int) round(
            ($this->max_hp + (int) ($this->equipmentBonuses()['hp'] ?? 0))
            * ($this->user?->botStrengthPercent($settings) ?? 100)
            / 100
        ));
    }

    /**
     * @param  array<string, int|float>  $special
     */
    public static function damageFromSpecial(array $special): int
    {
        return max(1, (int) round(
            4
            + ((int) ($special['strength'] ?? 1) * 1.30)
            + ((int) ($special['agility'] ?? 1) * 0.35)
            + ((int) ($special['intelligence'] ?? 1) * 0.25)
        ));
    }

    /**
     * @param  array<string, int|float>  $special
     */
    public static function defenseFromSpecial(array $special, bool $guarded = false): int
    {
        return max(0, (int) round(
            ((int) ($special['endurance'] ?? 1) * 0.55)
            + ((int) ($special['charisma'] ?? 1) * 0.25)
            + ((int) ($special['intelligence'] ?? 1) * ($guarded ? 0.28 : 0.15))
            + ($guarded ? 7 : 0)
        ));
    }

    /**
     * @param  array<string, mixed>  $bonuses
     */
    public static function damageBonusFromBonuses(array $bonuses): int
    {
        return self::combatBonusFromBonuses($bonuses, self::DAMAGE_BONUS_KEYS);
    }

    /**
     * @param  array<string, mixed>  $bonuses
     */
    public static function defenseBonusFromBonuses(array $bonuses): int
    {
        return self::combatBonusFromBonuses($bonuses, self::DEFENSE_BONUS_KEYS);
    }

    /**
     * @return array{damage: array{base: int, equipment: int, total: int}, defense: array{base: int, equipment: int, total: int}}
     */
    public function effectiveCombatStats(?ArenaSetting $settings = null): array
    {
        $special = $this->effectiveSpecialValues($settings);
        $bonuses = $this->equipmentBonuses();
        $damageBase = self::damageFromSpecial($special);
        $defenseBase = self::defenseFromSpecial($special);
        $damageBonus = self::applyEquipmentCombatMastery(self::damageBonusFromBonuses($bonuses), $this->user);
        $defenseBonus = self::applyEquipmentCombatMastery(self::defenseBonusFromBonuses($bonuses), $this->user);

        return [
            'damage' => [
                'base' => $damageBase,
                'equipment' => $damageBonus,
                'total' => max(1, $damageBase + $damageBonus),
            ],
            'defense' => [
                'base' => $defenseBase,
                'equipment' => $defenseBonus,
                'total' => max(0, $defenseBase + $defenseBonus),
            ],
        ];
    }

    public function spentCreationPoints(CreatureSpecies $species): int
    {
        return collect(self::SPECIAL_ATTRIBUTES)
            ->sum(fn (string $attribute): int => max(0, (int) $this->{$attribute} - $species->baseSpecialValue($attribute)));
    }

    public function maxSkills(): int
    {
        return self::BASE_SKILL_LIMIT + max(0, $this->level - 1);
    }

    public function hasSkillCapacity(): bool
    {
        return $this->skills()->count() < $this->maxSkills();
    }

    public function inventoryCapacity(): int
    {
        return 3 + intdiv($this->endurance, 10) + intdiv($this->level, 3);
    }

    /**
     * @return HasOne<Inventory, $this>
     */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function ensureInventory(): Inventory
    {
        return Inventory::forCreature($this);
    }

    public static function maxHpForEndurance(int $endurance): int
    {
        return 50 + ($endurance * 10);
    }

    /**
     * @param  array<string, mixed>  $bonuses
     * @param  list<string>  $keys
     */
    private static function combatBonusFromBonuses(array $bonuses, array $keys): int
    {
        return collect($keys)
            ->sum(fn (string $key): int => is_numeric($bonuses[$key] ?? null) ? (int) $bonuses[$key] : 0);
    }

    public static function applyEquipmentCombatMastery(int $bonus, ?User $user): int
    {
        if ($bonus <= 0 || ! $user || $user->is_bot) {
            return $bonus;
        }

        return max($bonus, (int) round($bonus * $user->equipmentCombatBonusMultiplier()));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'xp' => 'integer',
            'development_points' => 'integer',
            'strength' => 'integer',
            'perception' => 'integer',
            'endurance' => 'integer',
            'charisma' => 'integer',
            'intelligence' => 'integer',
            'agility' => 'integer',
            'luck' => 'integer',
            'current_hp' => 'integer',
            'max_hp' => 'integer',
            'inventory_slots' => 'integer',
            'wins' => 'integer',
            'losses' => 'integer',
            'draws' => 'integer',
            'is_available_for_battle' => 'boolean',
        ];
    }
}
