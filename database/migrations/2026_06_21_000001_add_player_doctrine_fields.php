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
            $table->unsignedInteger('doctrine_points')->default(0)->after('creature_creation_points');
            $table->unsignedInteger('doctrine_tactic')->default(0)->after('doctrine_points');
            $table->unsignedInteger('doctrine_command')->default(0)->after('doctrine_tactic');
            $table->unsignedInteger('doctrine_engineering')->default(0)->after('doctrine_command');
            $table->unsignedInteger('doctrine_breeding')->default(0)->after('doctrine_engineering');
            $table->unsignedInteger('doctrine_trade')->default(0)->after('doctrine_breeding');
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
                            'doctrine_points' => User::doctrinePointsEarnedForLevel((int) $user->level),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'doctrine_points',
                'doctrine_tactic',
                'doctrine_command',
                'doctrine_engineering',
                'doctrine_breeding',
                'doctrine_trade',
            ]);
        });
    }
};
