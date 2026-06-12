@php
    $itemModel = $item ?? null;
    $rarity = $rarity ?? $itemModel?->rarity ?? 'common';
    $label = \App\Models\Item::RARITIES[$rarity] ?? ucfirst((string) $rarity);
    $classes = match ($rarity) {
        'rare' => 'border-sky-400/60 bg-sky-400/10 text-sky-100',
        'elite' => 'border-violet-400/60 bg-violet-400/10 text-violet-100',
        'unique' => 'border-amber-400/70 bg-amber-400/10 text-amber-100',
        default => 'border-zinc-700 bg-zinc-950 text-zinc-300',
    };
@endphp

<span class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium {{ $classes }}">
    {{ $label }}
</span>
