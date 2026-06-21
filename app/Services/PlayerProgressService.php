<?php

namespace App\Services;

use App\Models\BattleParticipant;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlayerProgressService
{
    private const XP_TO_NEXT_LEVEL_BASE = 150;

    private const XP_TO_NEXT_LEVEL_EXPONENT = 1.6;

    private const WIN_XP_PER_OPPONENT_LEVEL = 40;

    private const DRAW_XP_PER_OPPONENT_LEVEL = 20;

    private const LOSS_XP_PER_OPPONENT_LEVEL = 8;

    public static function xpToNextLevel(int $level): int
    {
        return (int) ceil(self::XP_TO_NEXT_LEVEL_BASE * ($level ** self::XP_TO_NEXT_LEVEL_EXPONENT));
    }

    /**
     * @return array{player_xp: int, creation_points: int, doctrine_points: int, perk_points: int, level_before: int, level_after: int}
     */
    public function applyBattleProgress(
        User $user,
        ?string $result,
        int $opponentLevel,
        float $rewardMultiplier,
        int $battleSeed,
        int $participantId,
    ): array {
        if ($user->is_bot) {
            return [
                'player_xp' => 0,
                'creation_points' => 0,
                'doctrine_points' => 0,
                'perk_points' => 0,
                'level_before' => $user->level,
                'level_after' => $user->level,
            ];
        }

        $levelBefore = $user->level;
        $playerXp = $this->battleXp($result, $opponentLevel, $rewardMultiplier);
        $creationPoints = $this->creationPointDrop($user, $result, $opponentLevel, $rewardMultiplier, $battleSeed, $participantId);
        $level = $user->level;
        $xp = $user->xp + $playerXp;

        while ($xp >= self::xpToNextLevel($level)) {
            $xp -= self::xpToNextLevel($level);
            $level++;
        }

        $doctrinePoints = max(
            0,
            User::doctrinePointsEarnedForLevel($level) - User::doctrinePointsEarnedForLevel($levelBefore),
        );
        $perkPoints = max(
            0,
            User::perkPointsEarnedForLevel($level) - User::perkPointsEarnedForLevel($levelBefore),
        );

        $user->forceFill([
            'level' => $level,
            'xp' => $xp,
            'creature_creation_points' => $user->creature_creation_points + $creationPoints,
            'doctrine_points' => $user->doctrine_points + $doctrinePoints,
            'perk_points' => $user->perk_points + $perkPoints,
        ])->save();

        Inventory::forUser($user->refresh())->syncSlots();

        return [
            'player_xp' => $playerXp,
            'creation_points' => $creationPoints,
            'doctrine_points' => $doctrinePoints,
            'perk_points' => $perkPoints,
            'level_before' => $levelBefore,
            'level_after' => $level,
        ];
    }

    /**
     * @return array{spent_xp: int, gained_points: int}
     */
    public function convertXpToCreationPoints(User $user, int $points): array
    {
        if ($points < 1) {
            throw ValidationException::withMessages([
                'points' => 'Укажи количество очков создания больше нуля.',
            ]);
        }

        return DB::transaction(function () use ($user, $points): array {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $cost = $points * User::CREATURE_CREATION_POINT_XP_COST;

            if ($lockedUser->xp < $cost) {
                throw ValidationException::withMessages([
                    'points' => 'Недостаточно текущего опыта игрока для конвертации.',
                ]);
            }

            $lockedUser->forceFill([
                'xp' => $lockedUser->xp - $cost,
                'creature_creation_points' => $lockedUser->creature_creation_points + $points,
            ])->save();

            return [
                'spent_xp' => $cost,
                'gained_points' => $points,
            ];
        });
    }

    public function increaseDoctrineAttribute(User $user, string $attribute): User
    {
        if (! array_key_exists($attribute, User::DOCTRINE_ATTRIBUTES)) {
            throw ValidationException::withMessages([
                'attribute' => 'Неизвестное направление доктрины.',
            ]);
        }

        return DB::transaction(function () use ($user, $attribute): User {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUser->is_bot) {
                throw ValidationException::withMessages([
                    'attribute' => 'Боты не используют доктрину игрока.',
                ]);
            }

            if ($lockedUser->doctrine_points < 1) {
                throw ValidationException::withMessages([
                    'attribute' => 'Нет свободных очков доктрины.',
                ]);
            }

            $column = User::DOCTRINE_ATTRIBUTES[$attribute]['column'];

            if ((int) $lockedUser->{$column} >= User::MAX_DOCTRINE_ATTRIBUTE) {
                throw ValidationException::withMessages([
                    'attribute' => 'Это направление уже достигло текущего максимума.',
                ]);
            }

            $lockedUser->forceFill([
                'doctrine_points' => $lockedUser->doctrine_points - 1,
                $column => (int) $lockedUser->{$column} + 1,
            ])->save();

            Inventory::forUser($lockedUser->refresh())->syncSlots();

            return $lockedUser;
        });
    }

    public function buyPlayerPerk(User $user, string $perk): User
    {
        if (! array_key_exists($perk, User::PLAYER_PERKS)) {
            throw ValidationException::withMessages([
                'perk' => 'Неизвестный перк игрока.',
            ]);
        }

        return DB::transaction(function () use ($user, $perk): User {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedUser->canBuyPlayerPerk($perk)) {
                throw ValidationException::withMessages([
                    'perk' => 'Перк недоступен: проверь уровень, очки перков и вложения в нужную ветку доктрины.',
                ]);
            }

            $perks = $lockedUser->playerPerks();
            $perks[] = $perk;

            $lockedUser->forceFill([
                'perk_points' => $lockedUser->perk_points - 1,
                'player_perks' => array_values(array_unique($perks)),
            ])->save();

            Inventory::forUser($lockedUser->refresh())->syncSlots();

            return $lockedUser;
        });
    }

    private function battleXp(?string $result, int $opponentLevel, float $rewardMultiplier): int
    {
        $base = match ($result) {
            BattleParticipant::RESULT_WIN => self::WIN_XP_PER_OPPONENT_LEVEL,
            BattleParticipant::RESULT_DRAW => self::DRAW_XP_PER_OPPONENT_LEVEL,
            default => self::LOSS_XP_PER_OPPONENT_LEVEL,
        };

        return max(1, (int) floor($base * max(1, $opponentLevel) * $rewardMultiplier));
    }

    private function creationPointDrop(User $user, ?string $result, int $opponentLevel, float $rewardMultiplier, int $battleSeed, int $participantId): int
    {
        if ($result !== BattleParticipant::RESULT_WIN) {
            return 0;
        }

        $breedingBonus = $user->creationPointRewardBonusPercent();
        $chance = min(55, max(5, 5 + (max(1, $opponentLevel) * 3) + (int) round(($rewardMultiplier - 1) * 10) + intdiv($breedingBonus, 2)));
        $roll = $this->deterministicRoll($battleSeed, $participantId, 'creation-point-drop', 1, 100);

        if ($roll > $chance) {
            return 0;
        }

        $amountRoll = $this->deterministicRoll($battleSeed, $participantId, 'creation-point-amount', 0, 6);

        $amount = 8 + (max(1, $opponentLevel) * 2) + $amountRoll;

        return min(55, (int) floor($amount * (100 + $breedingBonus) / 100));
    }

    private function deterministicRoll(int $battleSeed, int $participantId, string $salt, int $min, int $max): int
    {
        $hash = crc32(implode('|', [$battleSeed, $participantId, $salt]));

        return $min + ((int) $hash % (($max - $min) + 1));
    }
}
