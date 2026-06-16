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
        $settings = ArenaSetting::current();

        return ArenaMatchmakingSession::query()->create([
            'user_id' => $user->id,
            'creature_id' => $creature->id,
            'power_score' => $this->powerScore->calculate($creature, $settings),
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
        $settings = ArenaSetting::current();
        $this->ensureRoughBotPool($creature, $settings);
        $candidates = $this->candidateCreatures($session, $creature, bots: null, settings: $settings);

        $missingSuitableBots = self::MIN_BOT_CANDIDATES - $candidates->where('is_bot', true)->count();

        if ($missingSuitableBots > 0) {
            $this->generateFallbackBots($creature, $settings, $missingSuitableBots);
            $candidates = $this->candidateCreatures($session, $creature, bots: null, settings: $settings);
        }

        return $candidates
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

    private function ensureRoughBotPool(Creature $creature, ArenaSetting $settings): void
    {
        $roughBots = $this->roughCandidateCount($creature, $settings, bots: true);

        if ($roughBots < self::MIN_BOT_CANDIDATES) {
            $this->generateFallbackBots($creature, $settings, self::MIN_BOT_CANDIDATES - $roughBots);
        }
    }

    private function generateFallbackBots(Creature $creature, ArenaSetting $settings, int $count): void
    {
        if ($count <= 0) {
            return;
        }

        $levelDiff = max(0, $settings->matchmaking_level_difference);

        $this->botGeneration->generateBatch(
            count: $count,
            style: 'balanced',
            minLevel: max(1, $creature->level - $levelDiff),
            maxLevel: max(1, $creature->level + $levelDiff),
            withCreature: true,
            withEquipment: false,
            withInventory: false,
            withSkills: true,
            loadCreatures: false,
            reloadProfiles: false,
        );
    }

    private function roughCandidateCount(Creature $creature, ArenaSetting $settings, bool $bots): int
    {
        $levelDiff = max(0, $settings->matchmaking_level_difference);

        $query = Creature::query()
            ->where('id', '!=', $creature->id)
            ->where('user_id', '!=', $creature->user_id)
            ->where('is_available_for_battle', true)
            ->whereBetween('level', [max(1, $creature->level - $levelDiff), max(1, $creature->level + $levelDiff)])
            ->whereHas('user', fn ($user) => $user->where('is_bot', $bots));

        if ($bots) {
            $query->whereHas('user.botProfile', fn ($profile) => $profile
                ->where('is_active', true)
                ->where('spawn_chance', '>', 0));
        }

        return $query->count();
    }

    /**
     * @return Collection<int, array{creature: Creature, user: User, is_bot: bool, power_score: int, power_delta: int, level_delta: int}>
     */
    private function candidateCreatures(ArenaMatchmakingSession $session, Creature $creature, ?bool $bots, ArenaSetting $settings): Collection
    {
        $levelDiff = max(0, $settings->matchmaking_level_difference);

        $query = Creature::query()
            ->where('id', '!=', $creature->id)
            ->where('user_id', '!=', $creature->user_id)
            ->where('is_available_for_battle', true)
            ->whereBetween('level', [max(1, $creature->level - $levelDiff), max(1, $creature->level + $levelDiff)])
            ->with(['user', 'type', 'species', 'skills', 'equipmentRows.itemInstance.item']);

        if ($bots === null) {
            $query->where(function ($scope): void {
                $scope
                    ->whereHas('user', fn ($user) => $user->where('is_bot', false))
                    ->orWhere(function ($botScope): void {
                        $botScope
                            ->whereHas('user', fn ($user) => $user->where('is_bot', true))
                            ->whereHas('user.botProfile', fn ($profile) => $profile
                                ->where('is_active', true)
                                ->where('spawn_chance', '>', 0));
                    });
            });
        } elseif ($bots) {
            $query
                ->whereHas('user', fn ($user) => $user->where('is_bot', true))
                ->whereHas('user.botProfile', fn ($profile) => $profile
                    ->where('is_active', true)
                    ->where('spawn_chance', '>', 0));
        } else {
            $query->whereHas('user', fn ($user) => $user->where('is_bot', false));
        }

        return $query
            ->orderByRaw('ABS(level - ?) asc', [$creature->level])
            ->latest('id')
            ->limit(self::MAX_CANDIDATES * 3)
            ->get()
            ->toBase()
            ->map(fn (Creature $candidate): array => $this->candidatePayload($session, $creature, $candidate, $settings))
            ->filter(fn (array $candidate): bool => $this->isSuitableCandidate($session, $candidate, $settings))
            ->values();
    }

    /**
     * @return array{creature: Creature, user: User, is_bot: bool, power_score: int, power_delta: int, level_delta: int}
     */
    private function candidatePayload(ArenaMatchmakingSession $session, Creature $creature, Creature $candidate, ArenaSetting $settings): array
    {
        $candidatePower = $this->powerScore->calculate($candidate, $settings);

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
    private function isSuitableCandidate(ArenaMatchmakingSession $session, array $candidate, ArenaSetting $settings): bool
    {
        $levelDiff = max(0, $settings->matchmaking_level_difference);
        $powerDiff = $settings->matchmaking_power_score_difference > 0
            ? $settings->matchmaking_power_score_difference
            : max(25, (int) ceil($session->power_score * 0.25));

        return $candidate['level_delta'] <= $levelDiff
            && $candidate['power_delta'] <= $powerDiff;
    }
}
