<?php

namespace App\Services;

use App\Models\ArenaSetting;
use App\Models\Battle;
use App\Models\BattleEvent;
use App\Models\BattleParticipant;
use App\Models\Creature;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BattleEngine
{
    private const MAX_ROUNDS = 20;

    private int $rngState = 1;

    private ?ArenaSetting $settings = null;

    public function __construct(
        private readonly PowerScoreService $powerScore,
        private readonly BattleArenaService $battleArenas,
    ) {}

    public function run(
        Creature $leftCreature,
        Creature $rightCreature,
        ?int $seed = null,
        string $battleType = Battle::TYPE_RANKED,
        ?User $initiator = null,
    ): Battle {
        $seed ??= random_int(1, 2_147_483_646);
        $this->rngState = max(1, $seed);
        $this->settings = ArenaSetting::current();
        $arena = $this->battleArenas->selectForSeed($seed);

        return DB::transaction(function () use ($leftCreature, $rightCreature, $seed, $battleType, $initiator, $arena): Battle {
            $leftCreature->loadMissing(['user', 'skills', 'equipmentRows.itemInstance.item']);
            $rightCreature->loadMissing(['user', 'skills', 'equipmentRows.itemInstance.item']);

            $battle = Battle::query()->create([
                'initiator_user_id' => $initiator?->id,
                ...$this->battleArenas->snapshot($arena),
                'battle_type' => $battleType,
                'status' => Battle::STATUS_RUNNING,
                'is_draw' => false,
                'seed' => $seed,
                'started_at' => now(),
            ]);

            $left = $this->combatant($leftCreature, 'challenger', $battle->arena_effects);
            $right = $this->combatant($rightCreature, 'opponent', $battle->arena_effects);

            $leftParticipant = $this->participant($battle, $left);
            $rightParticipant = $this->participant($battle, $right);

            $this->event($battle, 0, 'battle_started', null, null, [
                'seed' => $seed,
                'left_power' => $leftParticipant->power_score_before,
                'right_power' => $rightParticipant->power_score_before,
                'arena_name' => $battle->arena_name,
                'arena_effects' => $battle->arena_effects,
            ], "Бой начинается на арене «{$battle->arena_name}»: {$leftCreature->name} против {$rightCreature->name}. Seed: {$seed}.");

            for ($round = 1; $round <= self::MAX_ROUNDS; $round++) {
                $this->event($battle, $round, 'round_started', null, null, [], "Раунд {$round}.");

                if ($this->leftActsFirst($left, $right)) {
                    $this->performAttack($battle, $left, $right, $round);

                    if ($this->hasBattleEnded($left, $right)) {
                        break;
                    }

                    $this->performAttack($battle, $right, $left, $round);
                } else {
                    $this->performAttack($battle, $right, $left, $round);

                    if ($this->hasBattleEnded($left, $right)) {
                        break;
                    }

                    $this->performAttack($battle, $left, $right, $round);
                }

                if ($this->hasBattleEnded($left, $right)) {
                    break;
                }
            }

            [$winnerId, $isDraw] = $this->outcome($left, $right);
            $leftResult = $this->participantResult($leftCreature, $winnerId, $isDraw);
            $rightResult = $this->participantResult($rightCreature, $winnerId, $isDraw);

            $battle->forceFill([
                'winner_creature_id' => $winnerId,
                'is_draw' => $isDraw,
                'status' => Battle::STATUS_FINISHED,
                'finished_at' => now(),
            ])->save();

            $leftParticipant->forceFill([
                'result' => $leftResult,
                'hp_after' => max(0, $left['hp']),
                'level_after' => $leftCreature->level,
            ])->save();

            $rightParticipant->forceFill([
                'result' => $rightResult,
                'hp_after' => max(0, $right['hp']),
                'level_after' => $rightCreature->level,
            ])->save();

            $summary = $isDraw
                ? 'Бой завершился ничьей.'
                : 'Победитель: '.($winnerId === $leftCreature->id ? $leftCreature->name : $rightCreature->name).'.';

            $this->event($battle, self::MAX_ROUNDS + 1, 'battle_finished', null, null, [
                'winner_creature_id' => $winnerId,
                'is_draw' => $isDraw,
                'left_hp' => max(0, $left['hp']),
                'right_hp' => max(0, $right['hp']),
            ], $summary);

            return $battle->load(['participants.creature.user', 'events']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function combatant(Creature $creature, string $side, ?array $arenaEffects = null): array
    {
        $bonuses = $creature->equipmentBonuses();
        $special = $creature->effectiveSpecialValues($this->settings);

        foreach (($creature->user?->battleSupportBonus() ?? []) as $attribute => $value) {
            $special[$attribute] = ($special[$attribute] ?? 0) + $value;
        }

        $special = $this->battleArenas->applyEffects($special, $arenaEffects);
        $maxHp = max(1, 50 + ($special['endurance'] * 10) + ($creature->level * 5) + (int) ($bonuses['hp'] ?? 0));
        $damageBase = Creature::damageFromSpecial($special);
        $defenseBase = Creature::defenseFromSpecial($special);
        $damageBonus = Creature::damageBonusFromBonuses($bonuses);
        $defenseBonus = Creature::defenseBonusFromBonuses($bonuses);

        return [
            'creature' => $creature,
            'side' => $side,
            'special' => $special,
            'bonuses' => $bonuses,
            'combat' => [
                'damage_base' => $damageBase,
                'damage_bonus' => $damageBonus,
                'damage' => max(1, $damageBase + $damageBonus),
                'defense_base' => $defenseBase,
                'defense_bonus' => $defenseBonus,
                'defense' => max(0, $defenseBase + $defenseBonus),
            ],
            'skills' => $creature->skills->pluck('code')->all(),
            'max_hp' => $maxHp,
            'hp' => $maxHp,
        ];
    }

    /**
     * @param  array<string, mixed>  $combatant
     */
    private function participant(Battle $battle, array $combatant): BattleParticipant
    {
        /** @var Creature $creature */
        $creature = $combatant['creature'];

        return $battle->participants()->create([
            'user_id' => $creature->user_id,
            'creature_id' => $creature->id,
            'is_bot' => (bool) $creature->user?->is_bot,
            'side' => $combatant['side'],
            'power_score_before' => $this->powerScore->calculate($creature, $this->settings),
            'hp_before' => $combatant['max_hp'],
            'hp_after' => $combatant['hp'],
            'level_before' => $creature->level,
            'level_after' => $creature->level,
        ]);
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function leftActsFirst(array $left, array $right): bool
    {
        $leftInitiative = $this->initiative($left);
        $rightInitiative = $this->initiative($right);

        if ($leftInitiative === $rightInitiative) {
            $leftInitiative += $this->roll(0, 1);
        }

        return $leftInitiative >= $rightInitiative;
    }

    /**
     * @param  array<string, mixed>  $combatant
     */
    private function initiative(array $combatant): int
    {
        $bonus = in_array('quick-strike', $combatant['skills'], true) ? 5 : 0;

        return (int) $combatant['special']['agility']
            + (int) $combatant['special']['perception']
            + $bonus
            + $this->roll(1, 20);
    }

    /**
     * @param  array<string, mixed>  $attacker
     * @param  array<string, mixed>  $defender
     */
    private function performAttack(Battle $battle, array &$attacker, array &$defender, int $round): void
    {
        if ($attacker['hp'] <= 0 || $defender['hp'] <= 0) {
            return;
        }

        $this->applySelfRepair($battle, $attacker, $round);

        $hitChance = $this->clamp(
            (int) round(
                58
                + ((int) $attacker['special']['perception'] * 1.45)
                + ((int) $attacker['special']['intelligence'] * 0.45)
                - ((int) $defender['special']['agility'] * 0.9)
                - ((int) $defender['special']['intelligence'] * 0.2)
            ),
            20,
            95,
        );

        $hitRoll = $this->roll(1, 100);

        /** @var Creature $attackerCreature */
        $attackerCreature = $attacker['creature'];
        /** @var Creature $defenderCreature */
        $defenderCreature = $defender['creature'];

        if ($hitRoll > $hitChance) {
            $this->event($battle, $round, 'miss', $attackerCreature, $defenderCreature, [
                'hit_chance' => $hitChance,
                'roll' => $hitRoll,
            ], "{$attackerCreature->name} промахивается по {$defenderCreature->name}.");

            return;
        }

        $damageBreakdown = $this->damage($attacker, $defender);
        $damage = $damageBreakdown['damage'];
        $critChance = $this->critChance($attacker);
        $critRoll = $this->roll(1, 100);
        $isCrit = $critRoll <= $critChance;

        if ($isCrit) {
            $damage = (int) ceil($damage * 1.5);
        }

        if (in_array('venomous-sting', $attacker['skills'], true)) {
            $damage += 3;
        }

        [$damage, $pveBalanceMultiplier] = $this->applyPveDamageBalance($attacker, $defender, $damage);
        [$damage, $mitigated] = $this->applyComposureMitigation($damage, $defender);

        $defender['hp'] = max(0, $defender['hp'] - $damage);

        $suffix = $isCrit ? ' Критический удар.' : '';
        $suffix .= $mitigated ? ' Собранность цели смягчила удар.' : '';
        $this->event($battle, $round, $isCrit ? 'critical_hit' : 'hit', $attackerCreature, $defenderCreature, [
            'damage' => $damage,
            'composure_mitigation' => $mitigated,
            'hit_chance' => $hitChance,
            'hit_roll' => $hitRoll,
            'crit_chance' => $critChance,
            'crit_roll' => $critRoll,
            'pve_balance_multiplier' => $pveBalanceMultiplier,
            'attack_rating' => $damageBreakdown['attack_rating'],
            'defense_rating' => $damageBreakdown['defense_rating'],
            'damage_rating' => $attacker['combat']['damage'],
            'damage_equipment_bonus' => $attacker['combat']['damage_bonus'],
            'defense_equipment_bonus' => $defender['combat']['defense_bonus'],
            'target_hp' => $defender['hp'],
        ], "{$attackerCreature->name} наносит {$damage} урона. {$defenderCreature->name}: {$defender['hp']} HP.{$suffix}");
    }

    /**
     * @param  array<string, mixed>  $attacker
     * @param  array<string, mixed>  $defender
     * @return array{0: int, 1: float}
     */
    private function applyPveDamageBalance(array $attacker, array $defender, int $damage): array
    {
        $attackerIsBot = (bool) $attacker['creature']->user?->is_bot;
        $defenderIsBot = (bool) $defender['creature']->user?->is_bot;
        $settings = $this->settings ?? ArenaSetting::current();
        $multiplier = match (true) {
            $attackerIsBot && ! $defenderIsBot => $settings->botDamageMultiplier(),
            ! $attackerIsBot && $defenderIsBot => $settings->playerVsBotDamageMultiplier(),
            default => 1.0,
        };

        return [max(1, (int) round($damage * $multiplier)), $multiplier];
    }

    /**
     * @param  array<string, mixed>  $combatant
     */
    private function applySelfRepair(Battle $battle, array &$combatant, int $round): void
    {
        if ($round % 3 !== 0 || ! in_array('self-repair', $combatant['skills'], true) || $combatant['hp'] >= $combatant['max_hp']) {
            return;
        }

        /** @var Creature $creature */
        $creature = $combatant['creature'];
        $heal = max(1, (int) floor($combatant['max_hp'] * 0.05));
        $combatant['hp'] = min($combatant['max_hp'], $combatant['hp'] + $heal);

        $this->event($battle, $round, 'self_repair', $creature, null, [
            'heal' => $heal,
            'hp' => $combatant['hp'],
        ], "{$creature->name} восстанавливает {$heal} HP.");
    }

    /**
     * @param  array<string, mixed>  $defender
     * @return array{0: int, 1: bool}
     */
    private function applyComposureMitigation(int $damage, array $defender): array
    {
        $chance = $this->clamp(
            (int) round(4 + ((int) $defender['special']['charisma'] * 1.1) + ((int) $defender['special']['intelligence'] * 0.35)),
            5,
            40,
        );

        if ($this->roll(1, 100) > $chance) {
            return [$damage, false];
        }

        return [max(1, (int) floor($damage * 0.82)), true];
    }

    /**
     * @param  array<string, mixed>  $attacker
     * @param  array<string, mixed>  $defender
     * @return array{damage: int, attack_rating: int, defense_rating: int}
     */
    private function damage(array $attacker, array $defender): array
    {
        $attackRating = (int) $attacker['combat']['damage'] + $this->roll(1, 6);
        $defenseRating = (int) $defender['combat']['defense'];

        if (in_array('thick-hide', $defender['skills'], true)) {
            $defenseRating = (int) round($defenseRating * 1.1);
        }

        if (
            in_array('weakness-analysis', $attacker['skills'], true)
            && (int) $attacker['special']['intelligence'] > (int) $defender['special']['intelligence']
        ) {
            $attackRating = (int) round($attackRating * 1.1);
        }

        return [
            'damage' => max(1, $attackRating - $defenseRating),
            'attack_rating' => $attackRating,
            'defense_rating' => $defenseRating,
        ];
    }

    /**
     * @param  array<string, mixed>  $attacker
     */
    private function critChance(array $attacker): int
    {
        $chance = ((int) $attacker['special']['luck'] * 0.8)
            + ((int) $attacker['special']['perception'] * 0.12)
            + (int) ($attacker['bonuses']['crit_chance'] ?? 0);

        if (in_array('critical-instinct', $attacker['skills'], true)) {
            $chance += 8;
        }

        return $this->clamp((int) round($chance), 3, 50);
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function hasBattleEnded(array $left, array $right): bool
    {
        return $left['hp'] <= 0 || $right['hp'] <= 0;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return array{0: int|null, 1: bool}
     */
    private function outcome(array $left, array $right): array
    {
        /** @var Creature $leftCreature */
        $leftCreature = $left['creature'];
        /** @var Creature $rightCreature */
        $rightCreature = $right['creature'];

        if ($left['hp'] <= 0 && $right['hp'] <= 0) {
            return [null, true];
        }

        if ($left['hp'] <= 0) {
            return [$rightCreature->id, false];
        }

        if ($right['hp'] <= 0) {
            return [$leftCreature->id, false];
        }

        $leftRate = $left['hp'] / max(1, $left['max_hp']);
        $rightRate = $right['hp'] / max(1, $right['max_hp']);

        if (abs($leftRate - $rightRate) < 0.05) {
            return [null, true];
        }

        return $leftRate > $rightRate
            ? [$leftCreature->id, false]
            : [$rightCreature->id, false];
    }

    private function participantResult(Creature $creature, ?int $winnerId, bool $isDraw): string
    {
        if ($isDraw) {
            return BattleParticipant::RESULT_DRAW;
        }

        return $creature->id === $winnerId
            ? BattleParticipant::RESULT_WIN
            : BattleParticipant::RESULT_LOSS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function event(
        Battle $battle,
        int $round,
        string $eventType,
        ?Creature $actor,
        ?Creature $target,
        array $payload,
        string $text,
    ): BattleEvent {
        return $battle->events()->create([
            'round' => $round,
            'event_type' => $eventType,
            'actor_creature_id' => $actor?->id,
            'target_creature_id' => $target?->id,
            'payload' => $payload,
            'text_log' => $text,
        ]);
    }

    private function roll(int $min, int $max): int
    {
        $this->rngState = (int) ((1_103_515_245 * $this->rngState + 12_345) % 2_147_483_647);

        return $min + ($this->rngState % (($max - $min) + 1));
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return min($max, max($min, $value));
    }
}
