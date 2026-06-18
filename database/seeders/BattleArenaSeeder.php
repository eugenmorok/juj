<?php

namespace Database\Seeders;

use App\Models\BattleArena;
use Illuminate\Database\Seeder;

class BattleArenaSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    private const ARENAS = [
        [
            'name' => 'Стальная цитадель',
            'code' => 'steel-citadel',
            'description' => 'Тяжелые укрепления повышают стойкость и расчетливость, но сковывают движения.',
            'background_image' => 'game-assets/arena/industrial-fantasy-arena.webp',
            'special_effects' => ['endurance' => 2, 'intelligence' => 1, 'agility' => -1],
        ],
        [
            'name' => 'Пепельные пустоши',
            'code' => 'ash-wastes',
            'description' => 'Пепел обостряет чувства и заставляет полагаться на удачу, но истощает организм.',
            'background_image' => 'game-assets/arena/ash-wastes.webp',
            'special_effects' => ['perception' => 2, 'luck' => 1, 'endurance' => -2],
        ],
        [
            'name' => 'Кристальная пещера',
            'code' => 'crystal-cavern',
            'description' => 'Кристаллы усиливают восприятие и интеллект, но мешают психологическому давлению.',
            'background_image' => 'game-assets/arena/crystal-cavern.webp',
            'special_effects' => ['perception' => 2, 'intelligence' => 2, 'charisma' => -1],
        ],
        [
            'name' => 'Заросшие руины',
            'code' => 'overgrown-ruins',
            'description' => 'Живая среда помогает двигаться инстинктивно, но густая растительность закрывает обзор.',
            'background_image' => 'game-assets/arena/overgrown-ruins.webp',
            'special_effects' => ['agility' => 2, 'luck' => 1, 'perception' => -1],
        ],
        [
            'name' => 'Грозовая платформа',
            'code' => 'storm-platform',
            'description' => 'Электрическое поле ускоряет реакцию и обостряет чувства, снижая выносливость.',
            'background_image' => 'game-assets/arena/storm-platform.webp',
            'special_effects' => ['agility' => 2, 'perception' => 1, 'endurance' => -1],
        ],
        [
            'name' => 'Древний колизей',
            'code' => 'ancient-colosseum',
            'description' => 'Арена славы усиливает силу и боевой дух, но провоцирует прямолинейные решения.',
            'background_image' => 'game-assets/arena/ancient-colosseum.webp',
            'special_effects' => ['strength' => 2, 'charisma' => 1, 'intelligence' => -1],
        ],
    ];

    public function run(): void
    {
        foreach (self::ARENAS as $index => $arena) {
            BattleArena::query()->updateOrCreate(
                ['code' => $arena['code']],
                [
                    ...$arena,
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                ],
            );
        }
    }
}
