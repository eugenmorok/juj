@extends('layouts.app', ['title' => 'Инвентарь'])

@section('content')
    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Предметы</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Инвентарь</h1>
                <p class="mt-1 text-sm text-zinc-400">Фильтруй общий инвентарь и предметы сущностей, затем переносите их между владельцами.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="rounded-md border border-emerald-500/40 px-3 py-1 text-sm text-emerald-200">
                    {{ $user->tokens }} токенов
                </span>
                <span class="rounded-md border border-zinc-800 px-3 py-1 text-sm text-zinc-300">
                    {{ $playerInventory->usedSlots() }}/{{ $playerInventory->capacity() }}
                </span>
            </div>
        </div>

        @include('partials.form-errors')

        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            <form method="GET" action="{{ route('inventory') }}" class="grid gap-3 md:grid-cols-6">
                <label class="space-y-1 md:col-span-2">
                    <span class="text-xs uppercase text-zinc-500">Поиск</span>
                    <input
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        maxlength="80"
                        placeholder="Название, код или описание"
                        class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100"
                    >
                </label>

                <label class="space-y-1">
                    <span class="text-xs uppercase text-zinc-500">Категория</span>
                    <select name="item_type" class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                        <option value="">Все</option>
                        @foreach (\App\Models\Item::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['item_type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs uppercase text-zinc-500">Редкость</span>
                    <select name="rarity" class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                        <option value="">Все</option>
                        @foreach (\App\Models\Item::RARITIES as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['rarity'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs uppercase text-zinc-500">Где лежит</span>
                    <select name="location" class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                        <option value="all" @selected(($filters['location'] ?? 'all') === 'all')>Везде</option>
                        <option value="player" @selected(($filters['location'] ?? '') === 'player')>У игрока</option>
                        <option value="creatures" @selected(($filters['location'] ?? '') === 'creatures')>У сущностей</option>
                    </select>
                </label>

                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md bg-emerald-500 px-4 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400">
                        Фильтр
                    </button>
                    <a href="{{ route('inventory') }}" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-950">
                        Сброс
                    </a>
                </div>
            </form>
        </section>

        @if ($showPlayerInventory)
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

                @if ($playerInventoryItems->isEmpty())
                    <div class="mt-5 rounded-md border border-dashed border-zinc-700 bg-zinc-950 p-6 text-center text-sm text-zinc-400">
                        Предметы не найдены по выбранным фильтрам.
                    </div>
                @else
                    <div class="mt-5 grid gap-3 lg:grid-cols-2">
                        @foreach ($playerInventoryItems as $inventoryItem)
                            @php
                                $item = $inventoryItem->itemInstance->item;
                                $sellPrice = \App\Services\ShopService::sellPrice($item);
                            @endphp
                            <article class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <x-game-icon :icon="$item->icon" :label="$item->name" size="sm" />
                                        <div class="min-w-0">
                                            <h3 class="font-semibold text-white">{{ $item->name }}</h3>
                                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                                <span class="text-xs text-zinc-500">Ячейка {{ $inventoryItem->slot_number }}</span>
                                                <span class="text-xs text-zinc-500">{{ \App\Models\Item::TYPES[$item->item_type] ?? $item->item_type }}</span>
                                                @include('partials.rarity-badge', ['item' => $item])
                                                @if ($item->isConsumable())
                                                    <span class="text-xs text-emerald-300">Заряды: {{ $inventoryItem->itemInstance->remainingUses() }}</span>
                                                @endif
                                                <span class="text-xs text-amber-300">Продажа: {{ $sellPrice }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <x-item-details
                                    :item="$item"
                                    :inventory-item="$inventoryItem"
                                    :sell-price="$sellPrice"
                                    class="mt-3"
                                />

                                @include('game.inventory.partials.use-consumable-form', [
                                    'inventoryItem' => $inventoryItem,
                                    'creatures' => $creatures,
                                ])

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

                                <form method="POST" action="{{ route('inventory-items.sell', $inventoryItem) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-amber-500/50 px-3 py-2 text-sm text-amber-100 hover:bg-amber-500/10">
                                        Продать за {{ $sellPrice }}
                                    </button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        @if ($showCreatureInventories)
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
                                $filteredItems = $creatureInventoryItems[$creature->id] ?? collect();
                            @endphp
                            <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <x-game-icon :icon="$creature->species?->portrait_image ?? $creature->species?->icon ?? $creature->type?->icon" :label="$creature->name" size="sm" />
                                        <div class="min-w-0">
                                            <h3 class="text-lg font-semibold text-white">{{ $creature->name }}</h3>
                                            <p class="mt-1 text-sm text-zinc-400">{{ $creature->type->name }} / {{ $creature->species->name }}</p>
                                        </div>
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

                                @if ($filteredItems->isEmpty())
                                    <div class="mt-4 rounded-md border border-dashed border-zinc-700 bg-zinc-950 p-4 text-center text-sm text-zinc-400">
                                        Предметы не найдены по выбранным фильтрам.
                                    </div>
                                @else
                                    <div class="mt-4 space-y-3">
                                        @foreach ($filteredItems as $inventoryItem)
                                            @php
                                                $item = $inventoryItem->itemInstance->item;
                                                $sellPrice = \App\Services\ShopService::sellPrice($item);
                                            @endphp
                                            <article class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                                                <div class="flex flex-wrap items-start justify-between gap-3">
                                                    <div class="flex min-w-0 items-start gap-3">
                                                        <x-game-icon :icon="$item->icon" :label="$item->name" size="sm" />
                                                        <div class="min-w-0">
                                                            <h4 class="font-semibold text-white">{{ $item->name }}</h4>
                                                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                                                <span class="text-xs text-zinc-500">Ячейка {{ $inventoryItem->slot_number }}</span>
                                                                <span class="text-xs text-zinc-500">{{ \App\Models\Item::TYPES[$item->item_type] ?? $item->item_type }}</span>
                                                                @include('partials.rarity-badge', ['item' => $item])
                                                                @if ($item->isConsumable())
                                                                    <span class="text-xs text-emerald-300">Заряды: {{ $inventoryItem->itemInstance->remainingUses() }}</span>
                                                                @endif
                                                                <span class="text-xs text-amber-300">Продажа: {{ $sellPrice }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2">
                                                        @include('game.inventory.partials.use-consumable-form', [
                                                            'inventoryItem' => $inventoryItem,
                                                            'targetCreature' => $creature,
                                                        ])

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

                                                        <form method="POST" action="{{ route('inventory-items.sell', $inventoryItem) }}">
                                                            @csrf
                                                            <button
                                                                type="submit"
                                                                class="rounded-md border border-amber-500/50 px-3 py-2 text-sm text-amber-100 hover:bg-amber-500/10 disabled:cursor-not-allowed disabled:opacity-50"
                                                                @disabled(! $creature->is_available_for_battle)
                                                            >
                                                                Продать за {{ $sellPrice }}
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <x-item-details
                                                    :item="$item"
                                                    :inventory-item="$inventoryItem"
                                                    :sell-price="$sellPrice"
                                                    class="mt-3"
                                                />
                                            </article>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection
