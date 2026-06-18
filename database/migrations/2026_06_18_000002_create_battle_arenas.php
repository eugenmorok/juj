<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battle_arenas', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('background_image');
            $table->json('special_effects')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('battles', function (Blueprint $table): void {
            $table->foreignId('battle_arena_id')
                ->nullable()
                ->after('initiator_user_id')
                ->constrained('battle_arenas')
                ->nullOnDelete();
            $table->string('arena_name')->nullable()->after('battle_arena_id');
            $table->string('arena_background_image')->nullable()->after('arena_name');
            $table->json('arena_effects')->nullable()->after('arena_background_image');
        });
    }

    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('battle_arena_id');
            $table->dropColumn(['arena_name', 'arena_background_image', 'arena_effects']);
        });

        Schema::dropIfExists('battle_arenas');
    }
};
