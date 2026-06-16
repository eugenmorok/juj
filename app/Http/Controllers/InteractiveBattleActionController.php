<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\BattleAction;
use App\Services\InteractiveBattleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InteractiveBattleActionController extends Controller
{
    public function store(Request $request, Battle $battle, InteractiveBattleService $interactiveBattles): JsonResponse|RedirectResponse
    {
        $attributes = $request->validate([
            'attack_zone' => ['required', 'string', Rule::in(array_keys(BattleAction::ZONES))],
            'defense_zone' => ['required', 'string', Rule::in(array_keys(BattleAction::ZONES))],
            'inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
        ]);

        $battle = $interactiveBattles->submitAction($request->user(), $battle, $attributes);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $battle->status === Battle::STATUS_FINISHED
                    ? 'Шаг принят, бой завершен.'
                    : 'Тактика шага принята.',
                ...$interactiveBattles->statePayload($battle, $request->user(), true),
            ]);
        }

        return redirect()
            ->route('arena.battles.show', $battle)
            ->with('status', $battle->status === Battle::STATUS_FINISHED
                ? 'Шаг принят, бой завершен.'
                : 'Тактика шага принята.');
    }
}
