@extends('layouts.app', ['title' => $creature->name])

@section('content')
    @php
        $creatureInventory = $creature->inventory;
        $xpToNextLevel = \App\Services\BattleRewardService::xpToNextLevel($creature->level);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Карточка сущности</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">{{ $creature->name }}</h1>
                <p class="mt-1 text-sm text-zinc-400">{{ $creature->type->name }} / {{ $creature->species->name }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('entities.equipment', $creature) }}" class="rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400">
                    Экипировка
                </a>
                <a href="{{ route('entities.index') }}" class="rounded-md border border-zinc-700 px-4 py-2 text-zinc-200 hover:bg-zinc-900">
                    К списку
                </a>
            </div>
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
                <div class="mt-2 text-3xl font-semibold text-white">{{ $creature->current_hp }}/{{ $creature->effectiveMaxHp() }}</div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1fr_1fr_1fr]">
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                @include('partials.progress-bar', ['value' => $creature->xp, 'max' => $xpToNextLevel, 'label' => 'Опыт до следующего уровня', 'tone' => 'sky'])
                <p class="mt-3 text-xs text-zinc-500">Следующий уровень: {{ $xpToNextLevel }} XP.</p>
            </div>
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                @include('partials.progress-bar', ['value' => $creature->current_hp, 'max' => $creature->effectiveMaxHp(), 'label' => 'Здоровье'])
                <p class="mt-3 text-xs text-zinc-500">Экипировка уже учтена в максимальном HP.</p>
            </div>
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="grid grid-cols-3 gap-2 text-center text-sm">
                    <div>
                        <div class="text-xs uppercase text-zinc-500">W</div>
                        <div class="mt-1 text-xl font-semibold text-white">{{ $creature->wins }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-zinc-500">D</div>
                        <div class="mt-1 text-xl font-semibold text-white">{{ $creature->draws }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-zinc-500">L</div>
                        <div class="mt-1 text-xl font-semibold text-white">{{ $creature->losses }}</div>
                    </div>
                </div>
            </div>
        </section>

        @include('game.creatures.partials.special-summary', ['creature' => $creature])

        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold text-white">Экипировка</h2>
                <a href="{{ route('entities.equipment', $creature) }}" class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-950">
                    Управлять
                </a>
            </div>

            @if ($creature->equipmentRows->isEmpty())
                <p class="mt-4 text-sm text-zinc-400">Предметы пока не экипированы.</p>
            @else
                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    @foreach ($creature->equipmentRows->groupBy('item_instance_id') as $equipmentRows)
                        @php
                            $itemInstance = $equipmentRows->first()->itemInstance;
                            $item = $itemInstance->item;
                        @endphp
                        <article class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-white">{{ $item->name }}</h3>
                                    <div class="mt-2">
                                        @include('partials.rarity-badge', ['item' => $item])
                                    </div>
                                    <p class="mt-1 text-xs text-zinc-500">
                                        {{ $equipmentRows->pluck('slot.name')->filter()->implode(', ') }}
                                    </p>
                                    <x-item-details
                                        :item="$item"
                                        :slot-summary="$equipmentRows->pluck('slot.name')->filter()->implode(', ')"
                                        class="mt-3"
                                    />
                                </div>
                                <form method="POST" action="{{ route('entities.equipment.unequip', [$creature, $itemInstance]) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-900 disabled:cursor-not-allowed disabled:opacity-50"
                                        @disabled(! $creature->is_available_for_battle || ! $creatureInventory->hasFreeSlot())
                                    >
                                        Снять
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold text-white">Инвентарь сущности</h2>
                <span class="rounded-md border border-zinc-800 px-3 py-1 text-sm text-zinc-300">
                    {{ $creatureInventory->usedSlots() }}/{{ $creatureInventory->capacity() }}
                </span>
            </div>

            @if (! $creature->is_available_for_battle)
                <div class="mt-4 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">
                    Перенос и экипировка заблокированы на время боя.
                </div>
            @endif

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div>
                    <h3 class="text-sm font-medium uppercase text-zinc-400">У сущности</h3>

                    @if ($creatureInventory->inventoryItems->isEmpty())
                        <div class="mt-3 rounded-md border border-dashed border-zinc-700 bg-zinc-950 p-4 text-center text-sm text-zinc-400">
                            Пусто.
                        </div>
                    @else
                        <div class="mt-3 space-y-3">
                            @foreach ($creatureInventory->inventoryItems->sortBy('slot_number') as $inventoryItem)
                                @php
                                    $item = $inventoryItem->itemInstance->item;
                                @endphp
                                <article class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h4 class="font-semibold text-white">{{ $item->name }}</h4>
                                            <div class="mt-2">
                                                @include('partials.rarity-badge', ['item' => $item])
                                            </div>
                                            <div class="mt-1 flex flex-wrap gap-2">
                                                <span class="text-xs text-zinc-500">Ячейка {{ $inventoryItem->slot_number }}</span>
                                                @if ($item->isConsumable())
                                                    <span class="text-xs text-emerald-300">Заряды: {{ $inventoryItem->itemInstance->remainingUses() }}</span>
                                                @endif
                                            </div>
                                            <x-item-details
                                                :item="$item"
                                                :inventory-item="$inventoryItem"
                                                class="mt-3"
                                            />
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @include('game.inventory.partials.use-consumable-form', [
                                                'inventoryItem' => $inventoryItem,
                                                'targetCreature' => $creature,
                                            ])

                                            @if ($item->isEquipment())
                                                <form method="POST" action="{{ route('entities.equipment.equip', [$creature, $inventoryItem]) }}">
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="rounded-md border border-emerald-500/50 px-3 py-2 text-sm text-emerald-200 hover:bg-emerald-500/10 disabled:cursor-not-allowed disabled:opacity-50"
                                                        @disabled(! $creature->is_available_for_battle)
                                                    >
                                                        Надеть
                                                    </button>
                                                </form>
                                            @endif
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
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div>
                    <h3 class="text-sm font-medium uppercase text-zinc-400">У игрока</h3>

                    @if ($playerInventory->inventoryItems->isEmpty())
                        <div class="mt-3 rounded-md border border-dashed border-zinc-700 bg-zinc-950 p-4 text-center text-sm text-zinc-400">
                            Пусто.
                        </div>
                    @else
                        <div class="mt-3 space-y-3">
                            @foreach ($playerInventory->inventoryItems->sortBy('slot_number') as $inventoryItem)
                                @php
                                    $item = $inventoryItem->itemInstance->item;
                                @endphp
                                <article class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h4 class="font-semibold text-white">{{ $item->name }}</h4>
                                            <div class="mt-2">
                                                @include('partials.rarity-badge', ['item' => $item])
                                            </div>
                                            <div class="mt-1 flex flex-wrap gap-2">
                                                <span class="text-xs text-zinc-500">Ячейка {{ $inventoryItem->slot_number }}</span>
                                                @if ($item->isConsumable())
                                                    <span class="text-xs text-emerald-300">Заряды: {{ $inventoryItem->itemInstance->remainingUses() }}</span>
                                                @endif
                                            </div>
                                            <x-item-details
                                                :item="$item"
                                                :inventory-item="$inventoryItem"
                                                class="mt-3"
                                            />
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @include('game.inventory.partials.use-consumable-form', [
                                                'inventoryItem' => $inventoryItem,
                                                'targetCreature' => $creature,
                                            ])

                                            @if ($item->isEquipment())
                                                <form method="POST" action="{{ route('entities.equipment.equip', [$creature, $inventoryItem]) }}">
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="rounded-md border border-emerald-500/50 px-3 py-2 text-sm text-emerald-200 hover:bg-emerald-500/10 disabled:cursor-not-allowed disabled:opacity-50"
                                                        @disabled(! $creature->is_available_for_battle)
                                                    >
                                                        Надеть
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('inventory-items.move-to-creature', $inventoryItem) }}">
                                                @csrf
                                                <input type="hidden" name="creature_id" value="{{ $creature->id }}">
                                                <button
                                                    type="submit"
                                                    class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-900 disabled:cursor-not-allowed disabled:opacity-50"
                                                    @disabled(! $creature->is_available_for_battle || ! $creatureInventory->hasFreeSlot())
                                                >
                                                    Передать
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
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
