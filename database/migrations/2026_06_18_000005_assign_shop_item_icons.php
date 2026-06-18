<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $icons = [
            'reinforced-hide-plate',
            'venom-sting',
            'combat-processor',
            'healing-serum',
            'strength-stimulant',
            'vital-plasma',
            'ancient-core',
            'pack-leader-collar',
            'assault-visor',
            'pulse-cutter',
            'neural-accelerator',
            'endurance-charm',
            'tactical-stimulant',
            'regenerative-gel',
            'sensor-cloak',
            'probability-crystal',
        ];

        foreach ($icons as $code) {
            DB::table('items')
                ->where('code', $code)
                ->update(['icon' => "game-assets/shop/{$code}.webp"]);
        }

        $fallbacks = [
            'potion' => 'game-assets/shop/healing-serum.webp',
            'consumable' => 'game-assets/shop/tactical-stimulant.webp',
            'module' => 'game-assets/shop/combat-processor.webp',
            'artifact' => 'game-assets/shop/ancient-core.webp',
            'equipment' => 'game-assets/shop/reinforced-hide-plate.webp',
        ];

        foreach ($fallbacks as $type => $icon) {
            DB::table('items')
                ->where('item_type', $type)
                ->whereNull('icon')
                ->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        DB::table('items')
            ->where('icon', 'like', 'game-assets/shop/%')
            ->update(['icon' => null]);
    }
};
