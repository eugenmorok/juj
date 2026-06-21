<?php

namespace App\Support;

use App\Models\Battle;
use App\Models\BattleEvent;
use App\Models\BattleParticipant;
use App\Models\Creature;
use App\Services\BattleArenaService;

final class BattlePresentation
{
    /**
     * @return array<string, mixed>
     */
    public static function participant(BattleParticipant $participant): array
    {
        $creature = $participant->creature;
        $special = $creature?->effectiveSpecialValues() ?? [];

        foreach (($creature?->user?->battleSupportBonus() ?? []) as $attribute => $value) {
            $special[$attribute] = ($special[$attribute] ?? 0) + $value;
        }

        $special = app(BattleArenaService::class)->applyEffects(
            $special,
            $participant->battle?->arena_effects,
        );
        $bonuses = $creature?->equipmentBonuses() ?? [];
        $damageBase = Creature::damageFromSpecial($special);
        $defenseBase = Creature::defenseFromSpecial($special);
        $damageBonus = Creature::applyEquipmentCombatMastery(Creature::damageBonusFromBonuses($bonuses), $creature?->user);
        $defenseBonus = Creature::applyEquipmentCombatMastery(Creature::defenseBonusFromBonuses($bonuses), $creature?->user);

        return [
            'participant_id' => $participant->id,
            'user_id' => $participant->user_id,
            'creature_id' => $participant->creature_id,
            'creature_name' => $creature?->name,
            'owner_name' => $creature?->user?->name,
            'side' => $participant->side,
            'image_url' => self::creatureImage($creature),
            'portrait_url' => self::creaturePortrait($creature),
            'type_name' => $creature?->type?->name,
            'species_name' => $creature?->species?->name,
            'special' => $special,
            'combat' => [
                'damage' => max(1, $damageBase + $damageBonus),
                'defense' => max(0, $defenseBase + $defenseBonus),
            ],
            'spritesheet_image_url' => MediaUrl::resolve($creature?->species?->battle_spritesheet_image),
            'spritesheet_data_url' => MediaUrl::resolve($creature?->species?->battle_spritesheet_data),
            'hp_after' => $participant->hp_after,
            'hp_before' => $participant->hp_before,
            'result' => $participant->result,
            'level_before' => $participant->level_before,
            'level_after' => $participant->level_after,
            'reward_xp' => $participant->reward_xp,
            'reward_player_xp' => $participant->reward_player_xp,
            'reward_tokens' => $participant->reward_tokens,
            'reward_development_points' => $participant->reward_development_points,
            'reward_creation_points' => $participant->reward_creation_points,
            'reward_multiplier' => $participant->reward_multiplier,
            'power_score_before' => $participant->power_score_before,
            'player_level_before' => $participant->player_level_before,
            'player_level_after' => $participant->player_level_after,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function event(BattleEvent $event): array
    {
        return [
            'id' => $event->id,
            'round' => $event->round,
            'event_type' => $event->event_type,
            'actor_creature_id' => $event->actor_creature_id,
            'target_creature_id' => $event->target_creature_id,
            'actor_name' => $event->actor?->name,
            'target_name' => $event->target?->name,
            'payload' => $event->payload ?? [],
            'text' => $event->text_log,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function arena(Battle $battle): array
    {
        return [
            'name' => $battle->arena_name ?? 'Стальная цитадель',
            'background_url' => MediaUrl::resolve($battle->arena_background_image)
                ?? asset('game-assets/arena/industrial-fantasy-arena.webp'),
            'effects' => app(BattleArenaService::class)->normalizedEffects($battle->arena_effects),
        ];
    }

    private static function creatureImage(?Creature $creature): string
    {
        return MediaUrl::resolve($creature?->species?->battle_image)
            ?? self::fallbackCreatureImage($creature);
    }

    private static function creaturePortrait(?Creature $creature): string
    {
        return MediaUrl::resolve($creature?->species?->portrait_image)
            ?? MediaUrl::resolve($creature?->species?->icon)
            ?? self::fallbackCreatureImage($creature);
    }

    private static function fallbackCreatureImage(?Creature $creature): string
    {
        $path = match ($creature?->type?->code) {
            'mechanoids' => 'game-assets/creatures/mechanoid.webp',
            'insects' => 'game-assets/creatures/insect-mantis.webp',
            default => 'game-assets/creatures/animal-wolf.webp',
        };

        return asset($path);
    }
}
