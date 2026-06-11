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
        Schema::create('creature_species', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creature_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('rarity')->default('common');
            $table->unsignedTinyInteger('base_strength')->default(1);
            $table->unsignedTinyInteger('base_perception')->default(1);
            $table->unsignedTinyInteger('base_endurance')->default(1);
            $table->unsignedTinyInteger('base_charisma')->default(1);
            $table->unsignedTinyInteger('base_intelligence')->default(1);
            $table->unsignedTinyInteger('base_agility')->default(1);
            $table->unsignedTinyInteger('base_luck')->default(1);
            $table->boolean('is_starter_available')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['creature_type_id', 'name']);
            $table->index(['creature_type_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creature_species');
    }
};
