@extends('layouts.app', ['title' => 'Результат боя'])

@section('content')
    @php
        $resultLabels = [
            'win' => 'Победа',
            'loss' => 'Поражение',
            'draw' => 'Ничья',
        ];
    @endphp

    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Арена</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Результат боя #{{ $battle->id }}</h1>
                <p class="mt-1 text-sm text-zinc-400">
                    Seed {{ $battle->seed }} / {{ $battle->started_at?->format('d.m.Y H:i') }}
                </p>
            </div>
            <a href="{{ route('arena') }}" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-900">
                К арене
            </a>
        </div>

        <section class="grid gap-4 lg:grid-cols-2">
            @foreach ($battle->participants as $participant)
                <article class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold text-white">{{ $participant->creature->name }}</h2>
                            <p class="mt-1 text-sm text-zinc-400">
                                {{ $participant->creature->user->name }} / ур. {{ $participant->level_before }} -> {{ $participant->level_after }}
                            </p>
                        </div>
                        <span class="rounded-md border border-emerald-500/40 px-3 py-1 text-sm text-emerald-100">
                            {{ $resultLabels[$participant->result] ?? $participant->result }}
                        </span>
                    </div>

                    <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                            <dt class="text-xs text-zinc-500">Power score</dt>
                            <dd class="mt-1 text-zinc-200">{{ $participant->power_score_before }}</dd>
                        </div>
                        <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                            <dt class="text-xs text-zinc-500">HP</dt>
                            <dd class="mt-1 text-zinc-200">{{ $participant->hp_after }}/{{ $participant->hp_before }}</dd>
                        </div>
                        <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                            <dt class="text-xs text-zinc-500">XP</dt>
                            <dd class="mt-1 text-zinc-200">+{{ $participant->reward_xp }}</dd>
                        </div>
                        <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                            <dt class="text-xs text-zinc-500">Токены</dt>
                            <dd class="mt-1 text-zinc-200">+{{ $participant->reward_tokens }}</dd>
                        </div>
                    </dl>

                    <p class="mt-4 text-sm text-zinc-400">
                        Очки развития: +{{ $participant->reward_development_points }}.
                        Множитель наград: x{{ $participant->reward_multiplier }}.
                    </p>
                    <div class="mt-4">
                        @include('partials.progress-bar', [
                            'value' => $participant->hp_after,
                            'max' => max(1, $participant->hp_before),
                            'label' => 'HP после боя',
                        ])
                    </div>
                </article>
            @endforeach
        </section>

        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            <h2 class="text-xl font-semibold text-white">Лог боя</h2>
            <div class="mt-4 space-y-2">
                @foreach ($battle->events as $event)
                    @include('game.battles.partials.event-row', ['event' => $event])
                @endforeach
            </div>
        </section>
    </div>
@endsection
