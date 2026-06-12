<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arena_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->default('MVP balance');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('win_xp_per_level')->default(100);
            $table->unsignedInteger('draw_xp_per_level')->default(50);
            $table->unsignedInteger('loss_xp_per_level')->default(20);
            $table->unsignedInteger('win_development_points_per_level')->default(50);
            $table->unsignedInteger('draw_development_points_per_level')->default(25);
            $table->unsignedInteger('loss_development_points_per_level')->default(0);
            $table->unsignedInteger('win_tokens_per_level')->default(50);
            $table->unsignedInteger('draw_tokens_per_level')->default(25);
            $table->unsignedInteger('loss_tokens_per_level')->default(5);
            $table->unsignedInteger('xp_to_next_level_base')->default(100);
            $table->decimal('xp_to_next_level_exponent', 5, 2)->default(1.50);
            $table->unsignedInteger('level_up_development_points')->default(10);
            $table->unsignedInteger('level_up_hp_bonus')->default(5);
            $table->decimal('weak_opponent_power_ratio', 5, 2)->default(0.80);
            $table->decimal('weak_opponent_reward_multiplier', 5, 2)->default(0.50);
            $table->unsignedInteger('same_opponent_daily_limit')->default(3);
            $table->decimal('same_opponent_reward_multiplier', 5, 2)->default(0.50);
            $table->unsignedInteger('daily_full_reward_limit')->default(10);
            $table->decimal('daily_limit_reward_multiplier', 5, 2)->default(0.25);
            $table->decimal('minimum_reward_multiplier', 5, 2)->default(0.10);
            $table->unsignedInteger('matchmaking_level_difference')->default(2);
            $table->unsignedInteger('matchmaking_power_score_difference')->default(0);
            $table->decimal('power_score_level_weight', 6, 2)->default(10.00);
            $table->decimal('power_score_skill_weight', 6, 2)->default(1.00);
            $table->decimal('power_score_equipment_weight', 6, 2)->default(1.00);
            $table->unsignedInteger('daily_battle_limit')->default(0);
            $table->unsignedInteger('inventory_slot_base_cost')->default(100);
            $table->unsignedInteger('inventory_slot_step_cost')->default(25);
            $table->unsignedInteger('max_purchased_inventory_slots')->default(50);
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arena_settings');
    }
};
