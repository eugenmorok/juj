<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ArenaController;
use App\Http\Controllers\ArenaChallengeController;
use App\Http\Controllers\ArenaMatchmakingController;
use App\Http\Controllers\CreatureController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\GamePageController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InteractiveBattleActionController;
use App\Http\Controllers\ShopController;
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
    Route::get('/entities/{creature}/equipment', [EquipmentController::class, 'show'])->name('entities.equipment');
    Route::post('/entities/{creature}/equipment/{inventoryItem}', [EquipmentController::class, 'equip'])->name('entities.equipment.equip');
    Route::post('/entities/{creature}/equipment/{itemInstance}/unequip', [EquipmentController::class, 'unequip'])->name('entities.equipment.unequip');
    Route::get('/arena', [ArenaController::class, 'index'])->name('arena');
    Route::post('/arena/search', [ArenaMatchmakingController::class, 'store'])->name('arena.search.store');
    Route::get('/arena/search/{session}', [ArenaMatchmakingController::class, 'show'])->name('arena.search.show');
    Route::post('/arena/challenges', [ArenaChallengeController::class, 'store'])->name('arena.challenges.store');
    Route::get('/arena/challenges/{challenge}', [ArenaChallengeController::class, 'show'])->name('arena.challenges.show');
    Route::post('/arena/challenges/{challenge}/accept', [ArenaChallengeController::class, 'accept'])->name('arena.challenges.accept');
    Route::post('/arena/challenges/{challenge}/decline', [ArenaChallengeController::class, 'decline'])->name('arena.challenges.decline');
    Route::post('/arena/challenges/{challenge}/cancel', [ArenaChallengeController::class, 'cancel'])->name('arena.challenges.cancel');
    Route::post('/arena/battles', [ArenaController::class, 'start'])->name('arena.battles.start');
    Route::get('/arena/battles/{battle}', [ArenaController::class, 'show'])->name('arena.battles.show');
    Route::post('/arena/battles/{battle}/actions', [InteractiveBattleActionController::class, 'store'])->name('arena.battles.actions.store');
    Route::get('/shop', [ShopController::class, 'index'])->name('shop');
    Route::post('/shop/items/{item}', [ShopController::class, 'buyItem'])->name('shop.items.buy');
    Route::post('/shop/inventory-slots', [ShopController::class, 'buyInventorySlot'])->name('shop.inventory-slots.buy');
    Route::post('/shop/services/rename-creature', [ShopController::class, 'renameCreature'])->name('shop.services.rename-creature');
    Route::post('/shop/services/reset-skills', [ShopController::class, 'resetSkills'])->name('shop.services.reset-skills');
    Route::post('/shop/services/reset-special', [ShopController::class, 'resetSpecial'])->name('shop.services.reset-special');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
    Route::post('/inventory-items/{inventoryItem}/use', [InventoryController::class, 'useItem'])->name('inventory-items.use');
    Route::post('/inventory-items/{inventoryItem}/move-to-creature', [InventoryController::class, 'moveToCreature'])->name('inventory-items.move-to-creature');
    Route::post('/inventory-items/{inventoryItem}/move-to-player', [InventoryController::class, 'moveToPlayer'])->name('inventory-items.move-to-player');
    Route::get('/help', [GamePageController::class, 'help'])->name('help');
});
