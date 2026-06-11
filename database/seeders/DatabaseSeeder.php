<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CreatureCatalogSeeder::class);
        $this->call(SkillSeeder::class);

        if (app()->environment('production') && ! env('ADMIN_PASSWORD')) {
            return;
        }

        User::updateOrCreate([
            'email' => env('ADMIN_EMAIL', 'admin@rpg-arena.test'),
        ], [
            'name' => env('ADMIN_NAME', 'Arena Admin'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            'level' => 1,
            'xp' => 0,
            'tokens' => 0,
            'inventory_slots' => 5,
            'is_bot' => false,
            'is_admin' => true,
        ]);
    }
}
