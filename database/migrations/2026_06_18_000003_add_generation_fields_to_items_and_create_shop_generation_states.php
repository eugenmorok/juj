<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('is_generated')->default(false)->after('is_active');
            $table->timestamp('generated_at')->nullable()->after('is_generated');
        });

        Schema::create('shop_generation_states', function (Blueprint $table) {
            $table->id();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_generation_states');

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['is_generated', 'generated_at']);
        });
    }
};
