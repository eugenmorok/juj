<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arena_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('challenger_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('challenger_creature_id')->constrained('creatures')->cascadeOnDelete();
            $table->foreignId('defender_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('defender_creature_id')->constrained('creatures')->cascadeOnDelete();
            $table->boolean('defender_is_bot')->default(false);
            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->foreignId('battle_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['challenger_user_id', 'status', 'created_at']);
            $table->index(['defender_user_id', 'status', 'expires_at']);
            $table->index(['challenger_creature_id', 'status']);
            $table->index(['defender_creature_id', 'status']);
            $table->index(['battle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arena_challenges');
    }
};
