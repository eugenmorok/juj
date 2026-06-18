<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creature_species', function (Blueprint $table): void {
            $table->string('portrait_image')->nullable()->after('icon');
            $table->string('battle_image')->nullable()->after('portrait_image');
            $table->string('battle_spritesheet_image')->nullable()->after('battle_image');
            $table->string('battle_spritesheet_data')->nullable()->after('battle_spritesheet_image');
        });

        Schema::table('items', function (Blueprint $table): void {
            $table->string('effect_image')->nullable()->after('icon');
            $table->string('effect_sound')->nullable()->after('effect_image');
        });

        Schema::table('arena_settings', function (Blueprint $table): void {
            $table->string('battle_background_image')->nullable()->after('is_active');
        });

        $typeIds = DB::table('creature_types')->pluck('id', 'code');
        $starterImages = [
            'animals' => 'game-assets/creatures/animal-wolf.webp',
            'mechanoids' => 'game-assets/creatures/mechanoid.webp',
            'insects' => 'game-assets/creatures/insect-mantis.webp',
        ];

        foreach ($starterImages as $typeCode => $image) {
            $typeId = $typeIds->get($typeCode);

            if (! $typeId) {
                continue;
            }

            DB::table('creature_species')
                ->where('creature_type_id', $typeId)
                ->whereNull('battle_image')
                ->update([
                    'portrait_image' => $image,
                    'battle_image' => $image,
                ]);
        }

        DB::table('arena_settings')
            ->whereNull('battle_background_image')
            ->update([
                'battle_background_image' => 'game-assets/arena/industrial-fantasy-arena.webp',
            ]);
    }

    public function down(): void
    {
        Schema::table('arena_settings', function (Blueprint $table): void {
            $table->dropColumn('battle_background_image');
        });

        Schema::table('items', function (Blueprint $table): void {
            $table->dropColumn(['effect_image', 'effect_sound']);
        });

        Schema::table('creature_species', function (Blueprint $table): void {
            $table->dropColumn([
                'portrait_image',
                'battle_image',
                'battle_spritesheet_image',
                'battle_spritesheet_data',
            ]);
        });
    }
};
