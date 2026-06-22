<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('creature_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('creation_required_player_level')
                ->default(1)
                ->after('type_weakness')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('creature_types', function (Blueprint $table) {
            $table->dropColumn('creation_required_player_level');
        });
    }
};
