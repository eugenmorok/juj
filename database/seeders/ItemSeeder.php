<?php

namespace Database\Seeders;

use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\Item;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ItemSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    private const ITEMS = [
        [
            'name' => 'Усиленная бронепластина',
            'code' => 'reinforced-hide-plate',
            'icon' => 'game-assets/shop/reinforced-hide-plate.webp',
            'description' => 'Грубая, но надежная защита корпуса для первых арен.',
            'item_type' => 'equipment',
            'rarity' => 'common',
            'price' => 80,
            'slot_key' => 'body',
            'slots_required' => ['body'],
            'bonuses' => ['endurance' => 2, 'defense' => 2],
        ],
        [
            'name' => 'Ядовитое жало',
            'code' => 'venom-sting',
            'icon' => 'game-assets/shop/venom-sting.webp',
            'description' => 'Редкая насадка для инсектов, усиливающая быстрые атаки.',
            'item_type' => 'equipment',
            'rarity' => 'rare',
            'price' => 140,
            'slot_key' => 'primary-weapon',
            'slots_required' => ['primary-weapon'],
            'allowed_type_codes' => ['insects'],
            'bonuses' => ['agility' => 1, 'luck' => 1, 'damage' => 3, 'poison_damage' => 5],
        ],
        [
            'name' => 'Боевой процессор',
            'code' => 'combat-processor',
            'icon' => 'game-assets/shop/combat-processor.webp',
            'description' => 'Элитный модуль анализа угроз для механоидов.',
            'item_type' => 'module',
            'rarity' => 'elite',
            'price' => 220,
            'required_level' => 2,
            'slot_key' => 'neural',
            'slots_required' => ['neural'],
            'allowed_type_codes' => ['mechanoids'],
            'bonuses' => ['perception' => 2, 'intelligence' => 2, 'charisma' => -1],
        ],
        [
            'name' => 'Лечебная сыворотка',
            'code' => 'healing-serum',
            'icon' => 'game-assets/shop/healing-serum.webp',
            'description' => 'Расходуемое зелье, восстанавливающее здоровье сущности.',
            'item_type' => 'potion',
            'rarity' => 'common',
            'price' => 40,
            'duration_type' => 'consumable',
            'uses_count' => 1,
            'bonuses' => ['heal' => 35],
        ],
        [
            'name' => 'Силовой стимулятор',
            'code' => 'strength-stimulant',
            'icon' => 'game-assets/shop/strength-stimulant.webp',
            'description' => 'Редкий расходник, навсегда повышающий силу сущности на 1.',
            'item_type' => 'consumable',
            'rarity' => 'rare',
            'price' => 110,
            'required_level' => 2,
            'duration_type' => 'consumable',
            'uses_count' => 1,
            'bonuses' => ['strength' => 1],
        ],
        [
            'name' => 'Гиперплазма здоровья',
            'code' => 'vital-plasma',
            'icon' => 'game-assets/shop/vital-plasma.webp',
            'description' => 'Элитный препарат, повышающий максимальное здоровье сущности.',
            'item_type' => 'potion',
            'rarity' => 'elite',
            'price' => 180,
            'required_level' => 2,
            'duration_type' => 'consumable',
            'uses_count' => 1,
            'bonuses' => ['hp' => 15],
        ],
        [
            'name' => 'Древнее ядро',
            'code' => 'ancient-core',
            'icon' => 'game-assets/shop/ancient-core.webp',
            'description' => 'Уникальный артефакт с сильным, но дорогим усилением.',
            'item_type' => 'artifact',
            'rarity' => 'unique',
            'price' => 500,
            'required_level' => 3,
            'slot_key' => 'artifact',
            'slots_required' => ['artifact'],
            'is_unique' => true,
            'bonuses' => ['intelligence' => 3, 'luck' => 2],
        ],
        [
            'name' => 'Ошейник вожака',
            'code' => 'pack-leader-collar',
            'icon' => 'game-assets/shop/pack-leader-collar.webp',
            'description' => 'Аксессуар для животных, раскрывающий лидерские инстинкты.',
            'item_type' => 'equipment',
            'rarity' => 'rare',
            'price' => 160,
            'slot_key' => 'accessory',
            'slots_required' => ['accessory'],
            'allowed_type_codes' => ['animals'],
            'allowed_species_codes' => ['wolf', 'lynx'],
            'bonuses' => ['charisma' => 2, 'perception' => 1],
        ],
        [
            'name' => 'Штурмовой визор',
            'code' => 'assault-visor',
            'icon' => 'game-assets/shop/assault-visor.webp',
            'description' => 'Оптический комплект для точного выбора зоны атаки.',
            'item_type' => 'equipment',
            'rarity' => 'common',
            'price' => 75,
            'slot_key' => 'head',
            'slots_required' => ['head'],
            'bonuses' => ['perception' => 2],
        ],
        [
            'name' => 'Импульсный резак',
            'code' => 'pulse-cutter',
            'icon' => 'game-assets/shop/pulse-cutter.webp',
            'description' => 'Компактное оружие ближнего боя с усиленным приводом.',
            'item_type' => 'equipment',
            'rarity' => 'rare',
            'price' => 150,
            'slot_key' => 'primary-weapon',
            'slots_required' => ['primary-weapon'],
            'bonuses' => ['strength' => 2, 'agility' => 1, 'damage' => 4],
        ],
        [
            'name' => 'Нейронный ускоритель',
            'code' => 'neural-accelerator',
            'icon' => 'game-assets/shop/neural-accelerator.webp',
            'description' => 'Модуль ускоренной обработки боевых сигналов.',
            'item_type' => 'module',
            'rarity' => 'elite',
            'price' => 240,
            'required_level' => 2,
            'slot_key' => 'neural',
            'slots_required' => ['neural'],
            'bonuses' => ['intelligence' => 2, 'agility' => 2],
        ],
        [
            'name' => 'Оберег стойкости',
            'code' => 'endurance-charm',
            'icon' => 'game-assets/shop/endurance-charm.webp',
            'description' => 'Талисман, помогающий выдерживать тяжёлые удары.',
            'item_type' => 'artifact',
            'rarity' => 'rare',
            'price' => 135,
            'slot_key' => 'accessory',
            'slots_required' => ['accessory'],
            'bonuses' => ['endurance' => 2, 'luck' => 1, 'defense' => 2],
        ],
        [
            'name' => 'Тактический стимулятор',
            'code' => 'tactical-stimulant',
            'icon' => 'game-assets/shop/tactical-stimulant.webp',
            'description' => 'Одноразовая смесь для повышения реакции сущности.',
            'item_type' => 'consumable',
            'rarity' => 'common',
            'price' => 55,
            'duration_type' => 'consumable',
            'uses_count' => 1,
            'bonuses' => ['agility' => 1],
        ],
        [
            'name' => 'Регенеративный гель',
            'code' => 'regenerative-gel',
            'icon' => 'game-assets/shop/regenerative-gel.webp',
            'description' => 'Полевое средство для быстрого восстановления здоровья.',
            'item_type' => 'potion',
            'rarity' => 'rare',
            'price' => 95,
            'duration_type' => 'consumable',
            'uses_count' => 2,
            'bonuses' => ['heal' => 50],
        ],
        [
            'name' => 'Сенсорный плащ',
            'code' => 'sensor-cloak',
            'icon' => 'game-assets/shop/sensor-cloak.webp',
            'description' => 'Защитное покрытие, затрудняющее чтение движений владельца.',
            'item_type' => 'equipment',
            'rarity' => 'elite',
            'price' => 260,
            'required_level' => 2,
            'slot_key' => 'body',
            'slots_required' => ['body'],
            'bonuses' => ['agility' => 2, 'charisma' => 2, 'defense' => 3],
        ],
        [
            'name' => 'Кристалл вероятности',
            'code' => 'probability-crystal',
            'icon' => 'game-assets/shop/probability-crystal.webp',
            'description' => 'Артефакт, слегка склоняющий случай в пользу владельца.',
            'item_type' => 'artifact',
            'rarity' => 'unique',
            'price' => 480,
            'required_level' => 3,
            'slot_key' => 'artifact',
            'slots_required' => ['artifact'],
            'is_unique' => true,
            'bonuses' => ['luck' => 4, 'perception' => 1],
        ],
    ];

    public function run(): void
    {
        $typeIdsByCode = CreatureType::query()->pluck('id', 'code');
        $speciesIdsByCode = CreatureSpecies::query()->pluck('id', 'code');

        foreach (self::ITEMS as $item) {
            $allowedTypeCodes = $item['allowed_type_codes'] ?? [];
            $allowedSpeciesCodes = $item['allowed_species_codes'] ?? [];

            unset($item['allowed_type_codes'], $item['allowed_species_codes']);

            Item::query()->updateOrCreate([
                'code' => $item['code'],
            ], [
                'name' => $item['name'],
                'icon' => $item['icon'] ?? null,
                'description' => $item['description'],
                'item_type' => $item['item_type'],
                'rarity' => $item['rarity'],
                'price' => $item['price'],
                'required_level' => $item['required_level'] ?? 1,
                'allowed_types' => $this->idsForCodes($typeIdsByCode, $allowedTypeCodes),
                'allowed_species' => $this->idsForCodes($speciesIdsByCode, $allowedSpeciesCodes),
                'slot_key' => $item['slot_key'] ?? null,
                'slots_required' => $item['slots_required'] ?? null,
                'bonuses' => $item['bonuses'] ?? null,
                'duration_type' => $item['duration_type'] ?? 'permanent',
                'uses_count' => $item['uses_count'] ?? null,
                'is_unique' => $item['is_unique'] ?? false,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param  Collection<string, int>  $idsByCode
     * @param  list<string>  $codes
     * @return list<int>|null
     */
    private function idsForCodes(Collection $idsByCode, array $codes): ?array
    {
        $ids = collect($codes)
            ->map(fn (string $code): ?int => $idsByCode[$code] ?? null)
            ->filter()
            ->values()
            ->all();

        return $ids === [] ? null : $ids;
    }
}
