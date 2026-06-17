<?php

namespace App\Services;

use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Creature;
use App\Models\ArenaSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BattleRewardService
{
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
                $rewardTokens = (int) floor($baseRewards['tokens'] * $multiplier);
                $opponentLevel = max(1, $opponent->level_before);

                $user = User::query()
                    ->whereKey($participant->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $creature = Creature::query()
                    ->whereKey($participant->creature_id)
                    ->lockForUpdate()
                    ->firstOrFail();

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

            $this->rewardEvent($battle->refresh()->load('participants.creature'));

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

    private function rewardEvent(Battle $battle): void
    {
        $lines = $battle->participants
            ->map(fn (BattleParticipant $participant): string => "{$participant->creature->name}: +{$participant->reward_xp} XP сущности, +{$participant->reward_player_xp} XP игрока, +{$participant->reward_development_points} очков развития, +{$participant->reward_tokens} токенов, +{$participant->reward_creation_points} очков создания.")
            ->implode(' ');

        $battle->events()->create([
            'round' => 99,
            'event_type' => 'rewards_applied',
            'payload' => [
                'participants' => $battle->participants
                    ->map(fn (BattleParticipant $participant): array => [
                        'creature_id' => $participant->creature_id,
                        'reward_xp' => $participant->reward_xp,
                        'reward_player_xp' => $participant->reward_player_xp,
                        'reward_tokens' => $participant->reward_tokens,
                        'reward_development_points' => $participant->reward_development_points,
                        'reward_creation_points' => $participant->reward_creation_points,
                        'reward_multiplier' => $participant->reward_multiplier,
                    ])
                    ->values()
                    ->all(),
            ],
            'text_log' => 'Награды начислены. '.$lines,
        ]);
    }
}
