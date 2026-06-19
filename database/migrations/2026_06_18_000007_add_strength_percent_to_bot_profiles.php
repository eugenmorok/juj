<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('strength_percent')->default(100)->after('spawn_chance');
        });
    }

    public function down(): void
    {
        Schema::table('bot_profiles', function (Blueprint $table): void {
            $table->dropColumn('strength_percent');
        });
    }
};
