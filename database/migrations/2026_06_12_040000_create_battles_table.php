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
        Schema::create('battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('winner_creature_id')->nullable()->constrained('creatures')->nullOnDelete();
            $table->string('battle_type')->default('ranked');
            $table->string('status')->default('running');
            $table->boolean('is_draw')->default(false);
            $table->unsignedBigInteger('seed');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['battle_type', 'status']);
            $table->index(['initiator_user_id', 'started_at']);
            $table->index(['winner_creature_id']);
        });

        Schema::create('battle_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creature_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_bot')->default(false);
            $table->string('side');
            $table->string('result')->nullable();
            $table->unsignedInteger('power_score_before')->default(0);
            $table->unsignedInteger('hp_before')->default(0);
            $table->unsignedInteger('hp_after')->default(0);
            $table->unsignedInteger('level_before')->default(1);
            $table->unsignedInteger('level_after')->default(1);
            $table->unsignedInteger('reward_xp')->default(0);
            $table->unsignedInteger('reward_tokens')->default(0);
            $table->unsignedInteger('reward_development_points')->default(0);
            $table->decimal('reward_multiplier', 5, 2)->default(1);
            $table->timestamps();

            $table->unique(['battle_id', 'creature_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['creature_id', 'created_at']);
        });

        Schema::create('battle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round')->default(0);
            $table->string('event_type');
            $table->foreignId('actor_creature_id')->nullable()->constrained('creatures')->nullOnDelete();
            $table->foreignId('target_creature_id')->nullable()->constrained('creatures')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->text('text_log');
            $table->timestamps();

            $table->index(['battle_id', 'round']);
            $table->index(['event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('battle_events');
        Schema::dropIfExists('battle_participants');
        Schema::dropIfExists('battles');
    }
};
