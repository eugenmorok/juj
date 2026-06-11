@extends('layouts.app', ['title' => 'Сущности'])

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Пул сущностей</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Сущности</h1>
            </div>
            <button type="button" class="rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400">
                Создать сущность
            </button>
        </div>

        @if ($creatureTypes->isEmpty())
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-8 text-center">
                <h2 class="text-lg font-semibold text-white">Справочник пуст</h2>
                <p class="mt-2 text-sm text-zinc-400">Администратор пока не добавил активные типы сущностей.</p>
            </div>
        @else
            <div class="space-y-5">
                @foreach ($creatureTypes as $type)
                    <section class="rounded-md border border-zinc-800 bg-zinc-900">
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-800 px-5 py-4">
                            <div>
                                <h2 class="text-xl font-semibold text-white">{{ $type->name }}</h2>
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
                                                <h3 class="font-semibold text-white">{{ $species->name }}</h3>
                                                <span class="rounded-md border border-zinc-700 px-2 py-0.5 text-xs text-zinc-300">
                                                    {{ \App\Models\CreatureSpecies::RARITIES[$species->rarity] ?? $species->rarity }}
                                                </span>
                                                @if ($species->is_starter_available)
                                                    <span class="rounded-md border border-emerald-500/40 px-2 py-0.5 text-xs text-emerald-200">
                                                        Доступен при создании
                                                    </span>
                                                @else
                                                    <span class="rounded-md border border-amber-500/40 px-2 py-0.5 text-xs text-amber-200">
                                                        Не стартовый
                                                    </span>
                                                @endif
                                            </div>
                                            @if ($species->description)
                                                <p class="mt-1 text-sm text-zinc-400">{{ $species->description }}</p>
                                            @endif
                                        </div>

                                        <dl class="grid grid-cols-7 gap-2 text-center text-xs">
                                            @foreach ([
                                                'S' => $species->base_strength,
                                                'P' => $species->base_perception,
                                                'E' => $species->base_endurance,
                                                'C' => $species->base_charisma,
                                                'I' => $species->base_intelligence,
                                                'A' => $species->base_agility,
                                                'L' => $species->base_luck,
                                            ] as $label => $value)
                                                <div class="min-w-10 rounded-md border border-zinc-800 bg-zinc-950 px-2 py-1">
                                                    <dt class="text-zinc-500">{{ $label }}</dt>
                                                    <dd class="mt-0.5 font-semibold text-white">{{ $value }}</dd>
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
    </div>
@endsection
