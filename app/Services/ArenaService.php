<?php

namespace App\Services;

use App\Models\ArenaSetting;
use App\Models\Battle;
use App\Models\Creature;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ArenaService
{
    public function __construct(
        private readonly BattleEngine $battleEngine,
        private readonly BattleRewardService $battleRewards,
        private readonly PowerScoreService $powerScore,
    ) {}

    public function startBattle(User $user, Creature $creature): Battle
    {
        $this->ensureCreatureCanFight($user, $creature);
        $this->ensureDailyBattleLimit($user);

        $opponent = $this->findOpponent($creature);
        $battle = $this->battleEngine->run(
            leftCreature: $creature,
            rightCreature: $opponent,
            battleType: Battle::TYPE_RANKED,
            initiator: $user,
        );

        return $this->battleRewards->apply($battle);
    }

    public function findOpponent(Creature $creature): Creature
    {
        $settings = ArenaSetting::current();
        $creature->loadMissing(['skills', 'equipmentRows.itemInstance.item']);
        $creaturePower = $this->powerScore->calculate($creature);

        $realCandidates = Creature::query()
            ->where('id', '!=', $creature->id)
            ->where('user_id', '!=', $creature->user_id)
            ->where('is_available_for_battle', true)
            ->whereHas('user', fn ($query) => $query->where('is_bot', false))
            ->with(['user', 'skills', 'equipmentRows.itemInstance.item'])
            ->get();

        $botCandidates = Creature::query()
            ->where('id', '!=', $creature->id)
            ->where('user_id', '!=', $creature->user_id)
            ->where('is_available_for_battle', true)
            ->whereHas('user', fn ($query) => $query
                ->where('is_bot', true)
                ->whereHas('botProfile', fn ($profile) => $profile
                    ->where('is_active', true)
                    ->where('spawn_chance', '>', 0)))
            ->with(['user.botProfile', 'skills', 'equipmentRows.itemInstance.item'])
            ->get();

        $candidates = $this->shouldUseBot($realCandidates, $botCandidates)
            ? $botCandidates
            : $realCandidates;

        if ($candidates->isEmpty()) {
            throw ValidationException::withMessages([
                'arena' => 'Нет доступных соперников. Сгенерируйте активных ботов в админке.',
            ]);
        }

        $matchCandidates = $candidates
            ->filter(fn (Creature $candidate): bool => abs($candidate->level - $creature->level) <= $settings->matchmaking_level_difference)
            ->values();

        if ($matchCandidates->isEmpty()) {
            $matchCandidates = $candidates;
        }

        $powerDifference = $settings->matchmaking_power_score_difference > 0
            ? $settings->matchmaking_power_score_difference
            : max(25, (int) ceil($creaturePower * 0.25));
        $botPowerCeiling = max(1, min($creaturePower - 5, (int) floor($creaturePower * 0.97)));
        $powerCandidates = $matchCandidates
            ->filter(function (Creature $candidate) use ($creaturePower, $powerDifference, $botPowerCeiling): bool {
                $candidatePower = $this->powerScore->calculate($candidate);

                return abs($candidatePower - $creaturePower) <= $powerDifference
                    && (! $candidate->user?->is_bot || $candidatePower <= $botPowerCeiling);
            })
            ->values();

        if ($powerCandidates->isNotEmpty()) {
            $matchCandidates = $powerCandidates;
        }

        return $matchCandidates
            ->sortBy(fn (Creature $candidate): int => abs($this->powerScore->calculate($candidate) - $creaturePower) + (abs($candidate->level - $creature->level) * 10))
            ->first();
    }

    /**
     * @param  Collection<int, Creature>  $realCandidates
     * @param  Collection<int, Creature>  $botCandidates
     */
    private function shouldUseBot($realCandidates, $botCandidates): bool
    {
        if ($botCandidates->isEmpty()) {
            return false;
        }

        if ($realCandidates->isEmpty()) {
            return true;
        }

        $maxChance = $botCandidates
            ->map(fn (Creature $creature): int => (int) ($creature->user?->botProfile?->spawn_chance ?? 0))
            ->max();

        return random_int(1, 100) <= $maxChance;
    }

    private function ensureCreatureCanFight(User $user, Creature $creature): void
    {
        abort_unless($creature->user_id === $user->id, 404);

        if (! $creature->is_available_for_battle) {
            throw ValidationException::withMessages([
                'arena' => 'Эта сущность сейчас недоступна для боя.',
            ]);
        }
    }

    private function ensureDailyBattleLimit(User $user): void
    {
        $limit = ArenaSetting::current()->daily_battle_limit;

        if ($limit <= 0) {
            return;
        }

        $battlesToday = Battle::query()
            ->whereDate('started_at', today())
            ->whereHas('participants', fn ($participants) => $participants->where('user_id', $user->id))
            ->count();

        if ($battlesToday >= $limit) {
            throw ValidationException::withMessages([
                'arena' => 'Дневной лимит боев исчерпан.',
            ]);
        }
    }
}
