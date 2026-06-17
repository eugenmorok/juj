@props([
    'icon' => null,
    'label' => '',
    'size' => 'md',
])

@php
    $value = is_string($icon) ? trim($icon) : '';
    $labelText = trim((string) $label);
    $sizeClasses = [
        'sm' => 'h-9 w-9 text-xs',
        'md' => 'h-12 w-12 text-sm',
        'lg' => 'h-16 w-16 text-base',
    ][$size] ?? 'h-12 w-12 text-sm';
    $isImage = $value !== '' && preg_match('/^(https?:\/\/|\/|data:image\/|storage\/|images\/|assets\/)/i', $value) === 1;
    $src = $isImage && preg_match('/^(https?:\/\/|\/|data:image\/)/i', $value) === 1 ? $value : ($isImage ? asset($value) : null);
    $fallback = $value !== '' && ! $isImage ? $value : ($labelText !== '' ? mb_substr($labelText, 0, 2) : '?');
@endphp

<span
    {{ $attributes->merge(['class' => $sizeClasses.' inline-flex shrink-0 items-center justify-center overflow-hidden rounded-md border border-emerald-500/40 bg-zinc-950 font-semibold text-emerald-100']) }}
    data-game-icon
    aria-label="{{ $labelText !== '' ? $labelText : 'Иконка' }}"
>
    @if ($src)
        <img src="{{ $src }}" alt="" class="h-full w-full object-cover">
    @else
        <span>{{ mb_substr($fallback, 0, 3) }}</span>
    @endif
</span>
