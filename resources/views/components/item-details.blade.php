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

<div {{ $attributes->class(['item-details space-y-3 text-sm']) }}>
    @if ($showDescription && $item->description)
        <p class="item-details__description {{ $compact ? 'text-xs' : 'text-sm' }}">{{ $item->description }}</p>
    @endif

    @if ($hasCombatStats)
        <dl class="item-details__grid item-details__grid--combat grid gap-2 {{ $compact ? 'grid-cols-2' : 'sm:grid-cols-2' }}">
            <div class="item-details__tile item-details__tile--damage rounded-md px-3 py-2">
                <dt class="item-details__label">Урон</dt>
                <dd class="item-details__value mt-1 font-semibold">
                    {{ $damageBonus > 0 ? '+' : '' }}{{ $damageBonus }}
                </dd>
            </div>
            <div class="item-details__tile item-details__tile--defense rounded-md px-3 py-2">
                <dt class="item-details__label">Защита</dt>
                <dd class="item-details__value mt-1 font-semibold">
                    {{ $defenseBonus > 0 ? '+' : '' }}{{ $defenseBonus }}
                </dd>
            </div>
        </dl>
    @endif

    @if ($metaRows !== [])
        <dl class="item-details__grid item-details__grid--meta grid gap-2 {{ $compact ? 'grid-cols-2' : 'sm:grid-cols-2' }}">
            @foreach ($metaRows as [$label, $value])
                <div class="item-details__tile rounded-md px-3 py-2">
                    <dt class="item-details__label">{{ $label }}</dt>
                    <dd class="item-details__value mt-1 {{ $compact ? 'text-xs' : 'text-sm' }}">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    @endif

    @if ($showEffects)
        <div>
            <p class="item-details__effects-title text-xs font-medium uppercase tracking-wide">Все эффекты</p>
            @if ($bonusSummaries !== [])
                <x-item-effects :item="$item" :show-duration="false" class="mt-1" />
            @else
                <p class="item-details__empty mt-1 text-xs">Числовые бонусы не заданы.</p>
            @endif
        </div>
    @endif
</div>
