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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('item_type');
            $table->string('rarity')->default('common');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('required_level')->default(1);
            $table->json('allowed_types')->nullable();
            $table->json('allowed_species')->nullable();
            $table->string('slot_key')->nullable();
            $table->json('slots_required')->nullable();
            $table->json('bonuses')->nullable();
            $table->string('duration_type')->default('permanent');
            $table->unsignedInteger('uses_count')->nullable();
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['item_type', 'rarity']);
            $table->index(['is_active', 'required_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
