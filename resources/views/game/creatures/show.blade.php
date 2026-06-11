@extends('layouts.app', ['title' => $creature->name])

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Карточка сущности</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">{{ $creature->name }}</h1>
                <p class="mt-1 text-sm text-zinc-400">{{ $creature->type->name }} / {{ $creature->species->name }}</p>
            </div>
            <a href="{{ route('entities.index') }}" class="rounded-md border border-zinc-700 px-4 py-2 text-zinc-200 hover:bg-zinc-900">
                К списку
            </a>
        </div>

        @include('partials.form-errors')

        <section class="grid gap-4 md:grid-cols-4">
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="text-sm text-zinc-400">Уровень</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $creature->level }}</div>
            </div>
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="text-sm text-zinc-400">Опыт</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $creature->xp }}</div>
            </div>
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="text-sm text-zinc-400">Очки развития</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $creature->development_points }}</div>
            </div>
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="text-sm text-zinc-400">HP</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $creature->current_hp }}/{{ $creature->max_hp }}</div>
            </div>
        </section>

        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            <h2 class="font-semibold text-white">SPECIAL</h2>
            <dl class="mt-4 grid grid-cols-7 gap-2 text-center text-xs">
                @foreach (\App\Models\Creature::SPECIAL_LABELS as $attribute => $label)
                    <div class="rounded-md border border-zinc-800 bg-zinc-950 px-2 py-2">
                        <dt class="text-zinc-500">{{ $label }}</dt>
                        <dd class="mt-1 text-lg font-semibold text-white">{{ $creature->{$attribute} }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold text-white">Навыки сущности</h2>
                    <span class="rounded-md border border-zinc-800 px-3 py-1 text-sm text-zinc-300">
                        {{ $creature->skills->count() }}/{{ $creature->maxSkills() }}
                    </span>
                </div>

                @if ($creature->skills->isEmpty())
                    <p class="mt-4 text-sm text-zinc-400">Навыки пока не куплены.</p>
                @else
                    <div class="mt-4 space-y-3">
                        @foreach ($creature->skills as $skill)
                            <article class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <h3 class="font-semibold text-white">{{ $skill->name }}</h3>
                                    <span class="rounded-md border border-emerald-500/40 px-2 py-0.5 text-xs text-emerald-200">
                                        {{ $skill->pivot->cost_paid }} оч.
                                    </span>
                                </div>
                                @if ($skill->description)
                                    <p class="mt-2 text-sm text-zinc-400">{{ $skill->description }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <h2 class="font-semibold text-white">Доступные навыки</h2>

                @if ($availableSkills->isEmpty())
                    <p class="mt-4 text-sm text-zinc-400">Новых навыков для покупки нет.</p>
                @else
                    <div class="mt-4 space-y-3">
                        @foreach ($availableSkills as $skill)
                            @php
                                $isAvailable = $skill->isAvailableFor($creature);
                                $canBuy = $isAvailable && $creature->development_points >= $skill->cost && $creature->skills->count() < $creature->maxSkills();
                            @endphp

                            <article class="rounded-md border border-zinc-800 bg-zinc-950 p-4 {{ $isAvailable ? '' : 'opacity-60' }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold text-white">{{ $skill->name }}</h3>
                                        @if ($skill->description)
                                            <p class="mt-1 text-sm text-zinc-400">{{ $skill->description }}</p>
                                        @endif
                                    </div>
                                    <span class="rounded-md border border-emerald-500/40 px-2 py-0.5 text-xs text-emerald-200">
                                        {{ $skill->cost }} оч.
                                    </span>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    <span class="text-xs text-zinc-400">
                                        Ур. {{ $skill->required_level }}
                                        @if ($skill->requiredType)
                                            / {{ $skill->requiredType->name }}
                                        @endif
                                        @if ($skill->requiredSpecies)
                                            / {{ $skill->requiredSpecies->name }}
                                        @endif
                                    </span>

                                    <form method="POST" action="{{ route('entities.skills.purchase', [$creature, $skill]) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-900 disabled:cursor-not-allowed disabled:opacity-50"
                                            @disabled(! $canBuy)
                                        >
                                            Купить
                                        </button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
