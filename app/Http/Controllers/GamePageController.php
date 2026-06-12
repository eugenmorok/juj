<?php

namespace App\Http\Controllers;

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

    public function inventory(Request $request): View
    {
        return view('game.inventory', [
            'user' => $request->user(),
        ]);
    }

}
