<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('creature_id')->nullable()->constrained('creatures')->cascadeOnDelete();
            $table->string('inventory_type');
            $table->unsignedSmallInteger('slots')->default(0);
            $table->timestamps();

            $table->index(['owner_user_id', 'inventory_type']);
            $table->unique('creature_id');
        });

        $now = now();

        DB::table('users')
            ->orderBy('id')
            ->get(['id', 'level', 'inventory_slots'])
            ->each(function (object $user) use ($now): void {
                $baseSlots = 5 + (((int) $user->level) * 2);
                $purchasedSlots = max(0, ((int) $user->inventory_slots) - 5);

                DB::table('inventories')->insert([
                    'owner_user_id' => $user->id,
                    'creature_id' => null,
                    'inventory_type' => 'player',
                    'slots' => $baseSlots + $purchasedSlots,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        DB::table('creatures')
            ->orderBy('id')
            ->get(['id', 'user_id', 'level', 'endurance'])
            ->each(function (object $creature) use ($now): void {
                DB::table('inventories')->insert([
                    'owner_user_id' => $creature->user_id,
                    'creature_id' => $creature->id,
                    'inventory_type' => 'creature',
                    'slots' => 3 + intdiv((int) $creature->endurance, 10) + intdiv((int) $creature->level, 3),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
