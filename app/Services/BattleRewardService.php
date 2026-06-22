<?php

namespace App\Services;

use App\Models\ArenaSetting;
use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Creature;
use App\Models\Inventory;
use App\Models\ItemInstance;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BattleRewardService
{
    private const TROPHY_DROP_CHANCE_PERCENT = 5;

    public function __construct(
        private readonly PlayerProgressService $playerProgress,
    ) {}

    public function apply(Battle $battle): Battle
    {
        return DB::transaction(function () use ($battle): Battle {
            $battle = Battle::query()
                ->with(['participants.creature.user'])
                ->findOrFail($battle->id);

            if ($battle->battle_type === Battle::TYPE_SIMULATION) {
                return $battle;
            }

            $settings = ArenaSetting::current();

            if ($battle->events()->where('event_type', 'rewards_applied')->exists()) {
                return $battle;
            }

            if ($battle->participants->contains(fn (BattleParticipant $participant): bool => $participant->reward_xp > 0 || $participant->reward_player_xp > 0 || $participant->reward_tokens > 0 || $participant->reward_development_points > 0 || $participant->reward_creation_points > 0)) {
                return $battle;
            }

            $participants = $battle->participants->values();

            if ($participants->count() !== 2) {
                return $battle;
            }

            foreach ($participants as $participant) {
                /** @var BattleParticipant $opponent */
                $opponent = $participants->firstWhere('id', '!=', $participant->id);
                $baseRewards = $this->baseRewards($participant, $opponent, $settings);
                $multiplier = $this->rewardMultiplier($battle, $participant, $opponent, $settings);
                $rewardXp = (int) floor($baseRewards['xp'] * $multiplier);
                $rewardDevelopmentPoints = (int) floor($baseRewards['development_points'] * $multiplier);
                $opponentLevel = max(1, $opponent->level_before);

                $user = User::query()
                    ->whereKey($participant->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $creature = Creature::query()
                    ->whereKey($participant->creature_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $rewardXp = (int) floor($rewardXp * $user->creatureXpMultiplier($creature));
                $rewardTokens = (int) floor($baseRewards['tokens'] * $multiplier * $user->tokenRewardMultiplier());

                $levelBefore = $creature->level;
                $playerProgress = $this->playerProgress->applyBattleProgress(
                    $user,
                    $participant->result,
                    $opponentLevel,
                    $multiplier,
                    (int) $battle->seed,
                    (int) $participant->id,
                );

                $user->forceFill([
                    'tokens' => $user->tokens + $rewardTokens,
                ])->save();

                $this->applyCreatureProgress($creature, $participant->result, $rewardXp, $rewardDevelopmentPoints, $settings);

                $participant->forceFill([
                    'reward_xp' => $rewardXp,
                    'reward_player_xp' => $playerProgress['player_xp'],
                    'reward_tokens' => $rewardTokens,
                    'reward_development_points' => $rewardDevelopmentPoints,
                    'reward_creation_points' => $playerProgress['creation_points'],
                    'reward_multiplier' => $multiplier,
                    'level_before' => $levelBefore,
                    'level_after' => $creature->level,
                    'player_level_before' => $playerProgress['level_before'],
                    'player_level_after' => $playerProgress['level_after'],
                ])->save();
            }

            $trophy = $this->tryAwardTrophy($battle->refresh(), $participants);

            $this->rewardEvent($battle->refresh()->load('participants.creature'), $trophy);

            return $battle->load(['participants.creature.user', 'events']);
        });
    }

    public static function xpToNextLevel(int $level, ?ArenaSetting $settings = null): int
    {
        $settings ??= ArenaSetting::current();

        return (int) ceil($settings->xp_to_next_level_base * ($level ** $settings->xp_to_next_level_exponent));
    }

    /**
     * @return array{xp: int, development_points: int, tokens: int}
     */
    private function baseRewards(BattleParticipant $participant, BattleParticipant $opponent, ArenaSetting $settings): array
    {
        $opponentLevel = max(1, $opponent->level_before);

        return match ($participant->result) {
            BattleParticipant::RESULT_WIN => [
                'xp' => $settings->win_xp_per_level * $opponentLevel,
                'development_points' => $settings->win_development_points_per_level * $opponentLevel,
                'tokens' => $settings->win_tokens_per_level * $opponentLevel,
            ],
            BattleParticipant::RESULT_DRAW => [
                'xp' => $settings->draw_xp_per_level * $opponentLevel,
                'development_points' => $settings->draw_development_points_per_level * $opponentLevel,
                'tokens' => $settings->draw_tokens_per_level * $opponentLevel,
            ],
            default => [
                'xp' => $settings->loss_xp_per_level * $opponentLevel,
                'development_points' => $settings->loss_development_points_per_level * $opponentLevel,
                'tokens' => $settings->loss_tokens_per_level * $opponentLevel,
            ],
        };
    }

    private function rewardMultiplier(Battle $battle, BattleParticipant $participant, BattleParticipant $opponent, ArenaSetting $settings): float
    {
        $multiplier = 1.0;

        if ($opponent->power_score_before < ($participant->power_score_before * $settings->weak_opponent_power_ratio)) {
            $multiplier *= $settings->weak_opponent_reward_multiplier;
        }

        if ($settings->same_opponent_daily_limit > 0 && $this->sameOpponentBattlesToday($battle, $participant, $opponent) >= $settings->same_opponent_daily_limit) {
            $multiplier *= $settings->same_opponent_reward_multiplier;
        }

        if ($settings->daily_full_reward_limit > 0 && $this->rewardedBattlesToday($battle, $participant) >= $settings->daily_full_reward_limit) {
            $multiplier *= $settings->daily_limit_reward_multiplier;
        }

        return max($settings->minimum_reward_multiplier, round($multiplier, 2));
    }

    private function sameOpponentBattlesToday(Battle $battle, BattleParticipant $participant, BattleParticipant $opponent): int
    {
        return BattleParticipant::query()
            ->where('battle_id', '!=', $battle->id)
            ->where('creature_id', $participant->creature_id)
            ->whereHas('battle', fn ($query) => $query
                ->whereDate('started_at', today())
                ->whereHas('participants', fn ($participants) => $participants->where('creature_id', $opponent->creature_id)))
            ->count();
    }

    private function rewardedBattlesToday(Battle $battle, BattleParticipant $participant): int
    {
        return BattleParticipant::query()
            ->where('battle_id', '!=', $battle->id)
            ->where('user_id', $participant->user_id)
            ->where(function ($query): void {
                $query
                    ->where('reward_xp', '>', 0)
                    ->orWhere('reward_tokens', '>', 0)
                    ->orWhere('reward_development_points', '>', 0);
            })
            ->whereHas('battle', fn ($query) => $query->whereDate('started_at', today()))
            ->count();
    }

    private function applyCreatureProgress(Creature $creature, ?string $result, int $rewardXp, int $rewardDevelopmentPoints, ArenaSetting $settings): void
    {
        $level = $creature->level;
        $xp = $creature->xp + $rewardXp;
        $developmentPoints = $creature->development_points + $rewardDevelopmentPoints;
        $maxHp = $creature->max_hp;
        $currentHp = $creature->current_hp;

        while ($xp >= self::xpToNextLevel($level, $settings)) {
            $xp -= self::xpToNextLevel($level, $settings);
            $level++;
            $developmentPoints += $settings->level_up_development_points;
            $maxHp += $settings->level_up_hp_bonus;
            $currentHp += $settings->level_up_hp_bonus;
        }

        $stats = match ($result) {
            BattleParticipant::RESULT_WIN => ['wins' => $creature->wins + 1],
            BattleParticipant::RESULT_DRAW => ['draws' => $creature->draws + 1],
            default => ['losses' => $creature->losses + 1],
        };

        $creature->forceFill([
            'level' => $level,
            'xp' => $xp,
            'development_points' => $developmentPoints,
            'max_hp' => $maxHp,
            'current_hp' => min($currentHp, $maxHp),
            ...$stats,
        ])->save();
    }

    private function rewardEvent(Battle $battle, ?array $trophy = null): void
    {
        $lines = $battle->participants
            ->map(function (BattleParticipant $participant): string {
                $doctrinePoints = $this->rewardDoctrinePoints($participant);
                $perkPoints = $this->rewardPerkPoints($participant);
                $doctrineText = $doctrinePoints > 0 ? ", +{$doctrinePoints} очков доктрины" : '';
                $perkText = $perkPoints > 0 ? ", +{$perkPoints} очков перков" : '';

                return "{$participant->creature->name}: +{$participant->reward_xp} XP сущности, +{$participant->reward_player_xp} XP игрока, +{$participant->reward_development_points} очков развития, +{$participant->reward_tokens} токенов, +{$participant->reward_creation_points} очков создания{$doctrineText}{$perkText}.";
            })
            ->implode(' ');

        $trophyText = $this->trophyText($trophy);
        $payload = [
            'participants' => $battle->participants
                ->map(fn (BattleParticipant $participant): array => [
                    'creature_id' => $participant->creature_id,
                    'reward_xp' => $participant->reward_xp,
                    'reward_player_xp' => $participant->reward_player_xp,
                    'reward_tokens' => $participant->reward_tokens,
                    'reward_development_points' => $participant->reward_development_points,
                    'reward_creation_points' => $participant->reward_creation_points,
                    'reward_doctrine_points' => $this->rewardDoctrinePoints($participant),
                    'reward_perk_points' => $this->rewardPerkPoints($participant),
                    'reward_multiplier' => $participant->reward_multiplier,
                ])
                ->values()
                ->all(),
        ];

        if ($trophy !== null) {
            $payload['trophy'] = $trophy;
        }

        $battle->events()->create([
            'round' => 99,
            'event_type' => 'rewards_applied',
            'payload' => $payload,
            'text_log' => 'Награды начислены. '.$lines.$trophyText,
        ]);
    }

    /**
     * @param  Collection<int, BattleParticipant>  $participants
     * @return array<string, mixed>|null
     */
    private function tryAwardTrophy(Battle $battle, Collection $participants): ?array
    {
        if ($battle->is_draw) {
            return null;
        }

        $winner = $participants->firstWhere('result', BattleParticipant::RESULT_WIN)
            ?? $participants->firstWhere('creature_id', $battle->winner_creature_id);
        $loser = $participants->firstWhere('result', BattleParticipant::RESULT_LOSS)
            ?? $participants->first(fn (BattleParticipant $participant): bool => $participant->id !== $winner?->id);

        if (! $winner || ! $loser || ! $winner->creature_id || ! $loser->creature_id) {
            return null;
        }

        $roll = $this->trophyRoll($battle, $winner, $loser);

        if ($roll > self::TROPHY_DROP_CHANCE_PERCENT) {
            return null;
        }

        $winnerCreature = Creature::query()
            ->whereKey($winner->creature_id)
            ->lockForUpdate()
            ->firstOrFail();
        $winnerInventory = Inventory::forCreature($winnerCreature)->load('inventoryItems');

        if (! $winnerInventory->hasFreeSlot()) {
            return [
                'awarded' => false,
                'reason' => 'winner_inventory_full',
                'roll' => $roll,
                'chance_percent' => self::TROPHY_DROP_CHANCE_PERCENT,
                'winner_creature_id' => $winner->creature_id,
                'loser_creature_id' => $loser->creature_id,
            ];
        }

        $candidates = $this->loserTrophyCandidates($loser);

        if ($candidates->isEmpty()) {
            return [
                'awarded' => false,
                'reason' => 'loser_has_no_items',
                'roll' => $roll,
                'chance_percent' => self::TROPHY_DROP_CHANCE_PERCENT,
                'winner_creature_id' => $winner->creature_id,
                'loser_creature_id' => $loser->creature_id,
            ];
        }

        /** @var ItemInstance $itemInstance */
        $itemInstance = $candidates[$this->trophyIndex($battle, $winner, $loser, $candidates->count())];
        $slotKeys = $itemInstance->equipmentRows
            ->where('creature_id', $loser->creature_id)
            ->pluck('slot_key')
            ->values()
            ->all();

        $itemInstance->equipmentRows()
            ->where('creature_id', $loser->creature_id)
            ->delete();
        $itemInstance->inventoryItem?->delete();

        $inventoryItem = $winnerInventory->addItemInstance($itemInstance);

        return [
            'awarded' => true,
            'roll' => $roll,
            'chance_percent' => self::TROPHY_DROP_CHANCE_PERCENT,
            'winner_creature_id' => $winner->creature_id,
            'winner_creature_name' => $winnerCreature->name,
            'loser_creature_id' => $loser->creature_id,
            'item_instance_id' => $itemInstance->id,
            'item_id' => $itemInstance->item_id,
            'item_name' => $itemInstance->item?->name,
            'source_slot_keys' => $slotKeys,
            'inventory_item_id' => $inventoryItem->id,
            'inventory_slot_number' => $inventoryItem->slot_number,
        ];
    }

    /**
     * @return Collection<int, ItemInstance>
     */
    private function loserTrophyCandidates(BattleParticipant $loser): Collection
    {
        return ItemInstance::query()
            ->with(['item', 'inventoryItem.inventory', 'equipmentRows.slot'])
            ->whereIn('state', ['stored', 'equipped'])
            ->where('bound_creature_id', $loser->creature_id)
            ->where(function ($query) use ($loser): void {
                $query
                    ->whereHas('equipmentRows', fn ($equipment) => $equipment->where('creature_id', $loser->creature_id))
                    ->orWhereHas('inventoryItem.inventory', fn ($inventory) => $inventory
                        ->where('inventory_type', Inventory::TYPE_CREATURE)
                        ->where('creature_id', $loser->creature_id));
            })
            ->orderBy('id')
            ->get()
            ->values();
    }

    private function trophyRoll(Battle $battle, BattleParticipant $winner, BattleParticipant $loser): int
    {
        return $this->deterministicNumber($battle, $winner, $loser, 'trophy-roll', 100) + 1;
    }

    private function trophyIndex(Battle $battle, BattleParticipant $winner, BattleParticipant $loser, int $candidateCount): int
    {
        return $this->deterministicNumber($battle, $winner, $loser, 'trophy-item', $candidateCount);
    }

    private function deterministicNumber(Battle $battle, BattleParticipant $winner, BattleParticipant $loser, string $salt, int $modulo): int
    {
        $hash = sprintf('%u', crc32(implode('|', [
            $battle->seed,
            $battle->id,
            $winner->id,
            $winner->creature_id,
            $loser->id,
            $loser->creature_id,
            $salt,
        ])));

        return ((int) $hash) % max(1, $modulo);
    }

    /**
     * @param  array<string, mixed>|null  $trophy
     */
    private function trophyText(?array $trophy): string
    {
        if ($trophy === null) {
            return '';
        }

        if (($trophy['awarded'] ?? false) === true) {
            return ' Трофей круга: '.$trophy['winner_creature_name'].' получает предмет «'.$trophy['item_name'].'» в ячейку '.$trophy['inventory_slot_number'].'.';
        }

        if (($trophy['reason'] ?? null) === 'winner_inventory_full') {
            return ' Трофей круга выпал, но инвентарь победителя заполнен.';
        }

        if (($trophy['reason'] ?? null) === 'loser_has_no_items') {
            return ' Трофей круга выпал, но у проигравшего не нашлось переносимых предметов.';
        }

        return '';
    }

    private function rewardDoctrinePoints(BattleParticipant $participant): int
    {
        if ($participant->player_level_before === null || $participant->player_level_after === null) {
            return 0;
        }

        return max(
            0,
            User::doctrinePointsEarnedForLevel((int) $participant->player_level_after)
            - User::doctrinePointsEarnedForLevel((int) $participant->player_level_before),
        );
    }

    private function rewardPerkPoints(BattleParticipant $participant): int
    {
        if ($participant->player_level_before === null || $participant->player_level_after === null) {
            return 0;
        }

        return max(
            0,
            User::perkPointsEarnedForLevel((int) $participant->player_level_after)
            - User::perkPointsEarnedForLevel((int) $participant->player_level_before),
        );
    }
}
