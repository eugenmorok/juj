@extends('layouts.app', ['title' => 'Вызов на бой'])

@section('content')
    @php
        $statusLabels = [
            \App\Models\ArenaChallenge::STATUS_PENDING => 'Ожидает ответа',
            \App\Models\ArenaChallenge::STATUS_ACCEPTED => 'Принят',
            \App\Models\ArenaChallenge::STATUS_DECLINED => 'Отклонен',
            \App\Models\ArenaChallenge::STATUS_EXPIRED => 'Истек',
            \App\Models\ArenaChallenge::STATUS_CANCELLED => 'Отменен',
            \App\Models\ArenaChallenge::STATUS_BATTLE_STARTED => 'Бой запущен',
        ];
    @endphp

    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Арена</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Вызов #{{ $challenge->id }}</h1>
                <p class="mt-1 text-sm text-zinc-400">
                    {{ $statusLabels[$challenge->status] ?? $challenge->status }}
                    @if ($challenge->isPending())
                        / ответ до {{ $challenge->expires_at?->format('H:i:s') }}
                    @endif
                </p>
            </div>
            <a href="{{ route('arena') }}" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-900">
                К арене
            </a>
        </div>

        @include('partials.form-errors')

        <section class="grid gap-4 lg:grid-cols-2">
            <article class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <p class="text-sm uppercase text-zinc-500">Бросил вызов</p>
                <h2 class="mt-2 text-xl font-semibold text-white">{{ $challenge->challengerCreature->name }}</h2>
                <p class="mt-1 text-sm text-zinc-400">{{ $challenge->challengerCreature->user->name }}</p>
            </article>

            <article class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <p class="text-sm uppercase text-zinc-500">Получил вызов</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-semibold text-white">{{ $challenge->defenderCreature->name }}</h2>
                    @if ($challenge->defender_is_bot)
                        <span class="rounded-md border border-amber-500/50 px-2 py-0.5 text-xs text-amber-100">Бот</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-zinc-400">{{ $challenge->defenderCreature->user->name }}</p>
            </article>
        </section>

        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            @if ($challenge->status === \App\Models\ArenaChallenge::STATUS_PENDING && $isDefender)
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('arena.challenges.accept', $challenge) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-emerald-500 px-4 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400">
                            Принять
                        </button>
                    </form>
                    <form method="POST" action="{{ route('arena.challenges.decline', $challenge) }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-rose-500/50 px-4 py-2 text-sm text-rose-100 hover:bg-rose-500/10">
                            Отклонить
                        </button>
                    </form>
                </div>
            @elseif ($challenge->status === \App\Models\ArenaChallenge::STATUS_PENDING && $isChallenger)
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-zinc-400">Ожидаем ответ второго игрока.</p>
                    <form method="POST" action="{{ route('arena.challenges.cancel', $challenge) }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-950">
                            Отменить
                        </button>
                    </form>
                </div>
            @elseif ($challenge->battle)
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-zinc-400">Бой создан и доступен для продолжения.</p>
                    <a href="{{ route('arena.battles.show', $challenge->battle) }}" class="rounded-md bg-emerald-500 px-4 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400">
                        Открыть бой
                    </a>
                </div>
            @else
                <p class="text-sm text-zinc-400">Вызов завершен без боя.</p>
            @endif
        </section>
    </div>
@endsection
