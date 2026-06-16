<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Services\InteractiveBattleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BattleReplayController extends Controller
{
    public function show(Request $request, Battle $battle, InteractiveBattleService $interactiveBattles): View
    {
        abort_unless(
            $request->user()->is_admin || $battle->participants()->where('user_id', $request->user()->id)->exists(),
            404,
        );

        $battle = $interactiveBattles->loadForDisplay($battle);

        return view('game.battles.replay', [
            'battle' => $battle,
            'eventsByRound' => $battle->events->groupBy('round'),
        ]);
    }
}
