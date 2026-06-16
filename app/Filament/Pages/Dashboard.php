<?php

namespace App\Filament\Pages;

use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\EquipmentSlot;
use App\Models\Item;
use App\Models\Skill;
use App\Models\User;
use App\Models\ArenaSetting;
use App\Models\BalanceChangeLog;
use App\Models\Battle;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Инфопанель';

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.pages.dashboard')
                    ->viewData($this->getDashboardData()),
            ]);
    }

    /**
     * @return array{stats: array<int, array{label: string, value: int}>, links: array<int, array{label: string, description: string, route: string}>}
     */
    private function getDashboardData(): array
    {
        return [
            'stats' => [
                ['label' => 'Пользователи', 'value' => User::query()->count()],
                ['label' => 'Типы сущностей', 'value' => CreatureType::query()->count()],
                ['label' => 'Виды сущностей', 'value' => CreatureSpecies::query()->count()],
                ['label' => 'Сущности игроков', 'value' => Creature::query()->count()],
                ['label' => 'Навыки', 'value' => Skill::query()->count()],
                ['label' => 'Слоты экипировки', 'value' => EquipmentSlot::query()->count()],
                ['label' => 'Предметы', 'value' => Item::query()->count()],
                ['label' => 'Боты', 'value' => User::query()->where('is_bot', true)->count()],
                ['label' => 'Бои', 'value' => Battle::query()->count()],
                ['label' => 'Активные бои', 'value' => Battle::query()->where('status', Battle::STATUS_RUNNING)->count()],
                ['label' => 'Настройки баланса', 'value' => ArenaSetting::query()->count()],
                ['label' => 'Изменения баланса', 'value' => BalanceChangeLog::query()->count()],
            ],
            'links' => [
                ['label' => 'Игроки', 'description' => 'Баланс токенов, инвентарь, боты и быстрые экономические выдачи.', 'route' => 'filament.admin.resources.users.index'],
                ['label' => 'Типы сущностей', 'description' => 'Управление основными классами сущностей.', 'route' => 'filament.admin.resources.creature-types.index'],
                ['label' => 'Виды сущностей', 'description' => 'Базовые SPECIAL и доступность при создании.', 'route' => 'filament.admin.resources.creature-species.index'],
                ['label' => 'Навыки', 'description' => 'Стоимость, требования и доступность навыков.', 'route' => 'filament.admin.resources.skills.index'],
                ['label' => 'Слоты экипировки', 'description' => '10 базовых мест, которые занимают предметы сущности.', 'route' => 'filament.admin.resources.equipment-slots.index'],
                ['label' => 'Предметы', 'description' => 'Редкость, цена, бонусы, ограничения и слоты предметов.', 'route' => 'filament.admin.resources.items.index'],
                ['label' => 'Боты', 'description' => 'Псевдо игроки, генерация сущностей и частота появления.', 'route' => 'filament.admin.resources.bot-profiles.index'],
                ['label' => 'Бои', 'description' => 'Просмотр участников, статусов, логов и запуск безопасных симуляций без наград.', 'route' => 'filament.admin.resources.battles.index'],
                ['label' => 'Настройки арены', 'description' => 'Награды, опыт, токены, матчмейкинг, лимиты и экономика инвентаря.', 'route' => 'filament.admin.resources.arena-settings.index'],
                ['label' => 'Журнал баланса', 'description' => 'История изменений коэффициентов и лимитов баланса.', 'route' => 'filament.admin.resources.balance-change-logs.index'],
            ],
        ];
    }
}
