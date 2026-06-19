<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arena_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('bot_global_strength_percent')->default(100)->after('matchmaking_power_score_difference');
            $table->unsignedSmallInteger('bot_damage_percent')->default(80)->after('bot_global_strength_percent');
            $table->unsignedSmallInteger('player_vs_bot_damage_percent')->default(115)->after('bot_damage_percent');
            $table->unsignedSmallInteger('bot_matchmaking_max_power_percent')->default(97)->after('player_vs_bot_damage_percent');
            $table->unsignedSmallInteger('bot_matchmaking_power_gap')->default(5)->after('bot_matchmaking_max_power_percent');
        });
    }

    public function down(): void
    {
        Schema::table('arena_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'bot_global_strength_percent',
                'bot_damage_percent',
                'player_vs_bot_damage_percent',
                'bot_matchmaking_max_power_percent',
                'bot_matchmaking_power_gap',
            ]);
        });
    }
};
