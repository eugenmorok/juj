<?php

namespace App\Http\Controllers;

use App\Services\PlayerProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GamePageController extends Controller
{
    public function profile(Request $request): View
    {
        return view('game.profile', [
            'user' => $request->user(),
        ]);
    }

    public function convertCreationPoints(Request $request, PlayerProgressService $progress): RedirectResponse
    {
        $attributes = $request->validate([
            'points' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $progress->convertXpToCreationPoints($request->user(), (int) $attributes['points']);

        return back()->with(
            'status',
            "Конвертация выполнена: +{$result['gained_points']} очков создания за {$result['spent_xp']} XP.",
        );
    }

    public function increaseDoctrine(Request $request, string $attribute, PlayerProgressService $progress): RedirectResponse
    {
        $user = $progress->increaseDoctrineAttribute($request->user(), $attribute);
        $label = $user::DOCTRINE_ATTRIBUTES[$attribute]['label'] ?? $attribute;

        return back()->with('status', "Доктрина улучшена: {$label}.");
    }

    public function inventory(Request $request): View
    {
        return view('game.inventory', [
            'user' => $request->user(),
        ]);
    }

    public function help(): View
    {
        return view('game.help');
    }
}
