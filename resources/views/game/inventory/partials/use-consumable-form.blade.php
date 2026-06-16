@php
    $itemInstance = $inventoryItem->itemInstance;
    $item = $itemInstance->item;
    $targetCreatures = isset($targetCreature)
        ? collect([$targetCreature])
        : ($creatures ?? collect());
    $hasAvailableTarget = $targetCreatures->contains(fn ($creature) => $creature->is_available_for_battle && $item->canBeUsedBy($creature));
@endphp

@if ($item->isConsumable() && $targetCreatures->isNotEmpty())
    <form method="POST" action="{{ route('inventory-items.use', $inventoryItem) }}" class="flex min-w-0 flex-wrap gap-2">
        @csrf

        @if (isset($targetCreature))
            <input type="hidden" name="creature_id" value="{{ $targetCreature->id }}">
        @else
            <select name="creature_id" class="min-w-0 flex-1 rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                @foreach ($targetCreatures as $creatureOption)
                    <option
                        value="{{ $creatureOption->id }}"
                        @disabled(! $creatureOption->is_available_for_battle || ! $item->canBeUsedBy($creatureOption))
                    >
                        {{ $creatureOption->name }} (HP {{ $creatureOption->current_hp }}/{{ $creatureOption->effectiveMaxHp() }})
                    </option>
                @endforeach
            </select>
        @endif

        <button
            type="submit"
            class="rounded-md border border-emerald-500/50 px-3 py-2 text-sm text-emerald-200 hover:bg-emerald-500/10 disabled:cursor-not-allowed disabled:opacity-50"
            @disabled(! $hasAvailableTarget || $itemInstance->remainingUses() <= 0)
        >
            Применить
        </button>
    </form>
@endif
