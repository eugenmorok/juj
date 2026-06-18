<?php

namespace App\Models;

use Database\Factories\ArenaSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'is_active',
    'battle_background_image',
    'win_xp_per_level',
    'draw_xp_per_level',
    'loss_xp_per_level',
    'win_development_points_per_level',
    'draw_development_points_per_level',
    'loss_development_points_per_level',
    'win_tokens_per_level',
    'draw_tokens_per_level',
    'loss_tokens_per_level',
    'xp_to_next_level_base',
    'xp_to_next_level_exponent',
    'level_up_development_points',
    'level_up_hp_bonus',
    'weak_opponent_power_ratio',
    'weak_opponent_reward_multiplier',
    'same_opponent_daily_limit',
    'same_opponent_reward_multiplier',
    'daily_full_reward_limit',
    'daily_limit_reward_multiplier',
    'minimum_reward_multiplier',
    'matchmaking_level_difference',
    'matchmaking_power_score_difference',
    'power_score_level_weight',
    'power_score_skill_weight',
    'power_score_equipment_weight',
    'daily_battle_limit',
    'inventory_slot_base_cost',
    'inventory_slot_step_cost',
    'max_purchased_inventory_slots',
    'updated_by_id',
])]
class ArenaSetting extends Model
{
    /** @use HasFactory<ArenaSettingFactory> */
    use HasFactory;

    public const DEFAULT_NAME = 'MVP balance';

    public const BALANCE_FIELDS = [
        'name',
        'is_active',
        'battle_background_image',
        'win_xp_per_level',
        'draw_xp_per_level',
        'loss_xp_per_level',
        'win_development_points_per_level',
        'draw_development_points_per_level',
        'loss_development_points_per_level',
        'win_tokens_per_level',
        'draw_tokens_per_level',
        'loss_tokens_per_level',
        'xp_to_next_level_base',
        'xp_to_next_level_exponent',
        'level_up_development_points',
        'level_up_hp_bonus',
        'weak_opponent_power_ratio',
        'weak_opponent_reward_multiplier',
        'same_opponent_daily_limit',
        'same_opponent_reward_multiplier',
        'daily_full_reward_limit',
        'daily_limit_reward_multiplier',
        'minimum_reward_multiplier',
        'matchmaking_level_difference',
        'matchmaking_power_score_difference',
        'power_score_level_weight',
        'power_score_skill_weight',
        'power_score_equipment_weight',
        'daily_battle_limit',
        'inventory_slot_base_cost',
        'inventory_slot_step_cost',
        'max_purchased_inventory_slots',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'name' => self::DEFAULT_NAME,
            'is_active' => true,
            'battle_background_image' => 'game-assets/arena/industrial-fantasy-arena.webp',
            'win_xp_per_level' => 100,
            'draw_xp_per_level' => 50,
            'loss_xp_per_level' => 20,
            'win_development_points_per_level' => 50,
            'draw_development_points_per_level' => 25,
            'loss_development_points_per_level' => 0,
            'win_tokens_per_level' => 50,
            'draw_tokens_per_level' => 25,
            'loss_tokens_per_level' => 5,
            'xp_to_next_level_base' => 100,
            'xp_to_next_level_exponent' => 1.5,
            'level_up_development_points' => 10,
            'level_up_hp_bonus' => 5,
            'weak_opponent_power_ratio' => 0.8,
            'weak_opponent_reward_multiplier' => 0.5,
            'same_opponent_daily_limit' => 3,
            'same_opponent_reward_multiplier' => 0.5,
            'daily_full_reward_limit' => 10,
            'daily_limit_reward_multiplier' => 0.25,
            'minimum_reward_multiplier' => 0.1,
            'matchmaking_level_difference' => 2,
            'matchmaking_power_score_difference' => 0,
            'power_score_level_weight' => 10,
            'power_score_skill_weight' => 1,
            'power_score_equipment_weight' => 1,
            'daily_battle_limit' => 0,
            'inventory_slot_base_cost' => 100,
            'inventory_slot_step_cost' => 25,
            'max_purchased_inventory_slots' => 50,
        ];
    }

    public static function current(): self
    {
        $setting = self::query()
            ->active()
            ->latest('id')
            ->first();

        if ($setting) {
            return $setting;
        }

        return self::query()->create(self::defaults());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * @return HasMany<BalanceChangeLog, $this>
     */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(BalanceChangeLog::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        static::saving(function (ArenaSetting $setting): void {
            if (auth()->check() && $setting->isDirty(self::BALANCE_FIELDS)) {
                $setting->updated_by_id = auth()->id();
            }
        });

        static::updated(function (ArenaSetting $setting): void {
            $changedFields = collect(self::BALANCE_FIELDS)
                ->filter(fn (string $field): bool => $setting->wasChanged($field))
                ->values()
                ->all();

            if ($changedFields === []) {
                return;
            }

            BalanceChangeLog::query()->create([
                'arena_setting_id' => $setting->id,
                'user_id' => $setting->updated_by_id ?: auth()->id(),
                'changed_fields' => $changedFields,
                'before_values' => collect($changedFields)
                    ->mapWithKeys(fn (string $field): array => [$field => $setting->getOriginal($field)])
                    ->all(),
                'after_values' => collect($changedFields)
                    ->mapWithKeys(fn (string $field): array => [$field => $setting->{$field}])
                    ->all(),
            ]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'win_xp_per_level' => 'integer',
            'draw_xp_per_level' => 'integer',
            'loss_xp_per_level' => 'integer',
            'win_development_points_per_level' => 'integer',
            'draw_development_points_per_level' => 'integer',
            'loss_development_points_per_level' => 'integer',
            'win_tokens_per_level' => 'integer',
            'draw_tokens_per_level' => 'integer',
            'loss_tokens_per_level' => 'integer',
            'xp_to_next_level_base' => 'integer',
            'xp_to_next_level_exponent' => 'float',
            'level_up_development_points' => 'integer',
            'level_up_hp_bonus' => 'integer',
            'weak_opponent_power_ratio' => 'float',
            'weak_opponent_reward_multiplier' => 'float',
            'same_opponent_daily_limit' => 'integer',
            'same_opponent_reward_multiplier' => 'float',
            'daily_full_reward_limit' => 'integer',
            'daily_limit_reward_multiplier' => 'float',
            'minimum_reward_multiplier' => 'float',
            'matchmaking_level_difference' => 'integer',
            'matchmaking_power_score_difference' => 'integer',
            'power_score_level_weight' => 'float',
            'power_score_skill_weight' => 'float',
            'power_score_equipment_weight' => 'float',
            'daily_battle_limit' => 'integer',
            'inventory_slot_base_cost' => 'integer',
            'inventory_slot_step_cost' => 'integer',
            'max_purchased_inventory_slots' => 'integer',
            'updated_by_id' => 'integer',
        ];
    }
}
