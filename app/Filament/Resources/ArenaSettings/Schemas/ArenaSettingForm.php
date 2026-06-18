<?php

namespace App\Filament\Resources\ArenaSettings\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArenaSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Активная настройка')
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make('Оформление арены')
                    ->schema([
                        FileUpload::make('battle_background_image')
                            ->label('Фон боевой сцены')
                            ->helperText('Рекомендуемый размер 1920 x 1080, формат WebP или PNG.')
                            ->disk('public')
                            ->directory('media/arena/backgrounds')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->maxSize(12288),
                    ]),
                Section::make('Награды за бой')
                    ->schema([
                        TextInput::make('win_xp_per_level')
                            ->label('XP за победу / уровень соперника')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('draw_xp_per_level')
                            ->label('XP за ничью / уровень соперника')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('loss_xp_per_level')
                            ->label('XP за поражение / уровень соперника')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('win_development_points_per_level')
                            ->label('Очки развития за победу')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('draw_development_points_per_level')
                            ->label('Очки развития за ничью')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('loss_development_points_per_level')
                            ->label('Очки развития за поражение')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('win_tokens_per_level')
                            ->label('Токены за победу')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('draw_tokens_per_level')
                            ->label('Токены за ничью')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('loss_tokens_per_level')
                            ->label('Токены за поражение')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(3),
                Section::make('Уровни и опыт')
                    ->schema([
                        TextInput::make('xp_to_next_level_base')
                            ->label('База XP до уровня')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('xp_to_next_level_exponent')
                            ->label('Степень формулы XP')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(1)
                            ->maxValue(5)
                            ->required(),
                        TextInput::make('level_up_development_points')
                            ->label('Очки развития за уровень')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('level_up_hp_bonus')
                            ->label('HP за уровень')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(4),
                Section::make('Антиабуз наград')
                    ->schema([
                        TextInput::make('weak_opponent_power_ratio')
                            ->label('Порог слабого соперника')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1)
                            ->required(),
                        TextInput::make('weak_opponent_reward_multiplier')
                            ->label('Множитель за слабого соперника')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1)
                            ->required(),
                        TextInput::make('same_opponent_daily_limit')
                            ->label('Полных наград с тем же соперником')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('same_opponent_reward_multiplier')
                            ->label('Множитель повтора соперника')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1)
                            ->required(),
                        TextInput::make('daily_full_reward_limit')
                            ->label('Полных наград в день')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('daily_limit_reward_multiplier')
                            ->label('Множитель после дневного лимита')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1)
                            ->required(),
                        TextInput::make('minimum_reward_multiplier')
                            ->label('Минимальный множитель')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1)
                            ->required(),
                        TextInput::make('daily_battle_limit')
                            ->label('Жесткий дневной лимит боев')
                            ->helperText('0 означает без жесткого лимита.')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(4),
                Section::make('Матчмейкинг и power score')
                    ->schema([
                        TextInput::make('matchmaking_level_difference')
                            ->label('Допустимая разница уровней')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('matchmaking_power_score_difference')
                            ->label('Допустимая разница power score')
                            ->helperText('0 означает без жесткого ограничения.')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('power_score_level_weight')
                            ->label('Вес уровня')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->required(),
                        TextInput::make('power_score_skill_weight')
                            ->label('Вес навыков')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->required(),
                        TextInput::make('power_score_equipment_weight')
                            ->label('Вес экипировки')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(3),
                Section::make('Экономика инвентаря')
                    ->schema([
                        TextInput::make('inventory_slot_base_cost')
                            ->label('Базовая цена ячейки')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('inventory_slot_step_cost')
                            ->label('Шаг цены ячейки')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('max_purchased_inventory_slots')
                            ->label('Максимум купленных ячеек')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(3),
            ]);
    }
}
