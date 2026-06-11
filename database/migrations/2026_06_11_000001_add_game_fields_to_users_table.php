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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('level')->default(1)->after('password');
            $table->unsignedBigInteger('xp')->default(0)->after('level');
            $table->unsignedBigInteger('tokens')->default(0)->after('xp');
            $table->unsignedInteger('inventory_slots')->default(5)->after('tokens');
            $table->boolean('is_bot')->default(false)->after('inventory_slots');
            $table->boolean('is_admin')->default(false)->after('is_bot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'level',
                'xp',
                'tokens',
                'inventory_slots',
                'is_bot',
                'is_admin',
            ]);
        });
    }
};
