<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\Creature;
use App\Models\ArenaChallenge;
use App\Services\ArenaChallengeService;
use App\Services\ArenaService;
use App\Services\PowerScoreService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ArenaController extends Controller
{
    public function index(Request $request, PowerScoreService $powerScore, ArenaChallengeService $challenges): View
    {
        $challenges->expireStalePendingChallenges();

        $creatures = $request->user()
            ->creatures()
            ->with(['type', 'species', 'skills', 'equipmentRows.itemInstance.item'])
            ->orderBy('name')
            ->get();

        return view('game.arena', [
            'creatures' => $creatures,
            'powerScores' => $creatures
                ->mapWithKeys(fn (Creature $creature): array => [$creature->id => $powerScore->calculate($creature)])
                ->all(),
            'incomingChallenges' => ArenaChallenge::query()
                ->pending()
                ->where('defender_user_id', $request->user()->id)
                ->with(['challengerCreature.user', 'defenderCreature'])
                ->latest()
                ->get(),
            'outgoingChallenges' => ArenaChallenge::query()
                ->pending()
                ->where('challenger_user_id', $request->user()->id)
                ->with(['challengerCreature', 'defenderCreature.user'])
                ->latest()
                ->get(),
            'recentBattles' => Battle::query()
                ->whereHas('participants', fn ($query) => $query->where('user_id', $request->user()->id))
                ->with(['participants.creature.user'])
                ->latest('started_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function start(Request $request, ArenaService $arena): RedirectResponse
    {
        $attributes = $request->validate([
            'creature_id' => ['required', 'integer', 'exists:creatures,id'],
        ]);

        $creature = Creature::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail((int) $attributes['creature_id']);

        $battle = $arena->startBattle($request->user(), $creature);

        return redirect()
            ->route('arena.battles.show', $battle)
            ->with('status', 'Бой завершен, награды начислены.');
    }

    public function show(Request $request, Battle $battle): View
    {
        abort_unless(
            $battle->participants()->where('user_id', $request->user()->id)->exists(),
            404,
        );

        $battle->load([
            'participants.creature.user',
            'events.actor',
            'events.target',
        ]);

        return view('game.battles.show', [
            'battle' => $battle,
        ]);
    }
}
