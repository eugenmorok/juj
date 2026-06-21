<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('perk_points')->default(0)->after('doctrine_trade');
            $table->json('player_perks')->nullable()->after('perk_points');
        });

        DB::table('users')
            ->where('is_bot', false)
            ->orderBy('id')
            ->select(['id', 'level'])
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'perk_points' => User::perkPointsEarnedForLevel((int) $user->level),
                            'player_perks' => json_encode([]),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'perk_points',
                'player_perks',
            ]);
        });
    }
};
