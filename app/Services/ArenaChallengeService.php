<?php

namespace App\Services;

use App\Models\ArenaChallenge;
use App\Models\Battle;
use App\Models\Creature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArenaChallengeService
{
    public function __construct(
        private readonly BattleEngine $battleEngine,
        private readonly BattleRewardService $battleRewards,
    ) {}

    public function create(User $challenger, Creature $challengerCreature, Creature $defenderCreature): ArenaChallenge
    {
        $this->expireStalePendingChallenges();

        $challengerCreature->loadMissing('user');
        $defenderCreature->loadMissing('user');
        $this->ensureCanChallenge($challenger, $challengerCreature, $defenderCreature);

        $challenge = ArenaChallenge::query()->create([
            'challenger_user_id' => $challenger->id,
            'challenger_creature_id' => $challengerCreature->id,
            'defender_user_id' => $defenderCreature->user_id,
            'defender_creature_id' => $defenderCreature->id,
            'defender_is_bot' => (bool) $defenderCreature->user?->is_bot,
            'status' => $defenderCreature->user?->is_bot
                ? ArenaChallenge::STATUS_ACCEPTED
                : ArenaChallenge::STATUS_PENDING,
            'expires_at' => now()->addSeconds(ArenaChallenge::ACCEPTANCE_SECONDS),
            'accepted_at' => $defenderCreature->user?->is_bot ? now() : null,
        ]);

        if ($challenge->defender_is_bot) {
            return $this->startBattle($challenge);
        }

        return $challenge->load(['challengerCreature.user', 'defenderCreature.user']);
    }

    public function accept(User $defender, ArenaChallenge $challenge): ArenaChallenge
    {
        $challenge = $this->freshChallenge($challenge);
        abort_unless($challenge->defender_user_id === $defender->id, 404);
        $this->ensurePendingChallenge($challenge);

        $challenge->forceFill([
            'status' => ArenaChallenge::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ])->save();

        return $this->startBattle($challenge);
    }

    public function decline(User $defender, ArenaChallenge $challenge): ArenaChallenge
    {
        $challenge = $this->freshChallenge($challenge);
        abort_unless($challenge->defender_user_id === $defender->id, 404);
        $this->ensurePendingChallenge($challenge);

        $challenge->forceFill([
            'status' => ArenaChallenge::STATUS_DECLINED,
            'declined_at' => now(),
        ])->save();

        return $challenge;
    }

    public function cancel(User $challenger, ArenaChallenge $challenge): ArenaChallenge
    {
        $challenge = $this->freshChallenge($challenge);
        abort_unless($challenge->challenger_user_id === $challenger->id, 404);
        $this->ensurePendingChallenge($challenge);

        $challenge->forceFill([
            'status' => ArenaChallenge::STATUS_CANCELLED,
        ])->save();

        return $challenge;
    }

    public function refreshStatus(ArenaChallenge $challenge): ArenaChallenge
    {
        $challenge = $this->freshChallenge($challenge);

        if ($challenge->isExpired()) {
            $challenge->markExpired();
        }

        return $challenge->refresh()->load(['challengerCreature.user', 'defenderCreature.user', 'battle']);
    }

    public function expireStalePendingChallenges(): int
    {
        return ArenaChallenge::query()
            ->where('status', ArenaChallenge::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => ArenaChallenge::STATUS_EXPIRED]);
    }

    private function startBattle(ArenaChallenge $challenge): ArenaChallenge
    {
        return DB::transaction(function () use ($challenge): ArenaChallenge {
            $challenge = ArenaChallenge::query()
                ->whereKey($challenge->id)
                ->lockForUpdate()
                ->with(['challengerCreature.user', 'defenderCreature.user'])
                ->firstOrFail();

            if ($challenge->battle_id) {
                return $challenge;
            }

            $battle = $this->battleEngine->run(
                leftCreature: $challenge->challengerCreature,
                rightCreature: $challenge->defenderCreature,
                battleType: Battle::TYPE_RANKED,
                initiator: $challenge->challenger,
            );
            $battle = $this->battleRewards->apply($battle);

            $challenge->forceFill([
                'status' => ArenaChallenge::STATUS_BATTLE_STARTED,
                'battle_id' => $battle->id,
                'accepted_at' => $challenge->accepted_at ?? now(),
            ])->save();

            return $challenge->load(['challengerCreature.user', 'defenderCreature.user', 'battle']);
        });
    }

    private function freshChallenge(ArenaChallenge $challenge): ArenaChallenge
    {
        return ArenaChallenge::query()
            ->with(['challengerCreature.user', 'defenderCreature.user', 'battle'])
            ->findOrFail($challenge->id);
    }

    private function ensureCanChallenge(User $challenger, Creature $challengerCreature, Creature $defenderCreature): void
    {
        abort_unless($challengerCreature->user_id === $challenger->id, 404);

        if ($challengerCreature->id === $defenderCreature->id || $challengerCreature->user_id === $defenderCreature->user_id) {
            throw ValidationException::withMessages([
                'arena' => 'Нельзя бросить вызов своей сущности.',
            ]);
        }

        if (! $challengerCreature->is_available_for_battle || ! $defenderCreature->is_available_for_battle) {
            throw ValidationException::withMessages([
                'arena' => 'Одна из сущностей сейчас недоступна для боя.',
            ]);
        }
    }

    private function ensurePendingChallenge(ArenaChallenge $challenge): void
    {
        if ($challenge->isExpired()) {
            $challenge->markExpired();

            throw ValidationException::withMessages([
                'challenge' => 'Время ответа на вызов истекло.',
            ]);
        }

        if (! $challenge->isPending()) {
            throw ValidationException::withMessages([
                'challenge' => 'Этот вызов уже обработан.',
            ]);
        }
    }
}
