@php
    $itemInstance = $inventoryItem->itemInstance;
    $item = $itemInstance->item;
    $slotKeys = $item->equipmentSlotKeys();
    $hasOccupiedSlot = collect($slotKeys)->intersect($occupiedSlotKeys)->isNotEmpty();
    $canEquip = $creature->is_available_for_battle
        && $item->isEquipment()
        && $item->canBeUsedBy($creature)
        && $slotKeys !== []
        && ! $hasOccupiedSlot;
@endphp

<article class="rounded-md border border-zinc-800 bg-zinc-950 p-4 {{ $item->isEquipment() ? '' : 'opacity-70' }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="font-semibold text-white">{{ $item->name }}</h3>
            <p class="mt-1 text-xs text-zinc-500">
                Ячейка {{ $inventoryItem->slot_number }}
                @if ($slotKeys !== [])
                    / {{ implode(', ', $slotKeys) }}
                @endif
            </p>
        </div>
        <span class="rounded-md border border-zinc-700 px-2 py-0.5 text-xs text-zinc-300">
            {{ \App\Models\Item::RARITIES[$item->rarity] ?? $item->rarity }}
        </span>
    </div>

    @if ($item->description)
        <p class="mt-2 text-sm text-zinc-400">{{ $item->description }}</p>
    @endif

    @if (! $item->isEquipment())
        <p class="mt-3 text-xs text-zinc-500">Не является экипировкой.</p>
    @elseif (! $item->canBeUsedBy($creature))
        <p class="mt-3 text-xs text-amber-200">Не подходит по типу, виду или уровню.</p>
    @elseif ($hasOccupiedSlot)
        <p class="mt-3 text-xs text-amber-200">Один из нужных слотов занят.</p>
    @elseif ($slotKeys === [])
        <p class="mt-3 text-xs text-amber-200">У предмета не задан слот.</p>
    @endif

    <form method="POST" action="{{ route('entities.equipment.equip', [$creature, $inventoryItem]) }}" class="mt-4">
        @csrf
        <button
            type="submit"
            class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-900 disabled:cursor-not-allowed disabled:opacity-50"
            @disabled(! $canEquip)
        >
            Надеть
        </button>
    </form>
</article>
