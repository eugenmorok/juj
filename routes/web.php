<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CreatureController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GamePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'home'])->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [GamePageController::class, 'profile'])->name('profile');
    Route::get('/entities', [CreatureController::class, 'index'])->name('entities.index');
    Route::get('/entities/create', [CreatureController::class, 'create'])->name('entities.create');
    Route::post('/entities', [CreatureController::class, 'store'])->name('entities.store');
    Route::get('/entities/{creature}', [CreatureController::class, 'show'])->name('entities.show');
    Route::post('/entities/{creature}/skills/{skill}', [CreatureController::class, 'buySkill'])->name('entities.skills.purchase');
    Route::get('/arena', [GamePageController::class, 'arena'])->name('arena');
    Route::get('/shop', [GamePageController::class, 'shop'])->name('shop');
    Route::get('/inventory', [GamePageController::class, 'inventory'])->name('inventory');
});
