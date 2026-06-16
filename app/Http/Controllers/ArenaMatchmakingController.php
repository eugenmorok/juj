<?php

namespace App\Http\Controllers;

use App\Models\ArenaMatchmakingSession;
use App\Models\Creature;
use App\Services\ArenaMatchmakingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ArenaMatchmakingController extends Controller
{
    public function store(Request $request, ArenaMatchmakingService $matchmaking): RedirectResponse
    {
        $attributes = $request->validate([
            'creature_id' => ['required', 'integer', 'exists:creatures,id'],
        ]);

        $creature = Creature::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail((int) $attributes['creature_id']);

        $session = $matchmaking->createSession($request->user(), $creature);

        return redirect()
            ->route('arena.search.show', $session)
            ->with('status', 'Подбор соперников готов.');
    }

    public function show(Request $request, ArenaMatchmakingSession $session, ArenaMatchmakingService $matchmaking): View
    {
        abort_unless($session->user_id === $request->user()->id, 404);

        $session->load([
            'creature.type',
            'creature.species',
            'creature.skills',
            'creature.equipmentRows.itemInstance.item',
        ]);

        return view('game.arena.search', [
            'session' => $session,
            'creature' => $session->creature,
            'candidates' => $matchmaking->candidates($session),
        ]);
    }
}
