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
        Schema::create('creature_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creature_id')->constrained('creatures')->cascadeOnDelete();
            $table->foreignId('item_instance_id')->constrained('item_instances')->cascadeOnDelete();
            $table->string('slot_key');
            $table->timestamps();

            $table->foreign('slot_key')->references('code')->on('equipment_slots')->restrictOnDelete();
            $table->unique(['creature_id', 'slot_key']);
            $table->index(['item_instance_id', 'slot_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creature_equipment');
    }
};
