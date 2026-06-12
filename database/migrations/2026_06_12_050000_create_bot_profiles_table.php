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
        Schema::create('bot_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('style')->default('balanced');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('min_level')->default(1);
            $table->unsignedInteger('max_level')->default(3);
            $table->unsignedSmallInteger('spawn_chance')->default(100);
            $table->unsignedInteger('generated_creatures_count')->default(0);
            $table->timestamp('last_generated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'spawn_chance']);
            $table->index(['style', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_profiles');
    }
};
