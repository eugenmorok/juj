<?php

namespace Database\Seeders;

use App\Models\EquipmentSlot;
use Illuminate\Database\Seeder;

class EquipmentSlotSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    private const SLOTS = [
        [
            'name' => 'Голова / сенсор / черепной модуль',
            'code' => 'head',
            'description' => 'Шлемы, сенсоры, черепные пластины и управляющие модули.',
        ],
        [
            'name' => 'Корпус / броня / шкура',
            'code' => 'body',
            'description' => 'Основная защита корпуса, бронирование, пластины и укрепленная шкура.',
        ],
        [
            'name' => 'Передние конечности / манипуляторы / когти',
            'code' => 'front-limbs',
            'description' => 'Усиление передних лап, манипуляторов, когтей и хватательных систем.',
        ],
        [
            'name' => 'Задние конечности / привод / лапы',
            'code' => 'rear-limbs',
            'description' => 'Приводы движения, лапы, суставы и элементы мобильности.',
        ],
        [
            'name' => 'Основное оружие / клыки / жало',
            'code' => 'primary-weapon',
            'description' => 'Главный источник урона: оружие, клыки, жала и боевые насадки.',
        ],
        [
            'name' => 'Дополнительное оружие / хвост / модуль',
            'code' => 'secondary-weapon',
            'description' => 'Вспомогательное вооружение, хвостовые системы и дополнительные модули.',
        ],
        [
            'name' => 'Защита / панцирь / щит',
            'code' => 'defense',
            'description' => 'Щиты, панцири и защитные подсистемы.',
        ],
        [
            'name' => 'Нейро-слот / процессор / инстинкт',
            'code' => 'neural',
            'description' => 'Процессоры, инстинктивные усилители и нейронные схемы.',
        ],
        [
            'name' => 'Артефакт / мутаген / ядро',
            'code' => 'artifact',
            'description' => 'Редкие усилители, мутагены, ядра и аномальные предметы.',
        ],
        [
            'name' => 'Аксессуар / ошейник / чип',
            'code' => 'accessory',
            'description' => 'Чипы, ошейники, малые аксессуары и вспомогательные устройства.',
        ],
    ];

    public function run(): void
    {
        foreach (self::SLOTS as $index => $slot) {
            EquipmentSlot::query()->updateOrCreate([
                'code' => $slot['code'],
            ], [
                'name' => $slot['name'],
                'description' => $slot['description'],
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
            ]);
        }
    }
}
