<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Services\InteractiveBattleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BattleStateController extends Controller
{
    public function show(Request $request, Battle $battle, InteractiveBattleService $interactiveBattles): JsonResponse
    {
        abort_unless(
            $request->user()->is_admin || $battle->participants()->where('user_id', $request->user()->id)->exists(),
            404,
        );

        return response()->json(
            $interactiveBattles->statePayload(
                $battle,
                $request->user(),
                $request->boolean('include_fragments'),
            ),
        );
    }
}
