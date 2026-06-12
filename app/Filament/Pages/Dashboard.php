<?php

namespace App\Filament\Pages;

use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\Skill;
use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    protected static ?string $title = 'Инфопанель';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'stats' => [
                ['label' => 'Пользователи', 'value' => User::query()->count()],
                ['label' => 'Типы сущностей', 'value' => CreatureType::query()->count()],
                ['label' => 'Виды сущностей', 'value' => CreatureSpecies::query()->count()],
                ['label' => 'Сущности игроков', 'value' => Creature::query()->count()],
                ['label' => 'Навыки', 'value' => Skill::query()->count()],
            ],
            'links' => [
                ['label' => 'Типы сущностей', 'description' => 'Управление основными классами сущностей.', 'route' => 'filament.admin.resources.creature-types.index'],
                ['label' => 'Виды сущностей', 'description' => 'Базовые SPECIAL и доступность при создании.', 'route' => 'filament.admin.resources.creature-species.index'],
                ['label' => 'Навыки', 'description' => 'Стоимость, требования и доступность навыков.', 'route' => 'filament.admin.resources.skills.index'],
            ],
        ];
    }
}
