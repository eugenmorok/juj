<?php

namespace App\Services;

use App\Models\BotProfile;
use App\Models\Creature;
use App\Models\CreatureEquipment;
use App\Models\CreatureSpecies;
use App\Models\EquipmentSlot;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BotGenerationService
{
    private const BASE_SPECIAL_POINTS = 60;

    private const SPECIAL_POINTS_PER_LEVEL = 8;

    private const ECONOMICAL_BASE_SPECIAL_POINTS = 45;

    private const ECONOMICAL_SPECIAL_POINTS_PER_LEVEL = 6;

    /**
     * @return Collection<int, BotProfile>
     */
    public function generateBatch(
        int $count,
        string $style = 'balanced',
        int $minLevel = 1,
        int $maxLevel = 3,
        bool $withCreature = true,
        bool $withEquipment = true,
        bool $withInventory = true,
        bool $withSkills = true,
        bool $loadCreatures = true,
        bool $reloadProfiles = true,
    ): Collection {
        $count = min(50, max(1, $count));
        $minLevel = max(1, $minLevel);
        $maxLevel = max($minLevel, $maxLevel);

        return DB::transaction(function () use ($count, $style, $minLevel, $maxLevel, $withCreature, $withEquipment, $withInventory, $withSkills, $loadCreatures, $reloadProfiles): Collection {
            return collect(range(1, $count))
                ->map(function (int $index) use ($style, $minLevel, $maxLevel, $withCreature, $withEquipment, $withInventory, $withSkills, $loadCreatures, $reloadProfiles): BotProfile {
                    $profile = BotProfile::query()->create([
                        'display_name' => $this->botName($style, $index),
                        'style' => array_key_exists($style, BotProfile::STYLES) ? $style : 'balanced',
                        'is_active' => true,
                        'min_level' => $minLevel,
                        'max_level' => $maxLevel,
                        'spawn_chance' => 65,
                    ]);

                    if ($withCreature) {
                        $this->generateCreature($profile, $withEquipment, $withInventory, $withSkills, $loadCreatures);
                    }

                    return $reloadProfiles
                        ? $profile->refresh()->load('user.creatures')
                        : $profile;
                });
        });
    }

    public function generateCreature(BotProfile $profile, bool $withEquipment = true, bool $withInventory = true, bool $withSkills = true, bool $loadCreature = true): Creature
    {
        return DB::transaction(function () use ($profile, $withEquipment, $withInventory, $withSkills, $loadCreature): Creature {
            $profile->loadMissing('user');
            $species = $this->randomSpecies();
            $level = random_int($profile->min_level, max($profile->min_level, $profile->max_level));
            $special = $this->specialFor($species, $profile->style, $level);
            $maxHp = Creature::maxHpForEndurance($special['endurance']) + (($level - 1) * 5);
            $number = $profile->generated_creatures_count + 1;

            $creature = Creature::query()->create([
                'user_id' => $profile->user_id,
                'creature_type_id' => $species->creature_type_id,
                'creature_species_id' => $species->id,
                'name' => $profile->display_name.' '.$species->name.' #'.$number,
                'level' => $level,
                'xp' => 0,
                'development_points' => 0,
                ...$special,
                'current_hp' => $maxHp,
                'max_hp' => $maxHp,
                'inventory_slots' => Creature::STARTER_INVENTORY_SLOTS,
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'is_available_for_battle' => true,
            ]);

            if ($withInventory) {
                $profile->user->ensureInventory();
                $creature->ensureInventory();
            }

            if ($withSkills) {
                $this->attachSkills($creature, $profile->style);
            }

            if ($withEquipment) {
                $this->generateEquipmentForCreature($creature, $profile->style);
            }

            $profile->forceFill([
                'generated_creatures_count' => $number,
                'last_generated_at' => now(),
            ])->save();

            return $loadCreature
                ? $creature->load(['type', 'species', 'skills', 'equipmentRows.itemInstance.item'])
                : $creature;
        });
    }

    public function generateEquipmentForBot(BotProfile $profile): int
    {
        $profile->loadMissing('user.creatures');

        return $profile->user->creatures
            ->sum(fn (Creature $creature): int => $this->generateEquipmentForCreature($creature, $profile->style));
    }

    public function generateEquipmentForCreature(Creature $creature, string $style = 'balanced'): int
    {
        $creature->loadMissing(['equipmentRows.itemInstance.item', 'user']);
        $occupiedSlots = $creature->equipmentRows->pluck('slot_key')->all();
        $equipmentLimit = $this->equipmentLimit($style);
        $created = 0;

        $items = Item::query()
            ->active()
            ->whereIn('item_type', ['equipment', 'module', 'artifact'])
            ->where('required_level', '<=', $creature->level)
            ->get()
            ->filter(fn (Item $item): bool => $item->canBeUsedBy($creature))
            ->sortByDesc(fn (Item $item): int => $this->itemStyleScore($item, $style))
            ->values();

        foreach ($items as $item) {
            if ($created >= $equipmentLimit) {
                break;
            }

            $slotKeys = $item->equipmentSlotKeys();

            if ($slotKeys === [] || collect($slotKeys)->intersect($occupiedSlots)->isNotEmpty()) {
                continue;
            }

            if (EquipmentSlot::query()->active()->whereIn('code', $slotKeys)->count() !== count($slotKeys)) {
                continue;
            }

            $itemInstance = ItemInstance::query()->create([
                'item_id' => $item->id,
                'owner_user_id' => $creature->user_id,
                'bound_creature_id' => $creature->id,
                'durability' => 100,
                'state' => 'equipped',
            ]);

            foreach ($slotKeys as $slotKey) {
                CreatureEquipment::query()->create([
                    'creature_id' => $creature->id,
                    'item_instance_id' => $itemInstance->id,
                    'slot_key' => $slotKey,
                ]);
                $occupiedSlots[] = $slotKey;
            }

            $created++;
        }

        return $created;
    }

    private function randomSpecies(): CreatureSpecies
    {
        $species = CreatureSpecies::query()
            ->active()
            ->whereHas('type', fn ($query) => $query->active())
            ->inRandomOrder()
            ->first();

        if (! $species) {
            throw ValidationException::withMessages([
                'bot' => 'Нет активных видов сущностей для генерации бота.',
            ]);
        }

        return $species;
    }

    /**
     * @return array<string, int>
     */
    private function specialFor(CreatureSpecies $species, string $style, int $level): array
    {
        $special = [];
        foreach (Creature::SPECIAL_ATTRIBUTES as $attribute) {
            $special[$attribute] = $species->baseSpecialValue($attribute);
        }

        $points = self::specialPointBudget($style, $level);
        $cap = 20 + (($level - 1) * 4);
        $weights = $this->styleWeights($style);
        $attributes = collect($weights)
            ->flatMap(fn (int $weight, string $attribute): array => array_fill(0, $weight, $attribute))
            ->values()
            ->all();

        $guard = 0;
        while ($points > 0 && $guard < 3000) {
            $guard++;
            $attribute = $attributes[array_rand($attributes)];

            if ($special[$attribute] >= $cap) {
                continue;
            }

            $special[$attribute]++;
            $points--;
        }

        return $special;
    }

    public static function specialPointBudget(string $style, int $level): int
    {
        $level = max(1, $level);

        return $style === 'economical'
            ? self::ECONOMICAL_BASE_SPECIAL_POINTS + (($level - 1) * self::ECONOMICAL_SPECIAL_POINTS_PER_LEVEL)
            : self::BASE_SPECIAL_POINTS + (($level - 1) * self::SPECIAL_POINTS_PER_LEVEL);
    }

    /**
     * @return array<string, int>
     */
    private function styleWeights(string $style): array
    {
        return match ($style) {
            'aggressive' => [
                'strength' => 5,
                'perception' => 3,
                'endurance' => 2,
                'charisma' => 1,
                'intelligence' => 2,
                'agility' => 4,
                'luck' => 3,
            ],
            'defensive' => [
                'strength' => 2,
                'perception' => 2,
                'endurance' => 6,
                'charisma' => 1,
                'intelligence' => 3,
                'agility' => 2,
                'luck' => 2,
            ],
            'economical' => [
                'strength' => 2,
                'perception' => 3,
                'endurance' => 3,
                'charisma' => 1,
                'intelligence' => 4,
                'agility' => 3,
                'luck' => 2,
            ],
            'random' => collect(Creature::SPECIAL_ATTRIBUTES)
                ->mapWithKeys(fn (string $attribute): array => [$attribute => random_int(1, 5)])
                ->all(),
            default => [
                'strength' => 3,
                'perception' => 3,
                'endurance' => 3,
                'charisma' => 2,
                'intelligence' => 3,
                'agility' => 3,
                'luck' => 3,
            ],
        };
    }

    private function attachSkills(Creature $creature, string $style): void
    {
        $skills = Skill::query()
            ->active()
            ->get()
            ->filter(fn (Skill $skill): bool => $skill->isAvailableFor($creature))
            ->sortByDesc(fn (Skill $skill): int => $this->skillStyleScore($skill, $style))
            ->take($creature->maxSkills());

        $existingSkillIds = $creature->skills()->pluck('skills.id')->all();
        $now = now();
        $rows = $skills
            ->reject(fn (Skill $skill): bool => in_array($skill->id, $existingSkillIds, true))
            ->map(fn (Skill $skill): array => [
                'creature_id' => $creature->id,
                'skill_id' => $skill->id,
                'cost_paid' => $skill->cost,
                'source' => 'bot-generation',
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table('creature_skills')->insert($rows);
        }
    }

    private function equipmentLimit(string $style): int
    {
        return match ($style) {
            'economical' => 1,
            'aggressive', 'defensive' => 3,
            default => 2,
        };
    }

    private function itemStyleScore(Item $item, string $style): int
    {
        $bonuses = $item->bonuses ?? [];

        $primary = match ($style) {
            'aggressive' => ['damage', 'strength', 'agility', 'crit_chance'],
            'defensive' => ['armor', 'hp', 'endurance'],
            'economical' => ['perception', 'intelligence'],
            default => ['strength', 'perception', 'endurance', 'agility', 'luck'],
        };

        $score = $item->price;
        foreach ($primary as $bonus) {
            $score += abs((int) ($bonuses[$bonus] ?? 0)) * 50;
        }

        return $score;
    }

    private function skillStyleScore(Skill $skill, string $style): int
    {
        return match ($style) {
            'aggressive' => (int) str_contains($skill->code, 'critical') * 100 + (int) str_contains($skill->code, 'quick') * 80 + $skill->cost,
            'defensive' => (int) str_contains($skill->code, 'hide') * 100 + (int) str_contains($skill->code, 'repair') * 80 + $skill->cost,
            'economical' => (int) str_contains($skill->code, 'analysis') * 100 + $skill->cost,
            default => $skill->cost,
        };
    }

    private function botName(string $style, int $index): string
    {
        $label = BotProfile::STYLES[$style] ?? BotProfile::STYLES['balanced'];

        return 'Бот '.$label.' '.Str::upper(Str::random(3)).'-'.$index;
    }
}
