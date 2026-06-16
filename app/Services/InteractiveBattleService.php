<?php

namespace App\Services;

use App\Events\BattleStateUpdated;
use App\Jobs\ResolveInteractiveBattleRound;
use App\Models\Battle;
use App\Models\BattleAction;
use App\Models\BattleEvent;
use App\Models\BattleParticipant;
use App\Models\BattleRound;
use App\Models\Creature;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\ItemInstance;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InteractiveBattleService
{
    public const ACTION_SECONDS = 6;

    private const MAX_ROUNDS = 20;

    private const STATE_CACHE_TTL_SECONDS = 43200;

    public function __construct(
        private readonly PowerScoreService $powerScore,
        private readonly BattleRewardService $battleRewards,
    ) {}

    public function start(Creature $challengerCreature, Creature $defenderCreature, ?User $initiator = null): Battle
    {
        $seed = random_int(1, 2_147_483_646);

        $battle = DB::transaction(function () use ($challengerCreature, $defenderCreature, $initiator, $seed): Battle {
            $challengerCreature = $this->freshCreature($challengerCreature);
            $defenderCreature = $this->freshCreature($defenderCreature);

            $battle = Battle::query()->create([
                'initiator_user_id' => $initiator?->id,
                'battle_type' => Battle::TYPE_RANKED,
                'mode' => Battle::MODE_INTERACTIVE,
                'status' => Battle::STATUS_RUNNING,
                'is_draw' => false,
                'seed' => $seed,
                'started_at' => now(),
            ]);

            $this->participant($battle, $challengerCreature, 'challenger');
            $this->participant($battle, $defenderCreature, 'defender');

            Creature::query()
                ->whereIn('id', [$challengerCreature->id, $defenderCreature->id])
                ->update(['is_available_for_battle' => false]);

            $this->event($battle, 0, 'interactive_battle_started', null, null, [
                'seed' => $seed,
                'action_seconds' => self::ACTION_SECONDS,
                'first_actor_creature_id' => $defenderCreature->id,
            ], "Р‘РѕР№ РЅР°С‡РёРЅР°РµС‚СЃСЏ: {$challengerCreature->name} РїСЂРѕС‚РёРІ {$defenderCreature->name}. РџРµСЂРІС‹Р№ С‚РµРјРї Сѓ {$defenderCreature->name}.");

            $round = $this->createRound($battle, 1, $defenderCreature->id);
            $this->createBotActions($battle, $round);

            if ($this->roundHasAllActions($battle, $round)) {
                $this->dispatchRoundResolution($battle, $round, immediate: true);
            }

            return $battle;
        });

        $battle = $battle->refresh();
        $this->syncRealtimeState($battle);

        return $this->loadForDisplay($battle);
    }

    public function prepare(Battle $battle): Battle
    {
        return $this->loadForDisplay($battle->refresh());
    }

    public function processBattle(int|Battle $battle): Battle
    {
        $battleId = $battle instanceof Battle ? $battle->id : $battle;

        DB::transaction(function () use ($battleId): void {
            $battle = Battle::query()
                ->whereKey($battleId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $battle->isInteractive() || $battle->status !== Battle::STATUS_RUNNING) {
                return;
            }

            while ($battle->status === Battle::STATUS_RUNNING) {
                $round = $this->currentRound($battle);

                if (! $round) {
                    $firstActorId = $battle->participants()->orderBy('id')->value('creature_id');
                    $round = $this->createRound($battle, max(1, $battle->current_round + 1), (int) $firstActorId);
                }

                $this->createBotActions($battle, $round);
                $round = $round->refresh();

                if ($round->deadline_at && $round->deadline_at->isPast()) {
                    $this->createMissingAutoActions($battle, $round);
                    $round = $round->refresh();
                }

                if (! $this->roundHasAllActions($battle, $round)) {
                    break;
                }

                $this->resolveRound($battle, $round);

                $battle = $battle->refresh();
                if ($battle->status !== Battle::STATUS_RUNNING) {
                    break;
                }

                $nextFirstActorId = $this->nextFirstActorId($battle, $round);
                $nextRound = $this->createRound($battle, $round->round_number + 1, $nextFirstActorId);
                $this->createBotActions($battle, $nextRound);

                if (! $this->roundHasAllActions($battle, $nextRound)) {
                    break;
                }

                $battle = $battle->refresh();
            }
        });

        $battle = Battle::query()->findOrFail($battleId);
        $this->syncRealtimeState($battle);

        return $this->loadForDisplay($battle);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function submitAction(User $user, Battle $battle, array $attributes): Battle
    {
        $battle = DB::transaction(function () use ($user, $battle, $attributes): Battle {
            $battle = Battle::query()
                ->whereKey($battle->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureInteractiveRunning($battle);

            $round = $this->currentRound($battle);
            if (! $round?->isCollecting()) {
                throw ValidationException::withMessages([
                    'battle' => 'РЎРµР№С‡Р°СЃ РЅРµС‚ Р°РєС‚РёРІРЅРѕРіРѕ С€Р°РіР° РґР»СЏ РІС‹Р±РѕСЂР° С‚Р°РєС‚РёРєРё.',
                ]);
            }

            if ($round->deadline_at && $round->deadline_at->isPast()) {
                throw ValidationException::withMessages([
                    'battle' => 'Р’СЂРµРјСЏ РЅР° РІС‹Р±РѕСЂ С‚Р°РєС‚РёРєРё СѓР¶Рµ РёСЃС‚РµРєР»Рѕ. Р”РѕР¶РґРёС‚РµСЃСЊ РѕР±РЅРѕРІР»РµРЅРёСЏ С€Р°РіР°.',
                ]);
            }

            $participant = $this->participantForUser($battle, $user);

            if (BattleAction::query()->where('battle_round_id', $round->id)->where('creature_id', $participant->creature_id)->exists()) {
                throw ValidationException::withMessages([
                    'battle' => 'РўР°РєС‚РёРєР° РґР»СЏ СЌС‚РѕРіРѕ С€Р°РіР° СѓР¶Рµ РІС‹Р±СЂР°РЅР°.',
                ]);
            }

            $attackZone = $this->zone((string) ($attributes['attack_zone'] ?? 'body'), 'attack_zone');
            $defenseZone = $this->zone((string) ($attributes['defense_zone'] ?? 'body'), 'defense_zone');
            $inventoryItemId = $attributes['inventory_item_id'] ?? null;

            if ($inventoryItemId !== null && $inventoryItemId !== '') {
                $this->ensureConsumableAvailable($user, $participant->creature, (int) $inventoryItemId);
            } else {
                $inventoryItemId = null;
            }

            BattleAction::query()->create([
                'battle_id' => $battle->id,
                'battle_round_id' => $round->id,
                'user_id' => $user->id,
                'creature_id' => $participant->creature_id,
                'action_type' => $inventoryItemId ? BattleAction::TYPE_ITEM : BattleAction::TYPE_STRIKE,
                'attack_zone' => $attackZone,
                'defense_zone' => $defenseZone,
                'inventory_item_id' => $inventoryItemId,
                'is_auto' => false,
                'submitted_at' => now(),
            ]);

            if ($this->roundHasAllActions($battle, $round)) {
                $this->dispatchRoundResolution($battle, $round, immediate: true);
            }

            return $battle;
        });

        $battle = $battle->refresh();
        $this->syncRealtimeState($battle);

        return $this->loadForDisplay($battle);
    }

    public function loadForDisplay(Battle $battle): Battle
    {
        return $battle->load([
            'participants.creature.user',
            'rounds.firstActor',
            'rounds.actions.creature',
            'rounds.actions.inventoryItem.itemInstance.item',
            'events.actor',
            'events.target',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function statePayload(Battle $battle, User $viewer, bool $includeFragments = false): array
    {
        $snapshot = $this->cachedState($battle);
        $ownParticipant = collect($snapshot['participants'])->firstWhere('user_id', $viewer->id);
        $ownCreatureId = $ownParticipant['creature_id'] ?? null;
        $actionCreatureIds = $snapshot['active_round']['action_creature_ids'] ?? [];

        $payload = [
            ...$snapshot,
            'active_round' => $snapshot['active_round'] ? [
                ...$snapshot['active_round'],
                'own_action_submitted' => $ownCreatureId ? in_array($ownCreatureId, $actionCreatureIds, true) : false,
            ] : null,
        ];

        $payload['marker'] = $this->battleMarker($payload);

        if ($includeFragments) {
            $payload['fragments'] = $this->renderBattleFragments($battle, $viewer);
        }

        return $payload;
    }

    /**
     * @return array{battle: Battle, activeRound: BattleRound|null, ownParticipant: BattleParticipant|null, ownAction: BattleAction|null, zones: array<string, string>, availableConsumables: Collection<int, InventoryItem>, isInteractiveRunning: bool}
     */
    public function viewData(Battle $battle, User $viewer): array
    {
        $battle = $this->loadForDisplay($battle);
        $ownParticipant = $battle->participants->firstWhere('user_id', $viewer->id);
        $activeRound = $battle->rounds->firstWhere('round_number', $battle->current_round);
        $ownAction = $activeRound?->actions->firstWhere('creature_id', $ownParticipant?->creature_id);
        $isInteractiveRunning = $battle->isInteractive() && $battle->status === Battle::STATUS_RUNNING;

        return [
            'battle' => $battle,
            'activeRound' => $activeRound,
            'ownParticipant' => $ownParticipant,
            'ownAction' => $ownAction,
            'zones' => BattleAction::ZONES,
            'availableConsumables' => $isInteractiveRunning && $ownParticipant
                ? $this->availableConsumables($viewer, $ownParticipant->creature)
                : collect(),
            'isInteractiveRunning' => $isInteractiveRunning,
        ];
    }

    /**
     * @return Collection<int, InventoryItem>
     */
    public function availableConsumables(User $user, Creature $creature): Collection
    {
        return InventoryItem::query()
            ->with(['inventory', 'itemInstance.item'])
            ->whereHas('inventory', fn ($query) => $query
                ->where('owner_user_id', $user->id)
                ->where(function ($scope) use ($creature): void {
                    $scope
                        ->where('inventory_type', Inventory::TYPE_PLAYER)
                        ->orWhere('creature_id', $creature->id);
                }))
            ->get()
            ->filter(function (InventoryItem $inventoryItem) use ($creature): bool {
                $itemInstance = $inventoryItem->itemInstance;
                $item = $itemInstance?->item;

                return $itemInstance
                    && $item
                    && $itemInstance->state === 'stored'
                    && $itemInstance->remainingUses() > 0
                    && $item->isConsumable()
                    && $item->canBeUsedBy($creature);
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function syncRealtimeState(Battle|int $battle): array
    {
        $battle = $battle instanceof Battle ? $battle->refresh() : Battle::query()->findOrFail($battle);
        $snapshot = $this->buildStateSnapshot($battle);

        Cache::put($this->stateCacheKey($battle->id), $snapshot, now()->addSeconds(self::STATE_CACHE_TTL_SECONDS));

        event(new BattleStateUpdated(
            battleId: $battle->id,
            status: $snapshot['status'],
            currentRound: $snapshot['current_round'],
            turnDeadlineAt: $snapshot['turn_deadline_at'],
            latestEventId: $snapshot['latest_event_id'],
        ));

        return $snapshot;
    }

    private function freshCreature(Creature $creature): Creature
    {
        return Creature::query()
            ->with(['user.botProfile', 'skills', 'equipmentRows.itemInstance.item'])
            ->whereKey($creature->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function participant(Battle $battle, Creature $creature, string $side): BattleParticipant
    {
        $maxHp = $this->battleMaxHp($creature);

        return $battle->participants()->create([
            'user_id' => $creature->user_id,
            'creature_id' => $creature->id,
            'is_bot' => (bool) $creature->user?->is_bot,
            'side' => $side,
            'power_score_before' => $this->powerScore->calculate($creature),
            'hp_before' => $maxHp,
            'hp_after' => $maxHp,
            'level_before' => $creature->level,
            'level_after' => $creature->level,
        ]);
    }

    private function createRound(Battle $battle, int $roundNumber, int $firstActorCreatureId): BattleRound
    {
        $deadline = now()->addSeconds(self::ACTION_SECONDS);

        $round = $battle->rounds()->create([
            'round_number' => $roundNumber,
            'status' => BattleRound::STATUS_COLLECTING,
            'first_actor_creature_id' => $firstActorCreatureId,
            'started_at' => now(),
            'deadline_at' => $deadline,
        ]);

        $battle->forceFill([
            'current_round' => $roundNumber,
            'current_actor_creature_id' => $firstActorCreatureId,
            'turn_deadline_at' => $deadline,
        ])->save();

        $firstActorName = Creature::query()->whereKey($firstActorCreatureId)->value('name') ?? 'СѓС‡Р°СЃС‚РЅРёРє';
        $this->event($battle, $roundNumber, 'round_collecting', null, null, [
            'deadline_at' => $deadline->toISOString(),
            'first_actor_creature_id' => $firstActorCreatureId,
        ], "РЁР°Рі {$roundNumber}: РІС‹Р±РѕСЂ С‚Р°РєС‚РёРєРё РѕС‚РєСЂС‹С‚ РЅР° ".self::ACTION_SECONDS." СЃРµРєСѓРЅРґ. РџРµСЂРІС‹Р№ С‚РµРјРї Сѓ {$firstActorName}.");

        $this->dispatchRoundResolution($battle, $round);

        return $round;
    }

    private function createBotActions(Battle $battle, BattleRound $round): void
    {
        $participants = $this->participants($battle);

        foreach ($participants as $participant) {
            if (! $participant->is_bot || $this->hasAction($round, $participant)) {
                continue;
            }

            $this->createAutoAction($battle, $round, $participant, 'bot');
        }
    }

    private function createMissingAutoActions(Battle $battle, BattleRound $round): void
    {
        foreach ($this->participants($battle) as $participant) {
            if ($this->hasAction($round, $participant)) {
                continue;
            }

            $this->createAutoAction($battle, $round, $participant, 'timeout');
        }
    }

    private function createAutoAction(Battle $battle, BattleRound $round, BattleParticipant $participant, string $reason): BattleAction
    {
        [$attackZone, $defenseZone] = $this->autoZones($battle, $round, $participant);

        return BattleAction::query()->create([
            'battle_id' => $battle->id,
            'battle_round_id' => $round->id,
            'user_id' => $participant->user_id,
            'creature_id' => $participant->creature_id,
            'action_type' => BattleAction::TYPE_STRIKE,
            'attack_zone' => $attackZone,
            'defense_zone' => $defenseZone,
            'is_auto' => true,
            'payload' => ['reason' => $reason],
            'submitted_at' => now(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function autoZones(Battle $battle, BattleRound $round, BattleParticipant $participant): array
    {
        $style = $participant->creature->user?->botProfile?->style ?? 'balanced';

        return match ($style) {
            'aggressive' => [
                $this->deterministicZone($battle, $round, $participant, 'attack', ['head', 'body', 'body', 'arms']),
                $this->deterministicZone($battle, $round, $participant, 'defense', ['body', 'legs', 'arms']),
            ],
            'defensive' => [
                $this->deterministicZone($battle, $round, $participant, 'attack', ['body', 'arms', 'legs']),
                $this->deterministicZone($battle, $round, $participant, 'defense', ['head', 'body', 'body', 'legs']),
            ],
            default => [
                $this->deterministicZone($battle, $round, $participant, 'attack', array_keys(BattleAction::ZONES)),
                $this->deterministicZone($battle, $round, $participant, 'defense', array_keys(BattleAction::ZONES)),
            ],
        };
    }

    /**
     * @param  list<string>  $zones
     */
    private function deterministicZone(Battle $battle, BattleRound $round, BattleParticipant $participant, string $salt, array $zones): string
    {
        $index = $this->roll($battle, $round, $participant->creature_id, $salt, 0, count($zones) - 1);

        return $zones[$index] ?? 'body';
    }

    private function resolveRound(Battle $battle, BattleRound $round): void
    {
        $participants = $this->participants($battle);
        $actions = BattleAction::query()
            ->where('battle_round_id', $round->id)
            ->with(['inventoryItem.itemInstance.item'])
            ->get()
            ->keyBy('creature_id');

        $orderedParticipants = $participants
            ->sortBy(fn (BattleParticipant $participant): int => $participant->creature_id === $round->first_actor_creature_id ? 0 : 1)
            ->values();

        foreach ($orderedParticipants as $attacker) {
            $target = $orderedParticipants->firstWhere('id', '!=', $attacker->id);
            $action = $actions->get($attacker->creature_id);

            if (! $target || ! $action || $attacker->hp_after <= 0 || $target->hp_after <= 0) {
                continue;
            }

            $itemEffect = $this->applyBattleItem($battle, $round, $attacker, $action);
            $this->performAttack($battle, $round, $attacker->refresh(), $target->refresh(), $action, $itemEffect['special']);
        }

        $round->forceFill([
            'status' => BattleRound::STATUS_RESOLVED,
            'resolved_at' => now(),
            'payload' => [
                'participants' => $this->participants($battle)
                    ->map(fn (BattleParticipant $participant): array => [
                        'creature_id' => $participant->creature_id,
                        'hp_after' => $participant->hp_after,
                    ])
                    ->values()
                    ->all(),
            ],
        ])->save();

        $battle = $battle->refresh();
        if ($this->hasEnded($battle) || $round->round_number >= self::MAX_ROUNDS) {
            $this->finish($battle);
        }
    }

    /**
     * @param  array<string, int>  $specialBonus
     */
    private function performAttack(
        Battle $battle,
        BattleRound $round,
        BattleParticipant $attacker,
        BattleParticipant $target,
        BattleAction $action,
        array $specialBonus,
    ): void {
        $attackerSpecial = $this->special($attacker, $specialBonus);
        $targetAction = BattleAction::query()
            ->where('battle_round_id', $round->id)
            ->where('creature_id', $target->creature_id)
            ->first();
        $targetSpecial = $this->special($target);

        $sameZoneGuard = $targetAction?->defense_zone === $action->attack_zone;
        $hitChance = $this->clamp(
            (int) round(66 + ($attackerSpecial['perception'] * 1.4) - ($targetSpecial['agility'] * 0.8) - ($sameZoneGuard ? 24 : 0) + $this->zoneHitModifier($action->attack_zone)),
            15,
            95,
        );
        $hitRoll = $this->roll($battle, $round, $attacker->creature_id, 'hit-'.$action->id, 1, 100);

        if ($hitRoll > $hitChance) {
            $guardText = $sameZoneGuard ? ' Р—Р°С‰РёС‚Р° Р·РѕРЅС‹ СЃСЂР°Р±РѕС‚Р°Р»Р°.' : '';
            $this->event($battle, $round->round_number, 'interactive_miss', $attacker->creature, $target->creature, [
                'attack_zone' => $action->attack_zone,
                'defense_zone' => $targetAction?->defense_zone,
                'hit_chance' => $hitChance,
                'hit_roll' => $hitRoll,
            ], "{$attacker->creature->name} Р°С‚Р°РєСѓРµС‚ РІ {$this->zoneLabel($action->attack_zone)}, РЅРѕ РїСЂРѕРјР°С…РёРІР°РµС‚СЃСЏ.{$guardText}");

            return;
        }

        $damage = $this->damage($attackerSpecial, $targetSpecial, $action->attack_zone, $sameZoneGuard);
        $critChance = $this->clamp((int) round(4 + ($attackerSpecial['luck'] * 0.8)), 3, 45);
        $critRoll = $this->roll($battle, $round, $attacker->creature_id, 'crit-'.$action->id, 1, 100);
        $critical = $critRoll <= $critChance;

        if ($critical) {
            $damage = (int) ceil($damage * 1.45);
        }

        $target->forceFill([
            'hp_after' => max(0, $target->hp_after - $damage),
        ])->save();

        $suffix = $critical ? ' РљСЂРёС‚РёС‡РµСЃРєРёР№ СѓРґР°СЂ.' : '';
        $guardText = $sameZoneGuard ? ' РЈСЂРѕРЅ СЃРЅРёР¶РµРЅ Р·Р°С‰РёС‚РѕР№ Р·РѕРЅС‹.' : '';
        $this->event($battle, $round->round_number, $critical ? 'interactive_critical_hit' : 'interactive_hit', $attacker->creature, $target->creature, [
            'attack_zone' => $action->attack_zone,
            'defense_zone' => $targetAction?->defense_zone,
            'damage' => $damage,
            'hit_chance' => $hitChance,
            'hit_roll' => $hitRoll,
            'crit_chance' => $critChance,
            'crit_roll' => $critRoll,
            'target_hp' => max(0, $target->hp_after),
        ], "{$attacker->creature->name} РїРѕРїР°РґР°РµС‚ РІ {$this->zoneLabel($action->attack_zone)}: {$damage} СѓСЂРѕРЅР°. {$target->creature->name}: {$target->hp_after} HP.{$guardText}{$suffix}");
    }

    /**
     * @return array{heal: int, max_hp: int, special: array<string, int>}
     */
    private function applyBattleItem(Battle $battle, BattleRound $round, BattleParticipant $participant, BattleAction $action): array
    {
        if (! $action->inventory_item_id) {
            return ['heal' => 0, 'max_hp' => 0, 'special' => []];
        }

        $inventoryItem = InventoryItem::query()
            ->whereKey($action->inventory_item_id)
            ->lockForUpdate()
            ->with(['inventory', 'itemInstance.item'])
            ->first();

        if (! $inventoryItem || ! $this->isConsumableAvailableForParticipant($inventoryItem, $participant)) {
            $this->event($battle, $round->round_number, 'interactive_item_failed', $participant->creature, null, [
                'inventory_item_id' => $action->inventory_item_id,
            ], "{$participant->creature->name} РЅРµ СЃРјРѕРі РїСЂРёРјРµРЅРёС‚СЊ РїСЂРµРґРјРµС‚.");

            return ['heal' => 0, 'max_hp' => 0, 'special' => []];
        }

        $itemInstance = $inventoryItem->itemInstance;
        $item = $itemInstance->item;
        $bonuses = $item->bonuses ?? [];
        $special = [];

        foreach (Creature::SPECIAL_ATTRIBUTES as $attribute) {
            $value = $this->positiveInt($bonuses[$attribute] ?? 0);

            if ($value > 0) {
                $special[$attribute] = $value;
            }
        }

        $maxHp = $this->positiveInt($bonuses['hp'] ?? 0)
            + $this->positiveInt($bonuses['max_hp'] ?? 0)
            + $this->positiveInt($bonuses['hp_max'] ?? 0);
        $heal = $this->positiveInt($bonuses['heal'] ?? 0)
            + $this->positiveInt($bonuses['hp_restore'] ?? 0);

        if ($maxHp > 0) {
            $participant->forceFill([
                'hp_before' => $participant->hp_before + $maxHp,
                'hp_after' => $participant->hp_after + $maxHp,
            ])->save();
        }

        $healed = 0;
        if ($heal > 0) {
            $beforeHp = $participant->hp_after;
            $participant->forceFill([
                'hp_after' => min($participant->hp_before, $participant->hp_after + $heal),
            ])->save();
            $healed = max(0, $participant->hp_after - $beforeHp);
        }

        $this->consumeUse($itemInstance, $inventoryItem);

        $action->forceFill([
            'payload' => [
                ...($action->payload ?? []),
                'item_name' => $item->name,
                'effect' => [
                    'heal' => $healed,
                    'max_hp' => $maxHp,
                    'special' => $special,
                ],
            ],
        ])->save();

        $parts = [];

        if ($healed > 0) {
            $parts[] = "+{$healed} HP";
        }

        if ($maxHp > 0) {
            $parts[] = "+{$maxHp} max HP";
        }

        foreach ($special as $attribute => $value) {
            $parts[] = '+'.$value.' '.(Creature::SPECIAL_LABELS[$attribute] ?? $attribute);
        }

        $effectText = $parts === [] ? 'Р±РµР· Р·Р°РјРµС‚РЅРѕРіРѕ СЌС„С„РµРєС‚Р°' : implode(', ', $parts);
        $this->event($battle, $round->round_number, 'interactive_item_used', $participant->creature, null, [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'heal' => $healed,
            'max_hp' => $maxHp,
            'special' => $special,
        ], "{$participant->creature->name} РїСЂРёРјРµРЅСЏРµС‚ {$item->name}: {$effectText}.");

        return ['heal' => $healed, 'max_hp' => $maxHp, 'special' => $special];
    }

    private function finish(Battle $battle): void
    {
        $participants = $this->participants($battle)->values();
        $left = $participants->get(0);
        $right = $participants->get(1);

        if (! $left || ! $right) {
            return;
        }

        [$winnerId, $isDraw] = $this->outcome($left, $right);

        foreach ($participants as $participant) {
            $participant->forceFill([
                'result' => $this->participantResult($participant, $winnerId, $isDraw),
                'level_after' => $participant->creature->level,
            ])->save();
        }

        $battle->forceFill([
            'winner_creature_id' => $winnerId,
            'is_draw' => $isDraw,
            'status' => Battle::STATUS_FINISHED,
            'current_actor_creature_id' => null,
            'turn_deadline_at' => null,
            'finished_at' => now(),
        ])->save();

        Creature::query()
            ->whereIn('id', $participants->pluck('creature_id')->all())
            ->update(['is_available_for_battle' => true]);

        $summary = $isDraw
            ? 'Р‘РѕР№ Р·Р°РІРµСЂС€РёР»СЃСЏ РЅРёС‡СЊРµР№.'
            : 'РџРѕР±РµРґРёС‚РµР»СЊ: '.$participants->firstWhere('creature_id', $winnerId)?->creature?->name.'.';

        $this->event($battle, self::MAX_ROUNDS + 1, 'interactive_battle_finished', null, null, [
            'winner_creature_id' => $winnerId,
            'is_draw' => $isDraw,
            'participants' => $participants
                ->map(fn (BattleParticipant $participant): array => [
                    'creature_id' => $participant->creature_id,
                    'hp_after' => $participant->hp_after,
                ])
                ->values()
                ->all(),
        ], $summary);

        $this->battleRewards->apply($battle);
    }

    /**
     * @return EloquentCollection<int, BattleParticipant>
     */
    private function participants(Battle $battle): EloquentCollection
    {
        return BattleParticipant::query()
            ->where('battle_id', $battle->id)
            ->with(['creature.user.botProfile', 'creature.skills', 'creature.equipmentRows.itemInstance.item'])
            ->orderBy('id')
            ->get();
    }

    private function participantForUser(Battle $battle, User $user): BattleParticipant
    {
        $participant = BattleParticipant::query()
            ->where('battle_id', $battle->id)
            ->where('user_id', $user->id)
            ->with(['creature.user', 'creature.skills', 'creature.equipmentRows.itemInstance.item'])
            ->first();

        abort_unless($participant, 404);

        return $participant;
    }

    private function currentRound(Battle $battle): ?BattleRound
    {
        if ($battle->current_round <= 0) {
            return null;
        }

        return BattleRound::query()
            ->where('battle_id', $battle->id)
            ->where('round_number', $battle->current_round)
            ->with('actions')
            ->first();
    }

    private function nextFirstActorId(Battle $battle, BattleRound $round): int
    {
        $participants = $this->participants($battle);
        $current = $participants->firstWhere('creature_id', $round->first_actor_creature_id);
        $next = $participants->firstWhere('id', '!=', $current?->id);

        return (int) ($next?->creature_id ?? $participants->first()?->creature_id);
    }

    private function roundHasAllActions(Battle $battle, BattleRound $round): bool
    {
        return BattleAction::query()
            ->where('battle_round_id', $round->id)
            ->count() >= $battle->participants()->count();
    }

    private function hasAction(BattleRound $round, BattleParticipant $participant): bool
    {
        return BattleAction::query()
            ->where('battle_round_id', $round->id)
            ->where('creature_id', $participant->creature_id)
            ->exists();
    }

    private function hasEnded(Battle $battle): bool
    {
        $alive = $this->participants($battle)
            ->filter(fn (BattleParticipant $participant): bool => $participant->hp_after > 0)
            ->count();

        return $alive <= 1;
    }

    /**
     * @return array{0: int|null, 1: bool}
     */
    private function outcome(BattleParticipant $left, BattleParticipant $right): array
    {
        if ($left->hp_after <= 0 && $right->hp_after <= 0) {
            return [null, true];
        }

        if ($left->hp_after <= 0) {
            return [$right->creature_id, false];
        }

        if ($right->hp_after <= 0) {
            return [$left->creature_id, false];
        }

        $leftRate = $left->hp_after / max(1, $left->hp_before);
        $rightRate = $right->hp_after / max(1, $right->hp_before);

        if (abs($leftRate - $rightRate) < 0.05) {
            return [null, true];
        }

        return $leftRate > $rightRate
            ? [$left->creature_id, false]
            : [$right->creature_id, false];
    }

    private function participantResult(BattleParticipant $participant, ?int $winnerId, bool $isDraw): string
    {
        if ($isDraw) {
            return BattleParticipant::RESULT_DRAW;
        }

        return $participant->creature_id === $winnerId
            ? BattleParticipant::RESULT_WIN
            : BattleParticipant::RESULT_LOSS;
    }

    /**
     * @param  array<string, int>  $roundBonus
     * @return array<string, int>
     */
    private function special(BattleParticipant $participant, array $roundBonus = []): array
    {
        $values = $participant->creature->effectiveSpecialValues();

        foreach ($roundBonus as $attribute => $value) {
            $values[$attribute] = ($values[$attribute] ?? 0) + $value;
        }

        return $values;
    }

    /**
     * @param  array<string, int>  $attackerSpecial
     * @param  array<string, int>  $targetSpecial
     */
    private function damage(array $attackerSpecial, array $targetSpecial, string $zone, bool $guarded): int
    {
        $base = 4 + ($attackerSpecial['strength'] * 1.35) + ($attackerSpecial['agility'] * 0.35);
        $defense = ($targetSpecial['endurance'] * 0.55) + ($guarded ? 7 : 0);
        $zoneMultiplier = match ($zone) {
            'head' => 1.35,
            'arms' => 0.9,
            'legs' => 0.95,
            default => 1.0,
        };

        return max(1, (int) round(($base * $zoneMultiplier) - $defense));
    }

    private function zoneHitModifier(string $zone): int
    {
        return match ($zone) {
            'head' => -12,
            'arms' => -4,
            'legs' => -6,
            default => 0,
        };
    }

    private function zoneLabel(string $zone): string
    {
        return mb_strtolower(BattleAction::ZONES[$zone] ?? BattleAction::ZONES['body']);
    }

    private function zone(string $zone, string $field): string
    {
        if (! array_key_exists($zone, BattleAction::ZONES)) {
            throw ValidationException::withMessages([
                $field => 'Р’С‹Р±СЂР°РЅР° РЅРµРёР·РІРµСЃС‚РЅР°СЏ Р·РѕРЅР°.',
            ]);
        }

        return $zone;
    }

    private function ensureInteractiveRunning(Battle $battle): void
    {
        if (! $battle->isInteractive() || $battle->status !== Battle::STATUS_RUNNING) {
            throw ValidationException::withMessages([
                'battle' => 'Р­С‚РѕС‚ Р±РѕР№ СѓР¶Рµ РЅРµ РїСЂРёРЅРёРјР°РµС‚ РґРµР№СЃС‚РІРёСЏ.',
            ]);
        }
    }

    private function ensureConsumableAvailable(User $user, Creature $creature, int $inventoryItemId): void
    {
        $inventoryItem = InventoryItem::query()
            ->with(['inventory', 'itemInstance.item'])
            ->find($inventoryItemId);

        if (! $inventoryItem || ! $this->isConsumableAvailableFor($inventoryItem, $user, $creature)) {
            throw ValidationException::withMessages([
                'inventory_item_id' => 'Р­С‚РѕС‚ РїСЂРµРґРјРµС‚ РЅРµР»СЊР·СЏ РїСЂРёРјРµРЅРёС‚СЊ РІ С‚РµРєСѓС‰РµРј Р±РѕСЋ.',
            ]);
        }
    }

    private function isConsumableAvailableForParticipant(InventoryItem $inventoryItem, BattleParticipant $participant): bool
    {
        return $this->isConsumableAvailableFor($inventoryItem, $participant->creature->user, $participant->creature);
    }

    private function isConsumableAvailableFor(InventoryItem $inventoryItem, User $user, Creature $creature): bool
    {
        $inventory = $inventoryItem->inventory;
        $itemInstance = $inventoryItem->itemInstance;
        $item = $itemInstance?->item;

        if (! $inventory || ! $itemInstance || ! $item) {
            return false;
        }

        if ((int) $inventory->owner_user_id !== (int) $user->id || (int) $itemInstance->owner_user_id !== (int) $user->id) {
            return false;
        }

        if (! in_array($inventory->inventory_type, [Inventory::TYPE_PLAYER, Inventory::TYPE_CREATURE], true)) {
            return false;
        }

        if ($inventory->inventory_type === Inventory::TYPE_CREATURE && (int) $inventory->creature_id !== (int) $creature->id) {
            return false;
        }

        return $itemInstance->state === 'stored'
            && $itemInstance->remainingUses() > 0
            && $item->isConsumable()
            && $item->canBeUsedBy($creature);
    }

    private function consumeUse(ItemInstance $itemInstance, InventoryItem $inventoryItem): void
    {
        $usesRemaining = max(0, $itemInstance->remainingUses() - 1);

        if ($usesRemaining === 0) {
            $inventoryItem->delete();
            $itemInstance->forceFill([
                'bound_creature_id' => null,
                'durability' => 0,
                'state' => 'used',
            ])->save();

            return;
        }

        $itemInstance->forceFill([
            'durability' => $usesRemaining,
        ])->save();
    }

    private function battleMaxHp(Creature $creature): int
    {
        return max(1, $creature->effectiveMaxHp() + ($creature->level * 5));
    }

    private function roll(Battle $battle, BattleRound $round, int $creatureId, string $salt, int $min, int $max): int
    {
        $hash = crc32(implode('|', [$battle->seed, $round->round_number, $creatureId, $salt]));

        return $min + ((int) $hash % (($max - $min) + 1));
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return min($max, max($min, $value));
    }

    private function positiveInt(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function event(
        Battle $battle,
        int $round,
        string $eventType,
        ?Creature $actor,
        ?Creature $target,
        array $payload,
        string $text,
    ): void {
        $battle->events()->create([
            'round' => $round,
            'event_type' => $eventType,
            'actor_creature_id' => $actor?->id,
            'target_creature_id' => $target?->id,
            'payload' => $payload,
            'text_log' => $text,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStateSnapshot(Battle $battle): array
    {
        $battle = Battle::query()->whereKey($battle->id)->firstOrFail();
        $participants = BattleParticipant::query()
            ->where('battle_id', $battle->id)
            ->with(['creature.user'])
            ->orderBy('id')
            ->get();
        $activeRound = $battle->current_round > 0
            ? BattleRound::query()
                ->where('battle_id', $battle->id)
                ->where('round_number', $battle->current_round)
                ->with(['actions:id,battle_round_id,creature_id', 'firstActor:id,name'])
                ->first()
            : null;
        $latestEventId = BattleEvent::query()
            ->where('battle_id', $battle->id)
            ->latest('id')
            ->value('id');
        $eventsCount = BattleEvent::query()
            ->where('battle_id', $battle->id)
            ->count();

        return [
            'battle_id' => $battle->id,
            'mode' => $battle->mode,
            'status' => $battle->status,
            'current_round' => $battle->current_round,
            'turn_deadline_at' => $battle->turn_deadline_at?->toISOString(),
            'latest_event_id' => $latestEventId ? (int) $latestEventId : null,
            'events_count' => $eventsCount,
            'active_round' => $activeRound ? [
                'id' => $activeRound->id,
                'round_number' => $activeRound->round_number,
                'status' => $activeRound->status,
                'deadline_at' => $activeRound->deadline_at?->toISOString(),
                'actions_count' => $activeRound->actions->count(),
                'action_creature_ids' => $activeRound->actions->pluck('creature_id')->map(fn ($id) => (int) $id)->values()->all(),
                'first_actor_name' => $activeRound->firstActor?->name,
            ] : null,
            'participants' => $participants
                ->map(fn (BattleParticipant $participant): array => [
                    'user_id' => $participant->user_id,
                    'creature_id' => $participant->creature_id,
                    'creature_name' => $participant->creature?->name,
                    'owner_name' => $participant->creature?->user?->name,
                    'hp_after' => $participant->hp_after,
                    'hp_before' => $participant->hp_before,
                    'result' => $participant->result,
                    'level_before' => $participant->level_before,
                    'level_after' => $participant->level_after,
                    'reward_xp' => $participant->reward_xp,
                    'reward_tokens' => $participant->reward_tokens,
                    'reward_development_points' => $participant->reward_development_points,
                    'reward_multiplier' => $participant->reward_multiplier,
                    'power_score_before' => $participant->power_score_before,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function renderBattleFragments(Battle $battle, User $viewer): array
    {
        $viewData = $this->viewData($battle->refresh(), $viewer);

        return [
            'action_panel_html' => view('game.battles.partials.action-panel', $viewData)->render(),
            'participants_html' => view('game.battles.partials.participants-grid', $viewData)->render(),
            'events_html' => view('game.battles.partials.events-log', $viewData)->render(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cachedState(Battle $battle): array
    {
        return Cache::remember(
            $this->stateCacheKey($battle->id),
            now()->addSeconds(self::STATE_CACHE_TTL_SECONDS),
            fn (): array => $this->buildStateSnapshot($battle),
        );
    }

    private function stateCacheKey(int $battleId): string
    {
        return 'interactive-battle-state:'.$battleId;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function battleMarker(array $state): string
    {
        $activeRound = $state['active_round'] ?? null;

        return implode('|', [
            $state['status'] ?? '',
            $state['current_round'] ?? '',
            $state['latest_event_id'] ?? '',
            $activeRound['actions_count'] ?? '',
            ($activeRound['own_action_submitted'] ?? false) ? 1 : 0,
        ]);
    }

    private function dispatchRoundResolution(Battle $battle, BattleRound $round, bool $immediate = false): void
    {
        $battleId = $battle->id;
        $delaySeconds = 0;

        if (! $immediate && $round->deadline_at) {
            $delaySeconds = max(0, now()->diffInSeconds($round->deadline_at, false));
        }

        DB::afterCommit(function () use ($battleId, $delaySeconds, $immediate): void {
            if ($immediate && $this->dispatchesSynchronously()) {
                ResolveInteractiveBattleRound::dispatchSync($battleId);

                return;
            }

            if (! $immediate && $this->dispatchesSynchronously()) {
                return;
            }

            if ($delaySeconds > 0) {
                ResolveInteractiveBattleRound::dispatch($battleId)->delay($delaySeconds);

                return;
            }

            ResolveInteractiveBattleRound::dispatch($battleId);
        });
    }

    private function dispatchesSynchronously(): bool
    {
        return config('queue.default') === 'sync';
    }
}
