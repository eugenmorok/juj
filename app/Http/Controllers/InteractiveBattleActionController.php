<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\BattleAction;
use App\Services\InteractiveBattleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InteractiveBattleActionController extends Controller
{
    public function store(Request $request, Battle $battle, InteractiveBattleService $interactiveBattles): RedirectResponse
    {
        $attributes = $request->validate([
            'attack_zone' => ['required', 'string', Rule::in(array_keys(BattleAction::ZONES))],
            'defense_zone' => ['required', 'string', Rule::in(array_keys(BattleAction::ZONES))],
            'inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
        ]);

        $battle = $interactiveBattles->submitAction($request->user(), $battle, $attributes);

        return redirect()
            ->route('arena.battles.show', $battle)
            ->with('status', $battle->status === Battle::STATUS_FINISHED
                ? 'Шаг принят, бой завершен.'
                : 'Тактика шага принята.');
    }
}
