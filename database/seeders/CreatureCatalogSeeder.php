<?php

namespace Database\Seeders;

use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use Illuminate\Database\Seeder;

class CreatureCatalogSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, description: string, creation_required_player_level?: int}>
     */
    private const TYPES = [
        'animals' => [
            'name' => 'Животные',
            'description' => 'Органические бойцы с хорошими природными инстинктами и выносливостью.',
        ],
        'mechanoids' => [
            'name' => 'Механоиды',
            'description' => 'Механические сущности с точными сенсорами, броней и стабильным уроном.',
        ],
        'insects' => [
            'name' => 'Инсекты',
            'description' => 'Быстрые и опасные сущности, которые берут числом, ловкостью и ядами.',
        ],
        'reptiles' => [
            'name' => 'Пресмыкающиеся',
            'description' => 'Холоднокровные потомки Разлома: панцири, яд, терпение и древняя память болот, песков и руин.',
            'creation_required_player_level' => 10,
        ],
    ];

    /**
     * @var list<array<string, mixed>>
     */
    private const SPECIES = [
        ['type' => 'animals', 'name' => 'Волк', 'code' => 'wolf', 's' => 7, 'p' => 6, 'e' => 6, 'c' => 3, 'i' => 3, 'a' => 8, 'l' => 5],
        ['type' => 'animals', 'name' => 'Медведь', 'code' => 'bear', 's' => 10, 'p' => 4, 'e' => 9, 'c' => 2, 'i' => 2, 'a' => 4, 'l' => 4],
        ['type' => 'animals', 'name' => 'Кабан', 'code' => 'boar', 's' => 8, 'p' => 4, 'e' => 8, 'c' => 2, 'i' => 2, 'a' => 5, 'l' => 4],
        ['type' => 'animals', 'name' => 'Рысь', 'code' => 'lynx', 's' => 5, 'p' => 7, 'e' => 5, 'c' => 3, 'i' => 3, 'a' => 9, 'l' => 6],
        ['type' => 'animals', 'name' => 'Крыса-мутант', 'code' => 'mutant-rat', 's' => 3, 'p' => 6, 'e' => 4, 'c' => 2, 'i' => 2, 'a' => 8, 'l' => 7],
        ['type' => 'mechanoids', 'name' => 'Дрон-разведчик', 'code' => 'scout-drone', 's' => 3, 'p' => 9, 'e' => 5, 'c' => 1, 'i' => 6, 'a' => 8, 'l' => 4],
        ['type' => 'mechanoids', 'name' => 'Турель', 'code' => 'turret', 's' => 8, 'p' => 8, 'e' => 8, 'c' => 1, 'i' => 4, 'a' => 2, 'l' => 3],
        ['type' => 'mechanoids', 'name' => 'Сервобот', 'code' => 'servobot', 's' => 5, 'p' => 6, 'e' => 7, 'c' => 2, 'i' => 7, 'a' => 4, 'l' => 4],
        ['type' => 'mechanoids', 'name' => 'Боевой модуль', 'code' => 'combat-module', 's' => 9, 'p' => 7, 'e' => 8, 'c' => 1, 'i' => 5, 'a' => 5, 'l' => 3],
        ['type' => 'mechanoids', 'name' => 'Ремонтный автомат', 'code' => 'repair-unit', 's' => 4, 'p' => 6, 'e' => 7, 'c' => 2, 'i' => 8, 'a' => 4, 'l' => 5],
        ['type' => 'insects', 'name' => 'Боевой жук', 'code' => 'war-beetle', 's' => 7, 'p' => 4, 'e' => 8, 'c' => 1, 'i' => 2, 'a' => 5, 'l' => 5],
        ['type' => 'insects', 'name' => 'Богомол', 'code' => 'mantis', 's' => 6, 'p' => 7, 'e' => 5, 'c' => 1, 'i' => 3, 'a' => 9, 'l' => 5],
        ['type' => 'insects', 'name' => 'Скорпион', 'code' => 'scorpion', 's' => 5, 'p' => 6, 'e' => 7, 'c' => 1, 'i' => 2, 'a' => 6, 'l' => 6],
        ['type' => 'insects', 'name' => 'Рой мух', 'code' => 'fly-swarm', 's' => 2, 'p' => 7, 'e' => 4, 'c' => 1, 'i' => 2, 'a' => 10, 'l' => 7],
        ['type' => 'insects', 'name' => 'Паук-охотник', 'code' => 'hunter-spider', 's' => 5, 'p' => 8, 'e' => 5, 'c' => 1, 'i' => 3, 'a' => 8, 'l' => 6],
        ['type' => 'reptiles', 'name' => 'Ящер-разведчик', 'code' => 'monitor-lizard', 's' => 6, 'p' => 7, 'e' => 6, 'c' => 2, 'i' => 4, 'a' => 8, 'l' => 5, 'description' => 'Хладнокровный следопыт руин: быстрый, наблюдательный и достаточно крепкий для долгих переходов.'],
        ['type' => 'reptiles', 'name' => 'Панцирная черепаха', 'code' => 'ironback-turtle', 's' => 6, 'p' => 4, 'e' => 10, 'c' => 2, 'i' => 3, 'a' => 2, 'l' => 5, 'description' => 'Медленный бастион с природной броней и упрямой живучестью.'],
        ['type' => 'reptiles', 'name' => 'Болотный крокодил', 'code' => 'marsh-crocodile', 's' => 9, 'p' => 5, 'e' => 8, 'c' => 2, 'i' => 2, 'a' => 4, 'l' => 4, 'description' => 'Тяжёлый хищник мутных каналов, который решает бой силой челюстей и терпением засады.'],
        ['type' => 'reptiles', 'name' => 'Песчаная гадюка', 'code' => 'sand-viper', 's' => 4, 'p' => 8, 'e' => 4, 'c' => 2, 'i' => 3, 'a' => 9, 'l' => 7, 'description' => 'Ядовитая дуэлянтка пустошей: редко держит удар, зато первой находит слабое место.'],
    ];

    public function run(): void
    {
        $types = [];

        foreach (self::TYPES as $code => $type) {
            $types[$code] = CreatureType::query()->updateOrCreate([
                'code' => $code,
            ], [
                'name' => $type['name'],
                'description' => $type['description'],
                'icon' => null,
                'type_bonus' => null,
                'type_weakness' => null,
                'creation_required_player_level' => $type['creation_required_player_level'] ?? 1,
                'is_active' => true,
            ]);
        }

        foreach (self::SPECIES as $species) {
            $battleImage = match ($species['code']) {
                'bear' => 'game-assets/creatures/animal-bear.webp',
                'boar' => 'game-assets/creatures/animal-boar.webp',
                'lynx' => 'game-assets/creatures/animal-lynx.webp',
                'mutant-rat' => 'game-assets/creatures/animal-mutant-rat.webp',
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
                'monitor-lizard' => 'game-assets/creatures/reptile-monitor-lizard.webp',
                'ironback-turtle' => 'game-assets/creatures/reptile-ironback-turtle.webp',
                'marsh-crocodile' => 'game-assets/creatures/reptile-marsh-crocodile.webp',
                'sand-viper' => 'game-assets/creatures/reptile-sand-viper.webp',
                default => match ($species['type']) {
                    'mechanoids' => 'game-assets/creatures/mechanoid.webp',
                    'insects' => 'game-assets/creatures/insect-mantis.webp',
                    'reptiles' => 'game-assets/creatures/reptile-monitor-lizard.webp',
                    default => 'game-assets/creatures/animal-wolf.webp',
                },
            };

            CreatureSpecies::query()->updateOrCreate([
                'code' => $species['code'],
            ], [
                'creature_type_id' => $types[$species['type']]->id,
                'name' => $species['name'],
                'description' => $species['description'] ?? 'Стартовый вид для создания первой сущности.',
                'portrait_image' => $battleImage,
                'battle_image' => $battleImage,
                'rarity' => 'common',
                'base_strength' => $species['s'],
                'base_perception' => $species['p'],
                'base_endurance' => $species['e'],
                'base_charisma' => $species['c'],
                'base_intelligence' => $species['i'],
                'base_agility' => $species['a'],
                'base_luck' => $species['l'],
                'is_starter_available' => true,
                'is_active' => true,
            ]);
        }
    }
}
