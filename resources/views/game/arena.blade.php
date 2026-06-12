@extends('layouts.app', ['title' => 'Арена'])

@section('content')
    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Матчи</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Арена</h1>
                <p class="mt-1 text-sm text-zinc-400">Выбери сущность, система подберет ближайшего соперника и сразу рассчитает бой.</p>
            </div>
            <a href="{{ route('entities.index') }}" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-900">
                Мои сущности
            </a>
        </div>

        @include('partials.form-errors')

        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-white">Быстрый рейтинговый бой</h2>
                    <p class="mt-1 text-sm text-zinc-400">Подбор учитывает уровень и power score. Если реальных соперников мало, система подмешает активных ботов.</p>
                </div>
            </div>

            @if ($creatures->isEmpty())
                <div class="mt-5 rounded-md border border-dashed border-zinc-700 bg-zinc-950 p-6 text-center text-sm text-zinc-400">
                    Сначала нужно создать сущность.
                </div>
            @else
                <form method="POST" action="{{ route('arena.battles.start') }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_auto]">
                    @csrf
                    <select name="creature_id" class="rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                        @foreach ($creatures as $creature)
                            <option value="{{ $creature->id }}" @disabled(! $creature->is_available_for_battle)>
                                {{ $creature->name }}
                                / ур. {{ $creature->level }}
                                / PS {{ $powerScores[$creature->id] ?? 0 }}
                                / {{ $creature->type->name }} / {{ $creature->species->name }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md bg-emerald-500 px-5 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400">
                        Найти бой
                    </button>
                </form>

                <div class="mt-5 grid gap-3 lg:grid-cols-3">
                    @foreach ($creatures as $creature)
                        <article class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-white">{{ $creature->name }}</h3>
                                    <p class="mt-1 text-xs text-zinc-500">{{ $creature->type->name }} / {{ $creature->species->name }}</p>
                                </div>
                                <span class="rounded-md border border-emerald-500/40 px-2 py-0.5 text-xs text-emerald-200">
                                    PS {{ $powerScores[$creature->id] ?? 0 }}
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                                <div class="rounded-md border border-zinc-800 py-2">
                                    <div class="text-zinc-500">W</div>
                                    <div class="mt-1 text-zinc-200">{{ $creature->wins }}</div>
                                </div>
                                <div class="rounded-md border border-zinc-800 py-2">
                                    <div class="text-zinc-500">D</div>
                                    <div class="mt-1 text-zinc-200">{{ $creature->draws }}</div>
                                </div>
                                <div class="rounded-md border border-zinc-800 py-2">
                                    <div class="text-zinc-500">L</div>
                                    <div class="mt-1 text-zinc-200">{{ $creature->losses }}</div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-white">История боев</h2>
                <span class="text-sm text-zinc-400">{{ $recentBattles->count() }} последних</span>
            </div>

            @if ($recentBattles->isEmpty())
                <div class="rounded-md border border-zinc-800 bg-zinc-900 p-6 text-sm text-zinc-400">
                    Боев пока нет.
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($recentBattles as $battle)
                        @php
                            $ownParticipant = $battle->participants->firstWhere('user_id', auth()->id());
                            $opponent = $battle->participants->firstWhere('user_id', '!=', auth()->id());
                            $resultLabel = [
                                'win' => 'Победа',
                                'loss' => 'Поражение',
                                'draw' => 'Ничья',
                            ][$ownParticipant?->result] ?? 'Итог';
                            $resultTone = match ($ownParticipant?->result) {
                                'win' => 'border-emerald-500/50 text-emerald-100',
                                'loss' => 'border-rose-500/50 text-rose-100',
                                'draw' => 'border-amber-500/50 text-amber-100',
                                default => 'border-zinc-700 text-zinc-200',
                            };
                        @endphp
                        <a href="{{ route('arena.battles.show', $battle) }}" class="block rounded-md border border-zinc-800 bg-zinc-900 p-4 hover:bg-zinc-900/70">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-white">
                                        {{ $ownParticipant?->creature?->name }} против {{ $opponent?->creature?->name }}
                                    </h3>
                                    <p class="mt-1 text-sm text-zinc-400">
                                        {{ $battle->started_at?->format('d.m.Y H:i') }} / seed {{ $battle->seed }}
                                    </p>
                                </div>
                                <span class="rounded-md border px-3 py-1 text-sm {{ $resultTone }}">
                                    {{ $resultLabel }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
