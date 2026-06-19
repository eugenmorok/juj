<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $images = [
            'bear' => 'game-assets/creatures/animal-bear.webp',
            'boar' => 'game-assets/creatures/animal-boar.webp',
            'lynx' => 'game-assets/creatures/animal-lynx.webp',
            'mutant-rat' => 'game-assets/creatures/animal-mutant-rat.webp',
        ];

        foreach ($images as $code => $image) {
            DB::table('creature_species')
                ->where('code', $code)
                ->update([
                    'portrait_image' => $image,
                    'battle_image' => $image,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('creature_species')
            ->whereIn('code', ['bear', 'boar', 'lynx', 'mutant-rat'])
            ->update([
                'portrait_image' => 'game-assets/creatures/animal-wolf.webp',
                'battle_image' => 'game-assets/creatures/animal-wolf.webp',
                'updated_at' => now(),
            ]);
    }
};
