<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arena_matchmaking_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creature_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('power_score')->default(0);
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'expires_at']);
            $table->index(['creature_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arena_matchmaking_sessions');
    }
};
