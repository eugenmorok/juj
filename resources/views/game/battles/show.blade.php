@extends('layouts.app', ['title' => $battle->status === \App\Models\Battle::STATUS_FINISHED ? 'Результат боя' : 'Бой на арене'])

@section('content')
    @php
        $latestEvent = $battle->events->last();
        $battleMarker = implode('|', [
            $battle->status,
            $battle->current_round,
            $latestEvent?->id ?? '',
            $activeRound?->actions?->count() ?? '',
            $ownAction ? 1 : 0,
        ]);
    @endphp

    <div
        class="space-y-8"
        @if ($battle->isInteractive())
            data-battle-poll
            data-battle-id="{{ $battle->id }}"
            data-battle-channel="battle.{{ $battle->id }}"
            data-battle-state-url="{{ route('arena.battles.state', $battle) }}"
            data-battle-marker="{{ $battleMarker }}"
            data-battle-deadline="{{ $activeRound?->deadline_at?->toISOString() }}"
        @endif
    >
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Арена</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">
                    {{ $isInteractiveRunning ? 'Пошаговый бой' : 'Результат боя' }} #{{ $battle->id }}
                </h1>
                <p class="mt-1 text-sm text-zinc-400">
                    Seed {{ $battle->seed }} / {{ $battle->started_at?->format('d.m.Y H:i') }}
                    @if ($battle->isInteractive())
                        / пошаговый режим
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($isInteractiveRunning)
                    <a href="{{ route('arena.battles.show', $battle) }}" class="rounded-md border border-emerald-500/50 px-4 py-2 text-sm text-emerald-100 hover:bg-emerald-500/10">
                        Обновить
                    </a>
                @endif
                <a href="{{ route('arena.battles.replay', $battle) }}" class="rounded-md border border-sky-500/50 px-4 py-2 text-sm text-sky-100 hover:bg-sky-500/10">
                    Replay
                </a>
                <a href="{{ route('arena') }}" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-900">
                    К арене
                </a>
            </div>
        </div>

        @include('partials.form-errors')

        <div class="hidden rounded-md border px-4 py-3 text-sm" data-battle-live-status></div>

        <div data-battle-action-panel>
            @include('game.battles.partials.action-panel', [
                'battle' => $battle,
                'activeRound' => $activeRound,
                'ownParticipant' => $ownParticipant,
                'ownAction' => $ownAction,
                'zones' => $zones,
                'availableConsumables' => $availableConsumables,
                'isInteractiveRunning' => $isInteractiveRunning,
            ])
        </div>

        <div data-battle-participants>
            @include('game.battles.partials.participants-grid', ['battle' => $battle])
        </div>

        <div data-battle-events>
            @include('game.battles.partials.events-log', ['battle' => $battle])
        </div>
    </div>
@endsection
