@props([
    'item',
    'inventoryItem' => null,
    'applicability' => null,
    'price' => null,
    'sellPrice' => null,
    'slotSummary' => null,
    'compact' => false,
    'showDescription' => true,
    'showEffects' => true,
    'showMeta' => true,
])

@php
    $itemInstance = $inventoryItem?->itemInstance;
    $slotKeys = $item->equipmentSlotKeys();
    $slotText = $slotSummary ?? ($slotKeys !== [] ? implode(', ', $slotKeys) : null);
    $duration = $item->durationSummary();
    $applicabilityText = $applicability ?? $item->applicabilitySummary();
    $damageBonus = $item->damageBonus();
    $defenseBonus = $item->defenseBonus();
    $hasCombatStats = $damageBonus !== 0 || $defenseBonus !== 0;
    $bonusSummaries = $item->bonusSummaries();

    $metaRows = [];

    if ($inventoryItem) {
        $metaRows[] = ['Ячейка', $inventoryItem->slot_number];
    }

    if ($showMeta) {
        $metaRows[] = ['Тип', \App\Models\Item::TYPES[$item->item_type] ?? $item->item_type];
        $metaRows[] = ['Редкость', \App\Models\Item::RARITIES[$item->rarity] ?? $item->rarity];
        $metaRows[] = ['Уровень', $item->required_level];
    }

    if ($slotText) {
        $metaRows[] = ['Слоты', $slotText];
    }

    if ($duration) {
        $metaRows[] = ['Длительность', $duration];
    }

    if ($item->is_unique) {
        $metaRows[] = ['Уникальность', 'Уникальный предмет'];
    }

    if ($itemInstance && $item->isConsumable()) {
        $metaRows[] = ['Заряды сейчас', $itemInstance->remainingUses()];
    }

    if ($applicabilityText) {
        $metaRows[] = ['Для сущностей', $applicabilityText];
    }

    if ($price !== null) {
        $metaRows[] = ['Цена', $price.' жет.'];
    }

    if ($sellPrice !== null) {
        $metaRows[] = ['Продажа', $sellPrice.' жет.'];
    }
@endphp

<div {{ $attributes->class(['space-y-3 text-sm']) }}>
    @if ($showDescription && $item->description)
        <p class="{{ $compact ? 'text-xs' : 'text-sm' }} text-zinc-400">{{ $item->description }}</p>
    @endif

    @if ($hasCombatStats)
        <dl class="grid gap-2 {{ $compact ? 'grid-cols-2' : 'sm:grid-cols-2' }}">
            <div class="rounded-md border border-rose-500/30 bg-rose-500/10 px-3 py-2">
                <dt class="text-xs text-rose-200/80">Урон</dt>
                <dd class="mt-1 font-semibold text-rose-100">
                    {{ $damageBonus > 0 ? '+' : '' }}{{ $damageBonus }}
                </dd>
            </div>
            <div class="rounded-md border border-sky-500/30 bg-sky-500/10 px-3 py-2">
                <dt class="text-xs text-sky-200/80">Защита</dt>
                <dd class="mt-1 font-semibold text-sky-100">
                    {{ $defenseBonus > 0 ? '+' : '' }}{{ $defenseBonus }}
                </dd>
            </div>
        </dl>
    @endif

    @if ($metaRows !== [])
        <dl class="grid gap-2 {{ $compact ? 'grid-cols-2' : 'sm:grid-cols-2' }}">
            @foreach ($metaRows as [$label, $value])
                <div class="rounded-md border border-zinc-800 bg-zinc-950/60 px-3 py-2">
                    <dt class="text-xs text-zinc-500">{{ $label }}</dt>
                    <dd class="mt-1 {{ $compact ? 'text-xs' : 'text-sm' }} text-zinc-200">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    @endif

    @if ($showEffects)
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Все эффекты</p>
            @if ($bonusSummaries !== [])
                <x-item-effects :item="$item" :show-duration="false" class="mt-1" />
            @else
                <p class="mt-1 text-xs text-zinc-500">Числовые бонусы не заданы.</p>
            @endif
        </div>
    @endif
</div>
