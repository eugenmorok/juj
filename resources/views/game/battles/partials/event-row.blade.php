@php
    $eventLabel = [
        'battle_started' => 'Старт',
        'round_started' => 'Раунд',
        'hit' => 'Удар',
        'critical_hit' => 'Крит',
        'miss' => 'Промах',
        'self_repair' => 'Ремонт',
        'battle_finished' => 'Итог',
        'rewards_applied' => 'Награды',
        'interactive_battle_started' => 'Старт',
        'round_collecting' => 'Выбор',
        'interactive_hit' => 'Удар',
        'interactive_critical_hit' => 'Крит',
        'interactive_miss' => 'Промах',
        'interactive_item_used' => 'Эффект',
        'interactive_item_failed' => 'Сбой',
        'interactive_battle_finished' => 'Итог',
    ][$event->event_type] ?? $event->event_type;
    $payload = $event->payload ?? [];
    $ownCreatureId = isset($ownCreatureId) ? (int) $ownCreatureId : null;
    $actorIsOwn = $ownCreatureId && (int) $event->actor_creature_id === $ownCreatureId;
    $targetIsOwn = $ownCreatureId && (int) $event->target_creature_id === $ownCreatureId;
    $isHit = in_array($event->event_type, ['hit', 'critical_hit', 'interactive_hit', 'interactive_critical_hit'], true);
    $isPositive = in_array($event->event_type, ['self_repair', 'interactive_item_used'], true);
    $isBlocked = isset($payload['attack_zone'], $payload['defense_zone'])
        && $payload['attack_zone'] === $payload['defense_zone'];
    $damagePerspective = match (true) {
        $actorIsOwn => 'positive',
        $targetIsOwn => 'negative',
        default => 'neutral',
    };

    $perspective = match (true) {
        $isBlocked && $targetIsOwn => 'positive',
        $isBlocked && $actorIsOwn => 'negative',
        $isHit && $actorIsOwn => 'positive',
        $isHit && $targetIsOwn => 'negative',
        $isPositive && $actorIsOwn => 'positive',
        $isPositive && ! $actorIsOwn && $ownCreatureId => 'negative',
        default => 'neutral',
    };
    $rowTone = match ($perspective) {
        'positive' => 'battle-event-row--positive',
        'negative' => 'battle-event-row--negative',
        default => '',
    };
    $eventTone = match (true) {
        $perspective === 'positive' => 'battle-event-badge--positive',
        $perspective === 'negative' => 'battle-event-badge--negative',
        in_array($event->event_type, ['critical_hit', 'interactive_critical_hit'], true) => 'battle-event-badge--critical',
        default => '',
    };
    $zoneLabels = [
        'head' => 'голова',
        'body' => 'тело',
        'arms' => 'руки',
        'legs' => 'ноги',
    ];
@endphp

<article class="battle-event-row {{ $rowTone }}">
    <div class="battle-event-row__heading">
        <div class="battle-event-row__meta">
            <span class="battle-event-badge {{ $eventTone }}">R{{ $event->round }} · {{ $eventLabel }}</span>
            @if ($event->actor)
                <span class="battle-event-row__actors">
                    {{ $event->actor->name }}@if ($event->target)<b>→</b>{{ $event->target->name }}@endif
                </span>
            @endif
        </div>

        <div class="battle-event-row__signals">
            @if ($isHit && array_key_exists('damage', $payload))
                <strong class="battle-event-damage battle-event-damage--{{ $damagePerspective }}">
                    −{{ $payload['damage'] }} HP
                </strong>
            @endif
            @if ($isPositive && array_key_exists('heal', $payload) && (int) $payload['heal'] > 0)
                <strong class="battle-event-effect battle-event-effect--{{ $perspective }}">
                    +{{ $payload['heal'] }} HP
                </strong>
            @endif
            @if ($isBlocked)
                <strong class="battle-event-effect battle-event-effect--{{ $perspective }}">Блок</strong>
            @endif
        </div>
    </div>

    <p class="battle-event-row__text">{{ $event->text_log }}</p>

    @if (array_key_exists('attack_zone', $payload) || array_key_exists('hit_chance', $payload) || ! empty($payload['special']))
        <div class="battle-event-row__details">
            @if (array_key_exists('attack_zone', $payload))
                <span>Зона: {{ $zoneLabels[$payload['attack_zone']] ?? $payload['attack_zone'] }}</span>
            @endif
            @if (array_key_exists('hit_chance', $payload))
                <span>Шанс: {{ $payload['hit_chance'] }}%</span>
            @endif
            @if (! empty($payload['special']))
                @foreach ($payload['special'] as $attribute => $value)
                    <span class="battle-event-effect--{{ $perspective }}">
                        +{{ $value }} {{ \App\Models\Creature::SPECIAL_LABELS[$attribute] ?? strtoupper(substr($attribute, 0, 1)) }}
                    </span>
                @endforeach
            @endif
        </div>
    @endif
</article>
