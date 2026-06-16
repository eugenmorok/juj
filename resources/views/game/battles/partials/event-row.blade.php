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
        'interactive_item_used' => 'Предмет',
        'interactive_item_failed' => 'Сбой',
        'interactive_battle_finished' => 'Итог',
    ][$event->event_type] ?? $event->event_type;
    $eventTone = match ($event->event_type) {
        'critical_hit', 'interactive_critical_hit' => 'border-amber-400/60 text-amber-100',
        'miss', 'interactive_miss' => 'border-zinc-700 text-zinc-300',
        'self_repair', 'interactive_item_used' => 'border-sky-400/60 text-sky-100',
        'battle_finished', 'interactive_battle_finished', 'rewards_applied' => 'border-emerald-500/50 text-emerald-100',
        'interactive_item_failed' => 'border-rose-500/50 text-rose-100',
        default => 'border-zinc-700 text-zinc-200',
    };
    $payload = $event->payload ?? [];
@endphp

<article class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-3 text-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-md border px-2 py-0.5 text-xs font-medium {{ $eventTone }}">
                    R{{ $event->round }} / {{ $eventLabel }}
                </span>
                @if ($event->actor)
                    <span class="text-xs text-zinc-400">{{ $event->actor->name }}</span>
                @endif
                @if ($event->target)
                    <span class="text-xs text-zinc-500">-> {{ $event->target->name }}</span>
                @endif
            </div>
            <p class="mt-2 text-zinc-300">{{ $event->text_log }}</p>
        </div>

        @if ($payload !== [])
            <dl class="grid min-w-44 grid-cols-2 gap-2 text-xs">
                @foreach ([
                    'damage' => 'Урон',
                    'heal' => 'Лечение',
                    'hit_chance' => 'Шанс',
                    'hit_roll' => 'Бросок',
                    'roll' => 'Бросок',
                    'target_hp' => 'HP цели',
                    'attack_zone' => 'Зона',
                    'defense_zone' => 'Блок',
                ] as $key => $label)
                    @if (array_key_exists($key, $payload))
                        <div class="rounded-md border border-zinc-800 px-2 py-1">
                            <dt class="text-zinc-500">{{ $label }}</dt>
                            <dd class="font-semibold text-zinc-100">{{ $payload[$key] }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        @endif
    </div>
</article>
