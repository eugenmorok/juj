@props([
    'item',
    'compact' => false,
    'showDuration' => true,
])

@php
    $bonuses = collect($item->bonuses ?? [])
        ->filter(fn ($value) => is_numeric($value) && (float) $value !== 0.0);
@endphp

@if ($bonuses->isNotEmpty() || ($showDuration && $item->duration_type))
    <div {{ $attributes->class(['flex flex-wrap gap-1.5', 'mt-2']) }}>
        @foreach ($bonuses as $bonus => $value)
            <span class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-1 text-xs text-emerald-100">
                {{ \App\Models\Item::bonusLabel($bonus) }}
                {{ (float) $value > 0 ? '+' : '' }}{{ $value }}{{ str_contains($bonus, 'chance') ? '%' : '' }}
            </span>
        @endforeach

        @if ($showDuration && $item->duration_type)
            <span class="rounded-md border border-zinc-700 px-2 py-1 text-xs text-zinc-300">
                {{ \App\Models\Item::DURATIONS[$item->duration_type] ?? $item->duration_type }}
                @if ($item->isConsumable())
                    · {{ $item->initialUses() }} исп.
                @endif
            </span>
        @endif
    </div>
@endif
