<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function home(): View
    {
        return view('home');
    }

    public function dashboard(Request $request): View
    {
        return view('dashboard', [
            'user' => $request->user(),
        ]);
    }
}
