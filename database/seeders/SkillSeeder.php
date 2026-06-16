<?php

namespace Database\Seeders;

use App\Models\CreatureType;
use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    private const SKILLS = [
        [
            'name' => 'Толстая шкура',
            'code' => 'thick-hide',
            'description' => '+10% к броне в будущей боевой формуле.',
            'skill_type' => 'passive',
            'cost' => 15,
            'required_endurance' => 8,
            'is_starter_available' => true,
        ],
        [
            'name' => 'Быстрый удар',
            'code' => 'quick-strike',
            'description' => 'Повышенный шанс атаковать первым.',
            'skill_type' => 'active',
            'cost' => 12,
            'required_agility' => 8,
            'is_starter_available' => true,
        ],
        [
            'name' => 'Критическое чутье',
            'code' => 'critical-instinct',
            'description' => 'Бонус к критическому удару.',
            'skill_type' => 'passive',
            'cost' => 18,
            'required_perception' => 6,
            'required_luck' => 6,
            'is_starter_available' => true,
        ],
        [
            'name' => 'Саморемонт',
            'code' => 'self-repair',
            'description' => 'Восстановление части здоровья раз в несколько раундов.',
            'skill_type' => 'conditional',
            'cost' => 20,
            'required_type' => 'mechanoids',
            'required_intelligence' => 5,
            'is_starter_available' => true,
        ],
        [
            'name' => 'Ядовитое жало',
            'code' => 'venomous-sting',
            'description' => 'Периодический урон ядом.',
            'skill_type' => 'active',
            'cost' => 15,
            'required_type' => 'insects',
            'required_agility' => 6,
            'is_starter_available' => true,
        ],
        [
            'name' => 'Анализ слабости',
            'code' => 'weakness-analysis',
            'description' => 'Бонус против целей с меньшим Intelligence.',
            'skill_type' => 'active',
            'cost' => 20,
            'required_level' => 1,
            'required_intelligence' => 7,
            'is_starter_available' => false,
        ],
    ];

    public function run(): void
    {
        $types = CreatureType::query()
            ->whereIn('code', ['mechanoids', 'insects'])
            ->pluck('id', 'code');

        foreach (self::SKILLS as $skill) {
            Skill::query()->updateOrCreate([
                'code' => $skill['code'],
            ], [
                'name' => $skill['name'],
                'description' => $skill['description'],
                'skill_type' => $skill['skill_type'],
                'cost' => $skill['cost'],
                'required_level' => $skill['required_level'] ?? 1,
                'required_creature_type_id' => isset($skill['required_type']) ? $types[$skill['required_type']] ?? null : null,
                'required_creature_species_id' => null,
                'required_strength' => $skill['required_strength'] ?? 0,
                'required_perception' => $skill['required_perception'] ?? 0,
                'required_endurance' => $skill['required_endurance'] ?? 0,
                'required_charisma' => $skill['required_charisma'] ?? 0,
                'required_intelligence' => $skill['required_intelligence'] ?? 0,
                'required_agility' => $skill['required_agility'] ?? 0,
                'required_luck' => $skill['required_luck'] ?? 0,
                'effect' => $skill['description'],
                'cooldown_turns' => 0,
                'is_starter_available' => $skill['is_starter_available'],
                'is_active' => true,
            ]);
        }
    }
}
