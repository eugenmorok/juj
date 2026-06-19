@extends('layouts.app', ['title' => 'Replay боя'])

@section('content')
    @php
        $zones = \App\Models\BattleAction::ZONES;
        $resultLabels = [
            'win' => 'Победа',
            'loss' => 'Поражение',
            'draw' => 'Ничья',
        ];
    @endphp

    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Replay</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Бой #{{ $battle->id }}</h1>
                <p class="mt-1 text-sm text-zinc-400">
                    {{ $battle->isInteractive() ? 'Пошаговый бой' : 'Мгновенный бой' }}
                    / статус: {{ $battle->status }}
                    / seed {{ $battle->seed }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('arena.battles.show', $battle) }}" class="rounded-md border border-emerald-500/50 px-4 py-2 text-sm text-emerald-100 hover:bg-emerald-500/10">
                    К бою
                </a>
                <a href="{{ route('arena') }}" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-900">
                    К арене
                </a>
            </div>
        </div>

        <section class="grid gap-4 lg:grid-cols-2">
            @foreach ($battle->participants as $participant)
                <article class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold text-white">{{ $participant->creature->name }}</h2>
                            <p class="mt-1 text-sm text-zinc-400">{{ $participant->creature->user->name }}</p>
                        </div>
                        <span class="rounded-md border border-zinc-700 px-3 py-1 text-sm text-zinc-200">
                            {{ $participant->result ? ($resultLabels[$participant->result] ?? $participant->result) : 'В бою' }}
                        </span>
                    </div>
                    <div class="mt-4">
                        @include('partials.progress-bar', [
                            'value' => $participant->hp_after,
                            'max' => max(1, $participant->hp_before),
                            'label' => 'HP',
                        ])
                    </div>
                </article>
            @endforeach
        </section>

        @if ($battle->rounds->isNotEmpty())
            <section class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold text-white">Таймлайн раундов</h2>
                    <span class="text-sm text-zinc-400">{{ $battle->rounds->count() }} шагов</span>
                </div>

                @foreach ($battle->rounds as $round)
                    <article class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-white">Шаг {{ $round->round_number }}</h3>
                                <p class="mt-1 text-sm text-zinc-400">
                                    Первый темп: {{ $round->firstActor?->name ?? 'не определен' }}
                                    / статус: {{ $round->status }}
                                </p>
                            </div>
                            <span class="rounded-md border border-zinc-700 px-3 py-1 text-sm text-zinc-200">
                                {{ $round->deadline_at?->format('H:i:s') }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 lg:grid-cols-2">
                            @foreach ($battle->participants as $participant)
                                @php
                                    $action = $round->actions->firstWhere('creature_id', $participant->creature_id);
                                    $item = $action?->inventoryItem?->itemInstance?->item;
                                @endphp
                                <div class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h4 class="font-semibold text-white">{{ $participant->creature->name }}</h4>
                                            <p class="mt-1 text-xs text-zinc-500">{{ $action?->is_auto ? 'Автотактика' : 'Игрок' }}</p>
                                        </div>
                                        <span class="rounded-md border border-zinc-700 px-2 py-0.5 text-xs text-zinc-200">
                                            {{ $action?->action_type ?? 'нет действия' }}
                                        </span>
                                    </div>

                                    @if ($action)
                                        <dl class="mt-4 grid gap-2 sm:grid-cols-3">
                                            <div class="rounded-md border border-zinc-800 px-3 py-2">
                                                <dt class="text-xs text-zinc-500">Атака</dt>
                                                <dd class="mt-1 text-sm text-zinc-100">{{ $zones[$action->attack_zone] ?? $action->attack_zone }}</dd>
                                            </div>
                                            <div class="rounded-md border border-zinc-800 px-3 py-2">
                                                <dt class="text-xs text-zinc-500">Защита</dt>
                                                <dd class="mt-1 text-sm text-zinc-100">{{ $zones[$action->defense_zone] ?? $action->defense_zone }}</dd>
                                            </div>
                                            <div class="rounded-md border border-zinc-800 px-3 py-2">
                                                <dt class="text-xs text-zinc-500">Предмет</dt>
                                                <dd class="mt-1 text-sm text-zinc-100">{{ $item?->name ?? '-' }}</dd>
                                                @if ($item)
                                                    @if ($item->description)
                                                        <p class="mt-1 text-xs text-zinc-400">{{ $item->description }}</p>
                                                    @endif
                                                    <x-item-effects :item="$item" :show-duration="false" />
                                                @endif
                                            </div>
                                        </dl>
                                    @else
                                        <p class="mt-4 text-sm text-zinc-400">Действие еще не выбрано.</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if (($eventsByRound[$round->round_number] ?? collect())->isNotEmpty())
                            <div class="mt-4 space-y-2">
                                @foreach ($eventsByRound[$round->round_number] as $event)
                                    @include('game.battles.partials.event-row', ['event' => $event])
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif

        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            <h2 class="text-xl font-semibold text-white">Полный лог</h2>
            <div class="mt-4 space-y-2">
                @foreach ($battle->events as $event)
                    @include('game.battles.partials.event-row', ['event' => $event])
                @endforeach
            </div>
        </section>
    </div>
@endsection
