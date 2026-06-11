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
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('skill_type')->default('passive');
            $table->unsignedInteger('cost')->default(0);
            $table->unsignedInteger('required_level')->default(1);
            $table->foreignId('required_creature_type_id')->nullable()->constrained('creature_types')->nullOnDelete();
            $table->foreignId('required_creature_species_id')->nullable()->constrained('creature_species')->nullOnDelete();
            $table->unsignedSmallInteger('required_strength')->default(0);
            $table->unsignedSmallInteger('required_perception')->default(0);
            $table->unsignedSmallInteger('required_endurance')->default(0);
            $table->unsignedSmallInteger('required_charisma')->default(0);
            $table->unsignedSmallInteger('required_intelligence')->default(0);
            $table->unsignedSmallInteger('required_agility')->default(0);
            $table->unsignedSmallInteger('required_luck')->default(0);
            $table->text('effect')->nullable();
            $table->unsignedInteger('cooldown_turns')->default(0);
            $table->boolean('is_starter_available')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_starter_available']);
            $table->index('required_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
