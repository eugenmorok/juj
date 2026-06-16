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
        Schema::create('battle_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number');
            $table->string('status')->default('collecting');
            $table->foreignId('first_actor_creature_id')->nullable()->constrained('creatures')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['battle_id', 'round_number']);
            $table->index(['battle_id', 'status']);
            $table->index(['deadline_at']);
        });

        Schema::create('battle_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('battle_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creature_id')->constrained()->cascadeOnDelete();
            $table->string('action_type')->default('strike');
            $table->string('attack_zone')->default('body');
            $table->string('defense_zone')->default('body');
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_auto')->default(false);
            $table->json('payload')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['battle_round_id', 'creature_id']);
            $table->index(['battle_id', 'creature_id']);
            $table->index(['action_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('battle_actions');
        Schema::dropIfExists('battle_rounds');
    }
};
