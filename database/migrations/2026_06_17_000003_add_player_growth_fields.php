<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('creature_creation_points')->default(100)->after('tokens');
        });

        DB::table('users')
            ->whereExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('creatures')
                    ->whereColumn('creatures.user_id', 'users.id');
            })
            ->update(['creature_creation_points' => 0]);

        DB::table('users')
            ->where('is_bot', true)
            ->update(['creature_creation_points' => 0]);

        Schema::table('battle_participants', function (Blueprint $table): void {
            $table->unsignedInteger('reward_player_xp')->default(0)->after('reward_xp');
            $table->unsignedInteger('reward_creation_points')->default(0)->after('reward_development_points');
            $table->unsignedInteger('player_level_before')->nullable()->after('level_after');
            $table->unsignedInteger('player_level_after')->nullable()->after('player_level_before');
        });
    }

    public function down(): void
    {
        Schema::table('battle_participants', function (Blueprint $table): void {
            $table->dropColumn([
                'reward_player_xp',
                'reward_creation_points',
                'player_level_before',
                'player_level_after',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('creature_creation_points');
        });
    }
};
