<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('arena_setting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('changed_fields');
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['arena_setting_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_change_logs');
    }
};
