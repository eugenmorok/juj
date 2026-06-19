<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

App\Models\User::query()->updateOrCreate([
    'email' => 'shop-visual-check@local.test',
], [
    'name' => 'Shop Visual Check',
    'password' => Illuminate\Support\Facades\Hash::make('VisualCheck-2026!'),
    'level' => 5,
    'xp' => 0,
    'tokens' => 1500,
    'inventory_slots' => 10,
    'is_bot' => false,
    'is_admin' => false,
]);
