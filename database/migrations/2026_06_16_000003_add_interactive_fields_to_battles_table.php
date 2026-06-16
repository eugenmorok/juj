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
        Schema::table('battles', function (Blueprint $table) {
            $table->string('mode')->default('instant')->after('battle_type');
            $table->unsignedSmallInteger('current_round')->default(0)->after('is_draw');
            $table->foreignId('current_actor_creature_id')->nullable()->after('current_round')->constrained('creatures')->nullOnDelete();
            $table->timestamp('turn_deadline_at')->nullable()->after('current_actor_creature_id');

            $table->index(['mode', 'status']);
            $table->index(['current_actor_creature_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->dropIndex(['mode', 'status']);
            $table->dropIndex(['current_actor_creature_id']);
            $table->dropConstrainedForeignId('current_actor_creature_id');
            $table->dropColumn(['mode', 'current_round', 'turn_deadline_at']);
        });
    }
};
