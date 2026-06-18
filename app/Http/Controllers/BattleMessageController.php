<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Services\InteractiveBattleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BattleMessageController extends Controller
{
    public function store(Request $request, Battle $battle, InteractiveBattleService $interactiveBattles): JsonResponse|RedirectResponse
    {
        abort_unless(
            $request->user()->is_admin || $battle->participants()->where('user_id', $request->user()->id)->exists(),
            404,
        );

        $attributes = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        $messageText = trim((string) $attributes['message']);

        if ($messageText === '') {
            throw ValidationException::withMessages([
                'message' => 'Введите сообщение.',
            ]);
        }

        $battle->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $messageText,
        ]);

        $battle = $battle->refresh();
        $interactiveBattles->syncRealtimeState($battle);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Сообщение отправлено.',
                ...$interactiveBattles->statePayload(
                    $battle,
                    $request->user(),
                    true,
                    $request->integer('after_event_id') ?: null,
                ),
            ]);
        }

        return redirect()
            ->route('arena.battles.show', $battle)
            ->with('status', 'Сообщение отправлено.');
    }
}
