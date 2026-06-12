@extends('layouts.app', ['title' => $creature->name.' / Экипировка'])

@section('content')
    @php
        $creatureInventory = $creature->inventory;
        $occupiedSlotKeys = $creature->equipmentRows->pluck('slot_key')->all();
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Экипировка</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">{{ $creature->name }}</h1>
                <p class="mt-1 text-sm text-zinc-400">{{ $creature->type->name }} / {{ $creature->species->name }}</p>
            </div>
            <a href="{{ route('entities.show', $creature) }}" class="rounded-md border border-zinc-700 px-4 py-2 text-zinc-200 hover:bg-zinc-900">
                К карточке
            </a>
        </div>

        @include('partials.form-errors')
        @include('game.creatures.partials.special-summary', ['creature' => $creature])

        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold text-white">10 слотов экипировки</h2>
                <span class="rounded-md border border-zinc-800 px-3 py-1 text-sm text-zinc-300">
                    {{ $creature->equipmentRows->pluck('slot_key')->unique()->count() }}/{{ $equipmentSlots->count() }}
                </span>
            </div>

            @if (! $creature->is_available_for_battle)
                <div class="mt-4 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">
                    Смена экипировки заблокирована на время боя.
                </div>
            @endif

            <div class="mt-5 grid gap-3 lg:grid-cols-2">
                @foreach ($equipmentSlots as $slot)
                    @php
                        $equipmentRow = $creature->equipmentRows->firstWhere('slot_key', $slot->code);
                        $equippedInstance = $equipmentRow?->itemInstance;
                        $equippedItem = $equippedInstance?->item;
                    @endphp

                    <article class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-white">{{ $slot->name }}</h3>
                                <p class="mt-1 text-xs text-zinc-500">{{ $slot->code }}</p>
                            </div>
                            @if ($equippedItem)
                                <span class="rounded-md border border-emerald-500/40 px-2 py-0.5 text-xs text-emerald-200">
                                    Занят
                                </span>
                            @else
                                <span class="rounded-md border border-zinc-700 px-2 py-0.5 text-xs text-zinc-300">
                                    Свободен
                                </span>
                            @endif
                        </div>

                        @if ($equippedItem)
                            <div class="mt-3 rounded-md border border-zinc-800 bg-zinc-900 p-3">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h4 class="font-semibold text-white">{{ $equippedItem->name }}</h4>
                                        <p class="mt-1 text-xs text-zinc-400">
                                            {{ \App\Models\Item::RARITIES[$equippedItem->rarity] ?? $equippedItem->rarity }}
                                        </p>
                                    </div>
                                    <form method="POST" action="{{ route('entities.equipment.unequip', [$creature, $equippedInstance]) }}">
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
                            </div>
                        @else
                            <p class="mt-3 text-sm text-zinc-500">Предмет не установлен.</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <h2 class="font-semibold text-white">Инвентарь сущности</h2>
                <p class="mt-1 text-sm text-zinc-400">{{ $creatureInventory->usedSlots() }}/{{ $creatureInventory->capacity() }} ячеек занято.</p>

                <div class="mt-4 space-y-3">
                    @forelse ($creatureInventory->inventoryItems->sortBy('slot_number') as $inventoryItem)
                        @include('game.creatures.partials.equipment-candidate', [
                            'creature' => $creature,
                            'inventoryItem' => $inventoryItem,
                            'occupiedSlotKeys' => $occupiedSlotKeys,
                        ])
                    @empty
                        <div class="rounded-md border border-dashed border-zinc-700 bg-zinc-950 p-4 text-center text-sm text-zinc-400">
                            Пусто.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <h2 class="font-semibold text-white">Инвентарь игрока</h2>
                <p class="mt-1 text-sm text-zinc-400">{{ $playerInventory->usedSlots() }}/{{ $playerInventory->capacity() }} ячеек занято.</p>

                <div class="mt-4 space-y-3">
                    @forelse ($playerInventory->inventoryItems->sortBy('slot_number') as $inventoryItem)
                        @include('game.creatures.partials.equipment-candidate', [
                            'creature' => $creature,
                            'inventoryItem' => $inventoryItem,
                            'occupiedSlotKeys' => $occupiedSlotKeys,
                        ])
                    @empty
                        <div class="rounded-md border border-dashed border-zinc-700 bg-zinc-950 p-4 text-center text-sm text-zinc-400">
                            Пусто.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection
