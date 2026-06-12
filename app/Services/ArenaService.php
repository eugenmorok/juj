<?php

namespace App\Services;

use App\Models\Battle;
use App\Models\Creature;
use App\Models\User;
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
        $creature->loadMissing(['skills', 'equipmentRows.itemInstance.item']);
        $creaturePower = $this->powerScore->calculate($creature);

        $candidates = Creature::query()
            ->where('id', '!=', $creature->id)
            ->where('user_id', '!=', $creature->user_id)
            ->where('is_available_for_battle', true)
            ->with(['user', 'skills', 'equipmentRows.itemInstance.item'])
            ->get();

        if ($candidates->isEmpty()) {
            throw ValidationException::withMessages([
                'arena' => 'Нет доступных соперников. Боты будут добавлены следующим спринтом.',
            ]);
        }

        $levelCandidates = $candidates
            ->filter(fn (Creature $candidate): bool => abs($candidate->level - $creature->level) <= 2)
            ->values();

        if ($levelCandidates->isEmpty()) {
            $levelCandidates = $candidates;
        }

        return $levelCandidates
            ->sortBy(fn (Creature $candidate): int => abs($this->powerScore->calculate($candidate) - $creaturePower) + (abs($candidate->level - $creature->level) * 10))
            ->first();
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
}
