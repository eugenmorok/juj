<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bot_profiles')
            ->where('spawn_chance', '>', 65)
            ->update(['spawn_chance' => 65]);
    }

    public function down(): void
    {
        // Previous per-profile values cannot be reconstructed safely.
    }
};
