@extends('layouts.app', ['title' => 'Инвентарь'])

@section('content')
    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Предметы</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Инвентарь</h1>
            </div>
            <span class="rounded-md border border-zinc-800 px-3 py-1 text-sm text-zinc-300">
                {{ $playerInventory->usedSlots() }}/{{ $playerInventory->capacity() }}
            </span>
        </div>

        @include('partials.form-errors')

        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-white">Общий инвентарь игрока</h2>
                    <p class="mt-1 text-sm text-zinc-400">
                        Свободно {{ $playerInventory->freeSlots() }} из {{ $playerInventory->capacity() }} ячеек.
                    </p>
                </div>
                <span class="rounded-md border border-emerald-500/40 px-3 py-1 text-sm text-emerald-200">
                    Ур. {{ $user->level }}
                </span>
            </div>

            @if ($playerInventory->inventoryItems->isEmpty())
                <div class="mt-5 rounded-md border border-dashed border-zinc-700 bg-zinc-950 p-6 text-center text-sm text-zinc-400">
                    Предметов пока нет.
                </div>
            @else
                <div class="mt-5 grid gap-3 lg:grid-cols-2">
                    @foreach ($playerInventory->inventoryItems->sortBy('slot_number') as $inventoryItem)
                        @php
                            $item = $inventoryItem->itemInstance->item;
                        @endphp
                        <article class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-white">{{ $item->name }}</h3>
                                    <p class="mt-1 text-xs text-zinc-500">Ячейка {{ $inventoryItem->slot_number }}</p>
                                </div>
                                <span class="rounded-md border border-zinc-700 px-2 py-0.5 text-xs text-zinc-300">
                                    {{ \App\Models\Item::RARITIES[$item->rarity] ?? $item->rarity }}
                                </span>
                            </div>

                            @if ($item->description)
                                <p class="mt-3 text-sm text-zinc-400">{{ $item->description }}</p>
                            @endif

                            @if ($creatures->isNotEmpty())
                                <form method="POST" action="{{ route('inventory-items.move-to-creature', $inventoryItem) }}" class="mt-4 flex flex-wrap gap-2">
                                    @csrf
                                    <select name="creature_id" class="min-w-0 flex-1 rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                                        @foreach ($creatures as $creature)
                                            @php
                                                $targetInventory = $creature->inventory;
                                            @endphp
                                            <option
                                                value="{{ $creature->id }}"
                                                @disabled(! $creature->is_available_for_battle || ! $targetInventory?->hasFreeSlot())
                                            >
                                                {{ $creature->name }} ({{ $targetInventory?->usedSlots() ?? 0 }}/{{ $targetInventory?->capacity() ?? 0 }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-900">
                                        Передать
                                    </button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-xl font-semibold text-white">Инвентарь сущностей</h2>
            </div>

            @if ($creatures->isEmpty())
                <div class="rounded-md border border-zinc-800 bg-zinc-900 p-6 text-sm text-zinc-400">
                    Сущности пока не созданы.
                </div>
            @else
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($creatures as $creature)
                        @php
                            $inventory = $creature->inventory;
                        @endphp
                        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-white">{{ $creature->name }}</h3>
                                    <p class="mt-1 text-sm text-zinc-400">{{ $creature->type->name }} / {{ $creature->species->name }}</p>
                                </div>
                                <span class="rounded-md border border-zinc-700 px-3 py-1 text-sm text-zinc-300">
                                    {{ $inventory->usedSlots() }}/{{ $inventory->capacity() }}
                                </span>
                            </div>

                            @if (! $creature->is_available_for_battle)
                                <div class="mt-4 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">
                                    Перенос заблокирован на время боя.
                                </div>
                            @endif

                            @if ($inventory->inventoryItems->isEmpty())
                                <div class="mt-4 rounded-md border border-dashed border-zinc-700 bg-zinc-950 p-4 text-center text-sm text-zinc-400">
                                    Пусто.
                                </div>
                            @else
                                <div class="mt-4 space-y-3">
                                    @foreach ($inventory->inventoryItems->sortBy('slot_number') as $inventoryItem)
                                        @php
                                            $item = $inventoryItem->itemInstance->item;
                                        @endphp
                                        <article class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <h4 class="font-semibold text-white">{{ $item->name }}</h4>
                                                    <p class="mt-1 text-xs text-zinc-500">Ячейка {{ $inventoryItem->slot_number }}</p>
                                                </div>
                                                <form method="POST" action="{{ route('inventory-items.move-to-player', $inventoryItem) }}">
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-900 disabled:cursor-not-allowed disabled:opacity-50"
                                                        @disabled(! $creature->is_available_for_battle)
                                                    >
                                                        Забрать
                                                    </button>
                                                </form>
                                            </div>
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
