@extends('layouts.app', ['title' => 'Магазин'])

@section('content')
    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Экономика</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Магазин</h1>
                <p class="mt-1 text-sm text-zinc-400">Покупка экипировки, расходников, услуг и расширения общего инвентаря.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-sm">
                <span class="rounded-md border border-emerald-500/40 px-3 py-2 text-emerald-100">
                    {{ $user->tokens }} токенов
                </span>
                <span class="rounded-md border border-sky-500/40 px-3 py-2 text-sky-100">
                    Скидка {{ $user->shopDiscountPercent() }}%
                </span>
                <span class="rounded-md border border-zinc-800 px-3 py-2 text-zinc-300">
                    Инвентарь {{ $playerInventory->usedSlots() }}/{{ $playerInventory->capacity() }}
                </span>
            </div>
        </div>

        @include('partials.form-errors')

        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            <form method="GET" action="{{ route('shop') }}" class="grid gap-3 md:grid-cols-6">
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
                    <span class="text-xs uppercase text-zinc-500">Цена до</span>
                    <input
                        type="number"
                        min="0"
                        name="max_price"
                        value="{{ $filters['max_price'] ?? '' }}"
                        class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100"
                    >
                </label>

                <label class="space-y-1">
                    <span class="text-xs uppercase text-zinc-500">Уровень до</span>
                    <input
                        type="number"
                        min="1"
                        name="level"
                        value="{{ $filters['level'] ?? $user->level }}"
                        class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100"
                    >
                </label>

                <label class="space-y-1">
                    <span class="text-xs uppercase text-zinc-500">Доступность</span>
                    <select name="available" class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                        <option value="">Все товары</option>
                        <option value="1" @selected(($filters['available'] ?? '') === '1')>Можно купить сейчас</option>
                    </select>
                </label>

                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md bg-emerald-500 px-4 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400">
                        Фильтр
                    </button>
                    <a href="{{ route('shop') }}" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-950">
                        Сброс
                    </a>
                </div>
            </form>
        </section>

        <section class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold text-white">Товары</h2>
                    <span class="text-sm text-zinc-400">Найдено: {{ $items->count() }}</span>
                </div>

                @if ($items->isEmpty())
                    <div class="rounded-md border border-dashed border-zinc-700 bg-zinc-900 p-6 text-center text-sm text-zinc-400">
                        Под выбранные фильтры товары не найдены.
                    </div>
                @else
                    <div class="grid gap-4 xl:grid-cols-2">
                        @foreach ($items as $item)
                            @php
                                $uniqueOwned = $item->is_unique && in_array($item->id, $ownedUniqueItemIds, true);
                                $itemPrice = \App\Services\ShopService::itemPriceFor($user, $item);
                                $hasTokens = $user->tokens >= $itemPrice;
                                $hasSpace = $playerInventory->hasFreeSlot();
                                $available = $item->canBePurchasedBy($user);
                                $canBuy = $available && $hasTokens && $hasSpace && ! $uniqueOwned;
                            @endphp

                            <article class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <x-game-icon :icon="$item->icon" :label="$item->name" />
                                        <div class="min-w-0">
                                            <h3 class="text-lg font-semibold text-white">{{ $item->name }}</h3>
                                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                                <span class="text-xs text-zinc-500">{{ \App\Models\Item::TYPES[$item->item_type] ?? $item->item_type }}</span>
                                                @include('partials.rarity-badge', ['item' => $item])
                                            </div>
                                        </div>
                                    </div>
                                    <span class="rounded-md border border-emerald-500/40 px-3 py-1 text-sm text-emerald-100">
                                        {{ $itemPrice }} ток.
                                        @if ($itemPrice !== $item->price)
                                            <span class="ml-1 text-xs text-zinc-400 line-through">{{ $item->price }}</span>
                                        @endif
                                    </span>
                                </div>

                                @if ($item->description)
                                    <p class="mt-3 text-sm text-zinc-400">{{ $item->description }}</p>
                                @endif

                                <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                                    <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                                        <dt class="text-xs text-zinc-500">Уровень</dt>
                                        <dd class="mt-1 text-zinc-200">{{ $item->required_level }}</dd>
                                    </div>
                                    <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                                        <dt class="text-xs text-zinc-500">Длительность</dt>
                                        <dd class="mt-1 text-zinc-200">{{ \App\Models\Item::DURATIONS[$item->duration_type] ?? $item->duration_type }}</dd>
                                    </div>
                                </dl>

                                @if ($item->bonuses)
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach ($item->bonuses as $bonus => $value)
                                            <span class="rounded-md border border-zinc-700 px-2 py-1 text-xs text-zinc-300">
                                                {{ $bonus }} {{ (int) $value > 0 ? '+' : '' }}{{ $value }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                @if (! $available)
                                    <p class="mt-4 text-sm text-amber-200">Недоступно по уровню игрока.</p>
                                @elseif (! $hasTokens)
                                    <p class="mt-4 text-sm text-amber-200">Недостаточно токенов.</p>
                                @elseif (! $hasSpace)
                                    <p class="mt-4 text-sm text-amber-200">Нет свободной ячейки в общем инвентаре.</p>
                                @elseif ($uniqueOwned)
                                    <p class="mt-4 text-sm text-amber-200">Уникальный предмет уже есть у игрока.</p>
                                @endif

                                <form method="POST" action="{{ route('shop.items.buy', $item) }}" class="mt-4">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="rounded-md bg-emerald-500 px-4 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50"
                                        @disabled(! $canBuy)
                                    >
                                        Купить
                                    </button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>

            <aside class="space-y-4">
                <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                    <h2 class="font-semibold text-white">Расширение инвентаря</h2>
                    <p class="mt-2 text-sm text-zinc-400">
                        Следующая ячейка стоит {{ $inventorySlotCost }} токенов.
                    </p>
                    <form method="POST" action="{{ route('shop.inventory-slots.buy') }}" class="mt-4">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-md border border-emerald-500/50 px-4 py-2 text-sm text-emerald-100 hover:bg-emerald-500/10 disabled:cursor-not-allowed disabled:opacity-50"
                            @disabled($user->tokens < $inventorySlotCost)
                        >
                            Купить ячейку
                        </button>
                    </form>
                </section>

                <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                    <h2 class="font-semibold text-white">Услуги</h2>

                    @if ($creatures->isEmpty())
                        <p class="mt-3 text-sm text-zinc-400">Создай сущность, чтобы открыть услуги.</p>
                    @else
                        <form method="POST" action="{{ route('shop.services.rename-creature') }}" class="mt-4 space-y-3">
                            @csrf
                            <h3 class="text-sm font-semibold text-zinc-200">Смена имени / {{ $servicePrices['rename_creature'] }} ток.</h3>
                            <select name="creature_id" class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                                @foreach ($creatures as $creature)
                                    <option value="{{ $creature->id }}">{{ $creature->name }}</option>
                                @endforeach
                            </select>
                            <input
                                type="text"
                                name="name"
                                placeholder="Новое имя"
                                class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100"
                            >
                            <button
                                type="submit"
                                class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-950 disabled:cursor-not-allowed disabled:opacity-50"
                                @disabled($user->tokens < $servicePrices['rename_creature'])
                            >
                                Изменить
                            </button>
                        </form>

                        <div class="mt-5 border-t border-zinc-800 pt-4">
                            <h3 class="text-sm font-semibold text-zinc-200">Сброс развития</h3>
                            <div class="mt-3 grid gap-3">
                                <form method="POST" action="{{ route('shop.services.reset-skills') }}" class="space-y-3">
                                    @csrf
                                    <select name="creature_id" class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                                        @foreach ($creatures as $creature)
                                            <option value="{{ $creature->id }}">{{ $creature->name }} / навыков {{ $creature->skills->count() }}</option>
                                        @endforeach
                                    </select>
                                    <button
                                        type="submit"
                                        class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-950 disabled:cursor-not-allowed disabled:opacity-50"
                                        @disabled($user->tokens < $servicePrices['reset_skills'])
                                    >
                                        Сбросить навыки / {{ $servicePrices['reset_skills'] }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('shop.services.reset-special') }}" class="space-y-3">
                                    @csrf
                                    <select name="creature_id" class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                                        @foreach ($creatures as $creature)
                                            <option value="{{ $creature->id }}">{{ $creature->name }} / ур. {{ $creature->level }}</option>
                                        @endforeach
                                    </select>
                                    <button
                                        type="submit"
                                        class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-950 disabled:cursor-not-allowed disabled:opacity-50"
                                        @disabled($user->tokens < $servicePrices['reset_special'])
                                    >
                                        Сбросить SPECIAL / {{ $servicePrices['reset_special'] }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </section>
            </aside>
        </section>
    </div>
@endsection
