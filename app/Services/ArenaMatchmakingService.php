<?php

namespace App\Services;

use App\Models\ArenaMatchmakingSession;
use App\Models\ArenaSetting;
use App\Models\Creature;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ArenaMatchmakingService
{
    public const MIN_BOT_CANDIDATES = 5;

    public const MAX_CANDIDATES = 20;

    public function __construct(
        private readonly PowerScoreService $powerScore,
        private readonly BotGenerationService $botGeneration,
    ) {}

    public function createSession(User $user, Creature $creature): ArenaMatchmakingSession
    {
        $this->ensureCreatureCanSearch($user, $creature);
        $creature->loadMissing(['skills', 'equipmentRows.itemInstance.item']);

        return ArenaMatchmakingSession::query()->create([
            'user_id' => $user->id,
            'creature_id' => $creature->id,
            'power_score' => $this->powerScore->calculate($creature),
            'status' => ArenaMatchmakingSession::STATUS_ACTIVE,
            'expires_at' => now()->addMinutes(ArenaMatchmakingSession::TTL_MINUTES),
        ]);
    }

    /**
     * @return Collection<int, array{
     *     creature: Creature,
     *     user: User,
     *     is_bot: bool,
     *     power_score: int,
     *     power_delta: int,
     *     level_delta: int
     * }>
     */
    public function candidates(ArenaMatchmakingSession $session): Collection
    {
        $session->loadMissing('creature');
        $this->ensureSessionIsActive($session);

        $creature = $session->creature;
        $this->ensureMinimumBotCandidates($session, $creature);

        return $this->candidateCreatures($session, $creature, bots: false)
            ->concat($this->candidateCreatures($session, $creature, bots: true))
            ->sortBy(fn (array $candidate): string => sprintf('%08d:%08d', $candidate['power_delta'], $candidate['level_delta']))
            ->take(self::MAX_CANDIDATES)
            ->values();
    }

    private function ensureCreatureCanSearch(User $user, Creature $creature): void
    {
        abort_unless($creature->user_id === $user->id, 404);

        if (! $creature->is_available_for_battle) {
            throw ValidationException::withMessages([
                'arena' => 'Эта сущность сейчас недоступна для боя.',
            ]);
        }
    }

    private function ensureSessionIsActive(ArenaMatchmakingSession $session): void
    {
        if ($session->isActive()) {
            return;
        }

        $session->markExpired();

        throw ValidationException::withMessages([
            'arena' => 'Поиск боя устарел. Запустите поиск заново.',
        ]);
    }

    private function ensureMinimumBotCandidates(ArenaMatchmakingSession $session, Creature $creature): void
    {
        $currentBots = $this->candidateCreatures($session, $creature, bots: true)->count();

        if ($currentBots >= self::MIN_BOT_CANDIDATES) {
            return;
        }

        $settings = ArenaSetting::current();
        $levelDiff = max(0, $settings->matchmaking_level_difference);

        $this->botGeneration->generateBatch(
            count: self::MIN_BOT_CANDIDATES - $currentBots,
            style: 'balanced',
            minLevel: max(1, $creature->level - $levelDiff),
            maxLevel: max(1, $creature->level + $levelDiff),
            withCreature: true,
            withEquipment: true,
        );
    }

    /**
     * @return Collection<int, array{creature: Creature, user: User, is_bot: bool, power_score: int, power_delta: int, level_delta: int}>
     */
    private function candidateCreatures(ArenaMatchmakingSession $session, Creature $creature, bool $bots): Collection
    {
        $query = Creature::query()
            ->where('id', '!=', $creature->id)
            ->where('user_id', '!=', $creature->user_id)
            ->where('is_available_for_battle', true)
            ->whereHas('user', fn ($user) => $user->where('is_bot', $bots))
            ->with(['user.botProfile', 'type', 'species', 'skills', 'equipmentRows.itemInstance.item']);

        if ($bots) {
            $query->whereHas('user.botProfile', fn ($profile) => $profile
                ->where('is_active', true)
                ->where('spawn_chance', '>', 0));
        }

        return $query
            ->get()
            ->toBase()
            ->map(fn (Creature $candidate): array => $this->candidatePayload($session, $creature, $candidate))
            ->filter(fn (array $candidate): bool => $this->isSuitableCandidate($session, $creature, $candidate))
            ->values();
    }

    /**
     * @return array{creature: Creature, user: User, is_bot: bool, power_score: int, power_delta: int, level_delta: int}
     */
    private function candidatePayload(ArenaMatchmakingSession $session, Creature $creature, Creature $candidate): array
    {
        $candidatePower = $this->powerScore->calculate($candidate);

        return [
            'creature' => $candidate,
            'user' => $candidate->user,
            'is_bot' => (bool) $candidate->user?->is_bot,
            'power_score' => $candidatePower,
            'power_delta' => abs($candidatePower - $session->power_score),
            'level_delta' => abs($candidate->level - $creature->level),
        ];
    }

    /**
     * @param  array{power_delta: int, level_delta: int}  $candidate
     */
    private function isSuitableCandidate(ArenaMatchmakingSession $session, Creature $creature, array $candidate): bool
    {
        $settings = ArenaSetting::current();
        $levelDiff = max(0, $settings->matchmaking_level_difference);
        $powerDiff = $settings->matchmaking_power_score_difference > 0
            ? $settings->matchmaking_power_score_difference
            : max(25, (int) ceil($session->power_score * 0.25));

        return $candidate['level_delta'] <= $levelDiff
            && $candidate['power_delta'] <= $powerDiff;
    }
}
