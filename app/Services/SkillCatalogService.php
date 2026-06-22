<?php

namespace App\Services;

use App\Models\Creature;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SkillCatalogService
{
    private const MINIMUM_AVAILABLE_SKILLS = 4;

    /**
     * @var list<array{name: string, skill_type: string, cost: int, effect: string}>
     */
    private const GENERATED_SKILL_TEMPLATES = [
        [
            'name' => 'Тактическая стойка',
            'skill_type' => 'passive',
            'cost' => 8,
            'effect' => 'Базовая школа боя: помогает существу держать позицию и лучше читать темп арены.',
        ],
        [
            'name' => 'Контроль дистанции',
            'skill_type' => 'passive',
            'cost' => 10,
            'effect' => 'Боец учится не отдавать инициативу без необходимости.',
        ],
        [
            'name' => 'Живучий ритм',
            'skill_type' => 'conditional',
            'cost' => 12,
            'effect' => 'Тренировка выдержки для долгих обменов ударами.',
        ],
        [
            'name' => 'Полевой анализ',
            'skill_type' => 'active',
            'cost' => 14,
            'effect' => 'Простая тактическая привычка: замечать слабые стороны противника до решающего удара.',
        ],
        [
            'name' => 'Инстинкт уклонения',
            'skill_type' => 'passive',
            'cost' => 11,
            'effect' => 'Существо быстрее реагирует на очевидные атаки.',
        ],
        [
            'name' => 'Сдержанный натиск',
            'skill_type' => 'active',
            'cost' => 13,
            'effect' => 'Атака без лишней ярости: меньше ошибок, больше давления.',
        ],
    ];

    /**
     * @return Collection<int, Skill>
     */
    public function availableFor(Creature $creature): Collection
    {
        $creature->loadMissing('skills');

        return Skill::query()
            ->active()
            ->with(['requiredType', 'requiredSpecies'])
            ->orderBy('cost')
            ->orderBy('name')
            ->get()
            ->reject(fn (Skill $skill): bool => $creature->skills->contains('id', $skill->id))
            ->filter(fn (Skill $skill): bool => $skill->isAvailableFor($creature))
            ->values();
    }

    /**
     * @return Collection<int, Skill>
     */
    public function ensureMinimumAvailableFor(Creature $creature, int $minimum = self::MINIMUM_AVAILABLE_SKILLS): Collection
    {
        $available = $this->availableFor($creature);
        $missing = max(0, $minimum - $available->count());

        for ($index = 0; $index < $missing; $index++) {
            $this->createGeneratedSkill($creature);
        }

        return $missing > 0 ? $this->availableFor($creature) : $available;
    }

    private function createGeneratedSkill(Creature $creature): Skill
    {
        $template = self::GENERATED_SKILL_TEMPLATES[
            Skill::query()->where('code', 'like', 'generated-skill-%')->count()
            % count(self::GENERATED_SKILL_TEMPLATES)
        ];

        return Skill::query()->create([
            'name' => $template['name'].' #'.$this->shortCode(),
            'code' => $this->uniqueGeneratedCode(),
            'description' => $template['effect'],
            'skill_type' => $template['skill_type'],
            'cost' => $template['cost'] + max(0, (int) $creature->level - 1),
            'required_level' => min(max(1, (int) $creature->level), 3),
            'required_creature_type_id' => null,
            'required_creature_species_id' => null,
            'required_strength' => 0,
            'required_perception' => 0,
            'required_endurance' => 0,
            'required_charisma' => 0,
            'required_intelligence' => 0,
            'required_agility' => 0,
            'required_luck' => 0,
            'effect' => $template['effect'],
            'cooldown_turns' => 0,
            'is_starter_available' => false,
            'is_active' => true,
        ]);
    }

    private function uniqueGeneratedCode(): string
    {
        do {
            $code = 'generated-skill-'.Str::lower(Str::random(10));
        } while (Skill::query()->where('code', $code)->exists());

        return $code;
    }

    private function shortCode(): string
    {
        return Str::upper(Str::random(4));
    }
}
