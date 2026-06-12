@php
    $value = max(0, (float) ($value ?? 0));
    $max = max(1, (float) ($max ?? 1));
    $percent = min(100, max(0, (int) round(($value / $max) * 100)));
    $label = $label ?? null;
    $tone = $tone ?? 'emerald';
    $barClass = match ($tone) {
        'amber' => 'bg-amber-400',
        'red' => 'bg-red-400',
        'sky' => 'bg-sky-400',
        default => 'bg-emerald-400',
    };
@endphp

<div class="space-y-1">
    @if ($label)
        <div class="flex justify-between gap-3 text-xs text-zinc-400">
            <span>{{ $label }}</span>
            <span>{{ (int) $value }}/{{ (int) $max }}</span>
        </div>
    @endif
    <div class="h-2 overflow-hidden rounded-md border border-zinc-800 bg-zinc-950">
        <div class="h-full {{ $barClass }}" style="width: {{ $percent }}%"></div>
    </div>
</div>
