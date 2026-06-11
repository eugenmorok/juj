<?php

namespace App\Http\Controllers;

use App\Models\CreatureType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class GamePageController extends Controller
{
    public function profile(Request $request): View
    {
        return view('game.profile', [
            'user' => $request->user(),
        ]);
    }

    public function entities(): View
    {
        return view('game.entities', [
            'creatureTypes' => CreatureType::query()
                ->active()
                ->with(['species' => fn ($query) => $query->active()->orderBy('name')])
                ->withCount(['species' => fn ($query) => $query->active()])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function arena(): View
    {
        return view('game.arena');
    }

    public function shop(): View
    {
        return view('game.shop');
    }

    public function inventory(Request $request): View
    {
        return view('game.inventory', [
            'user' => $request->user(),
        ]);
    }

}
