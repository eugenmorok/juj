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
        Schema::create('item_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('bound_creature_id')->nullable()->constrained('creatures')->nullOnDelete();
            $table->unsignedSmallInteger('durability')->default(100);
            $table->string('state')->default('stored');
            $table->timestamps();

            $table->index(['owner_user_id', 'state']);
            $table->index(['bound_creature_id', 'state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_instances');
    }
};
