<?php

namespace App\Services;

use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Creature;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BattleRewardService
{
    public const DAILY_FULL_REWARD_LIMIT = 10;

    public const SAME_OPPONENT_FULL_REWARD_LIMIT = 3;

    public function apply(Battle $battle): Battle
    {
        return DB::transaction(function () use ($battle): Battle {
            $battle = Battle::query()
                ->with(['participants.creature.user'])
                ->findOrFail($battle->id);

            if ($battle->participants->contains(fn (BattleParticipant $participant): bool => $participant->reward_xp > 0 || $participant->reward_tokens > 0 || $participant->reward_development_points > 0)) {
                return $battle;
            }

            $participants = $battle->participants->values();

            if ($participants->count() !== 2) {
                return $battle;
            }

            foreach ($participants as $participant) {
                /** @var BattleParticipant $opponent */
                $opponent = $participants->firstWhere('id', '!=', $participant->id);
                $baseRewards = $this->baseRewards($participant, $opponent);
                $multiplier = $this->rewardMultiplier($battle, $participant, $opponent);
                $rewardXp = (int) floor($baseRewards['xp'] * $multiplier);
                $rewardDevelopmentPoints = (int) floor($baseRewards['development_points'] * $multiplier);
                $rewardTokens = (int) floor($baseRewards['tokens'] * $multiplier);

                $user = User::query()
                    ->whereKey($participant->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $creature = Creature::query()
                    ->whereKey($participant->creature_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $levelBefore = $creature->level;

                $user->forceFill([
                    'tokens' => $user->tokens + $rewardTokens,
                ])->save();

                $this->applyCreatureProgress($creature, $participant->result, $rewardXp, $rewardDevelopmentPoints);

                $participant->forceFill([
                    'reward_xp' => $rewardXp,
                    'reward_tokens' => $rewardTokens,
                    'reward_development_points' => $rewardDevelopmentPoints,
                    'reward_multiplier' => $multiplier,
                    'level_before' => $levelBefore,
                    'level_after' => $creature->level,
                ])->save();
            }

            $this->rewardEvent($battle->refresh()->load('participants.creature'));

            return $battle->load(['participants.creature.user', 'events']);
        });
    }

    public static function xpToNextLevel(int $level): int
    {
        return (int) ceil(100 * ($level ** 1.5));
    }

    /**
     * @return array{xp: int, development_points: int, tokens: int}
     */
    private function baseRewards(BattleParticipant $participant, BattleParticipant $opponent): array
    {
        $opponentLevel = max(1, $opponent->level_before);

        return match ($participant->result) {
            BattleParticipant::RESULT_WIN => [
                'xp' => 100 * $opponentLevel,
                'development_points' => 50 * $opponentLevel,
                'tokens' => 50 * $opponentLevel,
            ],
            BattleParticipant::RESULT_DRAW => [
                'xp' => 50 * $opponentLevel,
                'development_points' => 25 * $opponentLevel,
                'tokens' => 25 * $opponentLevel,
            ],
            default => [
                'xp' => 20 * $opponentLevel,
                'development_points' => 0,
                'tokens' => 5 * $opponentLevel,
            ],
        };
    }

    private function rewardMultiplier(Battle $battle, BattleParticipant $participant, BattleParticipant $opponent): float
    {
        $multiplier = 1.0;

        if ($opponent->power_score_before < ($participant->power_score_before * 0.8)) {
            $multiplier *= 0.5;
        }

        if ($this->sameOpponentBattlesToday($battle, $participant, $opponent) >= self::SAME_OPPONENT_FULL_REWARD_LIMIT) {
            $multiplier *= 0.5;
        }

        if ($this->rewardedBattlesToday($battle, $participant) >= self::DAILY_FULL_REWARD_LIMIT) {
            $multiplier *= 0.25;
        }

        return max(0.1, round($multiplier, 2));
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

    private function applyCreatureProgress(Creature $creature, ?string $result, int $rewardXp, int $rewardDevelopmentPoints): void
    {
        $level = $creature->level;
        $xp = $creature->xp + $rewardXp;
        $developmentPoints = $creature->development_points + $rewardDevelopmentPoints;
        $maxHp = $creature->max_hp;
        $currentHp = $creature->current_hp;

        while ($xp >= self::xpToNextLevel($level)) {
            $xp -= self::xpToNextLevel($level);
            $level++;
            $developmentPoints += 10;
            $maxHp += 5;
            $currentHp += 5;
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
            ->map(fn (BattleParticipant $participant): string => "{$participant->creature->name}: +{$participant->reward_xp} XP, +{$participant->reward_development_points} очков развития, +{$participant->reward_tokens} токенов.")
            ->implode(' ');

        $battle->events()->create([
            'round' => 99,
            'event_type' => 'rewards_applied',
            'payload' => [
                'participants' => $battle->participants
                    ->map(fn (BattleParticipant $participant): array => [
                        'creature_id' => $participant->creature_id,
                        'reward_xp' => $participant->reward_xp,
                        'reward_tokens' => $participant->reward_tokens,
                        'reward_development_points' => $participant->reward_development_points,
                        'reward_multiplier' => $participant->reward_multiplier,
                    ])
                    ->values()
                    ->all(),
            ],
            'text_log' => 'Награды начислены. '.$lines,
        ]);
    }
}
