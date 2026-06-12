<?php

namespace Database\Seeders;

use App\Models\BotProfile;
use App\Models\User;
use App\Services\BotGenerationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StarterBotSeeder extends Seeder
{
    /**
     * @var list<array{email: string, display_name: string, style: string, min_level: int, max_level: int, spawn_chance: int}>
     */
    private const BOTS = [
        [
            'email' => 'bot-aggressor@bots.rpg-arena.test',
            'display_name' => 'Arena Bot Aggressor',
            'style' => 'aggressive',
            'min_level' => 1,
            'max_level' => 2,
            'spawn_chance' => 100,
        ],
        [
            'email' => 'bot-guardian@bots.rpg-arena.test',
            'display_name' => 'Arena Bot Guardian',
            'style' => 'defensive',
            'min_level' => 1,
            'max_level' => 2,
            'spawn_chance' => 90,
        ],
        [
            'email' => 'bot-balanced@bots.rpg-arena.test',
            'display_name' => 'Arena Bot Balanced',
            'style' => 'balanced',
            'min_level' => 1,
            'max_level' => 3,
            'spawn_chance' => 80,
        ],
        [
            'email' => 'bot-scout@bots.rpg-arena.test',
            'display_name' => 'Arena Bot Scout',
            'style' => 'economical',
            'min_level' => 1,
            'max_level' => 2,
            'spawn_chance' => 70,
        ],
        [
            'email' => 'bot-chaos@bots.rpg-arena.test',
            'display_name' => 'Arena Bot Chaos',
            'style' => 'random',
            'min_level' => 1,
            'max_level' => 3,
            'spawn_chance' => 60,
        ],
    ];

    public function run(): void
    {
        $generator = app(BotGenerationService::class);

        foreach (self::BOTS as $bot) {
            $user = User::query()->firstOrCreate([
                'email' => $bot['email'],
            ], [
                'name' => $bot['display_name'],
                'password' => Hash::make(Str::random(32)),
                'level' => $bot['min_level'],
                'xp' => 0,
                'tokens' => 0,
                'inventory_slots' => 5,
                'is_bot' => true,
                'is_admin' => false,
            ]);

            $user->forceFill([
                'name' => $bot['display_name'],
                'level' => $bot['min_level'],
                'is_bot' => true,
                'is_admin' => false,
            ])->save();

            $profile = BotProfile::query()->updateOrCreate([
                'user_id' => $user->id,
            ], [
                'display_name' => $bot['display_name'],
                'style' => $bot['style'],
                'is_active' => true,
                'min_level' => $bot['min_level'],
                'max_level' => $bot['max_level'],
                'spawn_chance' => $bot['spawn_chance'],
                'notes' => 'Starter MVP arena bot.',
            ]);

            if (! $profile->user->creatures()->exists()) {
                $generator->generateCreature($profile, withEquipment: true);
            }
        }
    }
}
