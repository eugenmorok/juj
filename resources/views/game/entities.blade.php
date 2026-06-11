@extends('layouts.app', ['title' => 'Сущности'])

@section('content')
    <div class="space-y-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Пул сущностей</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Сущности</h1>
            </div>
            <a href="{{ route('entities.create') }}" class="rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400">
                Создать сущность
            </a>
        </div>

        <section class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-white">Мои сущности</h2>
                    <p class="mt-1 text-sm text-zinc-400">Боевой состав, доступный для арены и развития.</p>
                </div>
                <span class="rounded-md border border-zinc-800 px-3 py-1 text-sm text-zinc-300">
                    {{ $creatures->count() }} создано
                </span>
            </div>

            @if ($creatures->isEmpty())
                <div class="rounded-md border border-zinc-800 bg-zinc-900 p-8 text-center">
                    <h3 class="text-lg font-semibold text-white">Пул пока пуст</h3>
                    <p class="mt-2 text-sm text-zinc-400">Первая сущность создается из активных стартовых видов.</p>
                </div>
            @else
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($creatures as $creature)
                        <a href="{{ route('entities.show', $creature) }}" class="rounded-md border border-zinc-800 bg-zinc-900 p-5 hover:border-emerald-500/60">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-white">{{ $creature->name }}</h3>
                                    <p class="mt-1 text-sm text-zinc-400">{{ $creature->type->name }} / {{ $creature->species->name }}</p>
                                </div>
                                <span class="rounded-md border border-emerald-500/40 px-3 py-1 text-sm text-emerald-200">
                                    Ур. {{ $creature->level }}
                                </span>
                            </div>

                            <dl class="mt-4 grid grid-cols-7 gap-2 text-center text-xs">
                                @foreach (\App\Models\Creature::SPECIAL_LABELS as $attribute => $label)
                                    <div class="rounded-md border border-zinc-800 bg-zinc-950 px-2 py-1">
                                        <dt class="text-zinc-500">{{ $label }}</dt>
                                        <dd class="mt-0.5 font-semibold text-white">{{ $creature->{$attribute} }}</dd>
                                    </div>
                                @endforeach
                            </dl>

                            <div class="mt-4 grid gap-2 text-sm sm:grid-cols-3">
                                <div class="rounded-md border border-zinc-800 px-3 py-2">
                                    <span class="text-zinc-400">HP</span>
                                    <span class="ml-2 font-semibold text-white">{{ $creature->current_hp }}/{{ $creature->max_hp }}</span>
                                </div>
                                <div class="rounded-md border border-zinc-800 px-3 py-2">
                                    <span class="text-zinc-400">Очки</span>
                                    <span class="ml-2 font-semibold text-white">{{ $creature->development_points }}</span>
                                </div>
                                <div class="rounded-md border border-zinc-800 px-3 py-2">
                                    <span class="text-zinc-400">Навыки</span>
                                    <span class="ml-2 font-semibold text-white">{{ $creature->skills->count() }}/{{ $creature->maxSkills() }}</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-xl font-semibold text-white">Каталог стартовых видов</h2>
                <p class="mt-1 text-sm text-zinc-400">Обычный игрок видит только активные типы и виды.</p>
            </div>

            @if ($creatureTypes->isEmpty())
                <div class="rounded-md border border-zinc-800 bg-zinc-900 p-8 text-center">
                    <h3 class="text-lg font-semibold text-white">Справочник пуст</h3>
                    <p class="mt-2 text-sm text-zinc-400">Администратор пока не добавил активные типы сущностей.</p>
                </div>
            @else
                <div class="space-y-5">
                    @foreach ($creatureTypes as $type)
                        <section class="rounded-md border border-zinc-800 bg-zinc-900">
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-800 px-5 py-4">
                                <div>
                                    <h3 class="text-xl font-semibold text-white">{{ $type->name }}</h3>
                                    @if ($type->description)
                                        <p class="mt-1 max-w-3xl text-sm text-zinc-400">{{ $type->description }}</p>
                                    @endif
                                </div>
                                <span class="rounded-md border border-emerald-500/40 px-3 py-1 text-sm text-emerald-200">
                                    {{ $type->species_count }} видов
                                </span>
                            </div>

                            @if ($type->species->isEmpty())
                                <div class="px-5 py-5 text-sm text-zinc-400">
                                    У этого типа пока нет активных видов.
                                </div>
                            @else
                                <div class="divide-y divide-zinc-800">
                                    @foreach ($type->species as $species)
                                        <article class="grid gap-4 px-5 py-4 lg:grid-cols-[1fr_auto] lg:items-center">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h4 class="font-semibold text-white">{{ $species->name }}</h4>
                                                    <span class="rounded-md border border-zinc-700 px-2 py-0.5 text-xs text-zinc-300">
                                                        {{ \App\Models\CreatureSpecies::RARITIES[$species->rarity] ?? $species->rarity }}
                                                    </span>
                                                    @if ($species->is_starter_available)
                                                        <span class="rounded-md border border-emerald-500/40 px-2 py-0.5 text-xs text-emerald-200">
                                                            Доступен при создании
                                                        </span>
                                                    @endif
                                                </div>
                                                @if ($species->description)
                                                    <p class="mt-1 text-sm text-zinc-400">{{ $species->description }}</p>
                                                @endif
                                            </div>

                                            <dl class="grid grid-cols-7 gap-2 text-center text-xs">
                                                @foreach (\App\Models\Creature::SPECIAL_LABELS as $attribute => $label)
                                                    <div class="min-w-10 rounded-md border border-zinc-800 bg-zinc-950 px-2 py-1">
                                                        <dt class="text-zinc-500">{{ $label }}</dt>
                                                        <dd class="mt-0.5 font-semibold text-white">{{ $species->baseSpecialValue($attribute) }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
