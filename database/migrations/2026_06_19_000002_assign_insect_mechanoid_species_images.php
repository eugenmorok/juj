<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $images = [
            'war-beetle' => 'game-assets/creatures/insect-war-beetle.webp',
            'mantis' => 'game-assets/creatures/insect-mantis-v2.webp',
            'scorpion' => 'game-assets/creatures/insect-scorpion.webp',
            'fly-swarm' => 'game-assets/creatures/insect-fly-swarm.webp',
            'hunter-spider' => 'game-assets/creatures/insect-hunter-spider.webp',
            'scout-drone' => 'game-assets/creatures/mechanoid-scout-drone.webp',
            'turret' => 'game-assets/creatures/mechanoid-turret.webp',
            'servobot' => 'game-assets/creatures/mechanoid-servobot.webp',
            'combat-module' => 'game-assets/creatures/mechanoid-combat-module.webp',
            'repair-unit' => 'game-assets/creatures/mechanoid-repair-unit.webp',
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
            ->whereIn('code', ['war-beetle', 'mantis', 'scorpion', 'fly-swarm', 'hunter-spider'])
            ->update([
                'portrait_image' => 'game-assets/creatures/insect-mantis.webp',
                'battle_image' => 'game-assets/creatures/insect-mantis.webp',
                'updated_at' => now(),
            ]);

        DB::table('creature_species')
            ->whereIn('code', ['scout-drone', 'turret', 'servobot', 'combat-module', 'repair-unit'])
            ->update([
                'portrait_image' => 'game-assets/creatures/mechanoid.webp',
                'battle_image' => 'game-assets/creatures/mechanoid.webp',
                'updated_at' => now(),
            ]);
    }
};
