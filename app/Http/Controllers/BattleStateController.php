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

        if ($battle->isInteractive()) {
            $battle = $interactiveBattles->prepare($battle);
        } else {
            $battle->load(['participants.creature.user', 'events']);
        }

        $ownParticipant = $battle->participants->firstWhere('user_id', $request->user()->id);
        $activeRound = $battle->rounds->firstWhere('round_number', $battle->current_round);
        $ownAction = $activeRound?->actions->firstWhere('creature_id', $ownParticipant?->creature_id);
        $latestEvent = $battle->events->last();

        return response()->json([
            'battle_id' => $battle->id,
            'mode' => $battle->mode,
            'status' => $battle->status,
            'current_round' => $battle->current_round,
            'turn_deadline_at' => $battle->turn_deadline_at?->toISOString(),
            'latest_event_id' => $latestEvent?->id,
            'events_count' => $battle->events->count(),
            'active_round' => $activeRound ? [
                'id' => $activeRound->id,
                'round_number' => $activeRound->round_number,
                'status' => $activeRound->status,
                'deadline_at' => $activeRound->deadline_at?->toISOString(),
                'actions_count' => $activeRound->actions->count(),
                'own_action_id' => $ownAction?->id,
            ] : null,
            'participants' => $battle->participants
                ->map(fn ($participant): array => [
                    'creature_id' => $participant->creature_id,
                    'creature_name' => $participant->creature?->name,
                    'hp_after' => $participant->hp_after,
                    'hp_before' => $participant->hp_before,
                    'result' => $participant->result,
                ])
                ->values()
                ->all(),
        ]);
    }
}
