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
        Schema::create('creatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creature_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('creature_species_id')->constrained('creature_species')->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedInteger('development_points')->default(0);
            $table->unsignedSmallInteger('strength');
            $table->unsignedSmallInteger('perception');
            $table->unsignedSmallInteger('endurance');
            $table->unsignedSmallInteger('charisma');
            $table->unsignedSmallInteger('intelligence');
            $table->unsignedSmallInteger('agility');
            $table->unsignedSmallInteger('luck');
            $table->unsignedInteger('current_hp');
            $table->unsignedInteger('max_hp');
            $table->unsignedInteger('inventory_slots')->default(5);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->boolean('is_available_for_battle')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['creature_type_id', 'creature_species_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creatures');
    }
};
