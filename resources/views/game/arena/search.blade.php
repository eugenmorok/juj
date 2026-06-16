@extends('layouts.app', ['title' => 'Подбор соперников'])

@section('content')
    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Арена</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Подбор соперников</h1>
                <p class="mt-1 text-sm text-zinc-400">
                    {{ $creature->name }} / ур. {{ $creature->level }} / PS {{ $session->power_score }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('arena.search.store') }}">
                    @csrf
                    <input type="hidden" name="creature_id" value="{{ $creature->id }}">
                    <button type="submit" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-900">
                        Обновить
                    </button>
                </form>
                <a href="{{ route('arena') }}" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-900">
                    К арене
                </a>
            </div>
        </div>

        @include('partials.form-errors')

        @if ($candidates->isEmpty())
            <section class="rounded-md border border-dashed border-zinc-700 bg-zinc-900 p-6 text-center text-sm text-zinc-400">
                Подходящих соперников пока нет. Попробуй обновить подбор.
            </section>
        @else
            <section class="grid gap-4 lg:grid-cols-2">
                @foreach ($candidates as $candidate)
                    @php
                        $candidateCreature = $candidate['creature'];
                        $candidateUser = $candidate['user'];
                    @endphp
                    <article class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-xl font-semibold text-white">{{ $candidateCreature->name }}</h2>
                                    <span class="rounded-md border px-2 py-0.5 text-xs {{ $candidate['is_bot'] ? 'border-amber-500/50 text-amber-100' : 'border-sky-500/50 text-sky-100' }}">
                                        {{ $candidate['is_bot'] ? 'Бот' : 'Игрок' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-zinc-400">
                                    {{ $candidateUser->name }} / {{ $candidateCreature->type->name }} / {{ $candidateCreature->species->name }}
                                </p>
                            </div>
                            <span class="rounded-md border border-emerald-500/40 px-3 py-1 text-sm text-emerald-100">
                                PS {{ $candidate['power_score'] }}
                            </span>
                        </div>

                        <dl class="mt-5 grid gap-3 sm:grid-cols-4">
                            <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                                <dt class="text-xs text-zinc-500">Уровень</dt>
                                <dd class="mt-1 text-zinc-200">{{ $candidateCreature->level }}</dd>
                            </div>
                            <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                                <dt class="text-xs text-zinc-500">Разница PS</dt>
                                <dd class="mt-1 text-zinc-200">{{ $candidate['power_delta'] }}</dd>
                            </div>
                            <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                                <dt class="text-xs text-zinc-500">W</dt>
                                <dd class="mt-1 text-zinc-200">{{ $candidateCreature->wins }}</dd>
                            </div>
                            <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                                <dt class="text-xs text-zinc-500">L</dt>
                                <dd class="mt-1 text-zinc-200">{{ $candidateCreature->losses }}</dd>
                            </div>
                        </dl>

                        <form method="POST" action="{{ route('arena.challenges.store') }}" class="mt-5">
                            @csrf
                            <input type="hidden" name="challenger_creature_id" value="{{ $creature->id }}">
                            <input type="hidden" name="defender_creature_id" value="{{ $candidateCreature->id }}">
                            <button type="submit" class="rounded-md bg-emerald-500 px-4 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400">
                                Бросить вызов
                            </button>
                        </form>
                    </article>
                @endforeach
            </section>
        @endif
    </div>
@endsection
