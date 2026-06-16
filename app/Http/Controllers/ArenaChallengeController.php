<?php

namespace App\Http\Controllers;

use App\Models\ArenaChallenge;
use App\Models\Creature;
use App\Services\ArenaChallengeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ArenaChallengeController extends Controller
{
    public function store(Request $request, ArenaChallengeService $challenges): RedirectResponse
    {
        $attributes = $request->validate([
            'challenger_creature_id' => ['required', 'integer', 'exists:creatures,id'],
            'defender_creature_id' => ['required', 'integer', 'exists:creatures,id'],
        ]);

        $challengerCreature = Creature::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail((int) $attributes['challenger_creature_id']);

        $defenderCreature = Creature::query()
            ->with('user')
            ->findOrFail((int) $attributes['defender_creature_id']);

        $challenge = $challenges->create($request->user(), $challengerCreature, $defenderCreature);

        if ($challenge->battle_id) {
            return redirect()
                ->route('arena.battles.show', $challenge->battle)
                ->with('status', 'Вызов принят ботом, бой запущен. Выбери тактику шага.');
        }

        return redirect()
            ->route('arena.challenges.show', $challenge)
            ->with('status', 'Вызов отправлен. У игрока есть 2 минуты на ответ.');
    }

    public function show(Request $request, ArenaChallenge $challenge, ArenaChallengeService $challenges): View
    {
        abort_unless(
            in_array($request->user()->id, [$challenge->challenger_user_id, $challenge->defender_user_id], true),
            404,
        );

        $challenge = $challenges->refreshStatus($challenge);

        return view('game.arena.challenge', [
            'challenge' => $challenge,
            'isChallenger' => $request->user()->id === $challenge->challenger_user_id,
            'isDefender' => $request->user()->id === $challenge->defender_user_id,
        ]);
    }

    public function accept(Request $request, ArenaChallenge $challenge, ArenaChallengeService $challenges): RedirectResponse
    {
        $challenge = $challenges->accept($request->user(), $challenge);

        return redirect()
            ->route('arena.battles.show', $challenge->battle)
            ->with('status', 'Вызов принят, бой запущен. Выбери тактику шага.');
    }

    public function decline(Request $request, ArenaChallenge $challenge, ArenaChallengeService $challenges): RedirectResponse
    {
        $challenge = $challenges->decline($request->user(), $challenge);

        return redirect()
            ->route('arena.challenges.show', $challenge)
            ->with('status', 'Вызов отклонен.');
    }

    public function cancel(Request $request, ArenaChallenge $challenge, ArenaChallengeService $challenges): RedirectResponse
    {
        $challenge = $challenges->cancel($request->user(), $challenge);

        return redirect()
            ->route('arena.challenges.show', $challenge)
            ->with('status', 'Вызов отменен.');
    }
}
