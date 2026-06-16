@php
    $resultLabels = [
        'win' => 'РџРѕР±РµРґР°',
        'loss' => 'РџРѕСЂР°Р¶РµРЅРёРµ',
        'draw' => 'РќРёС‡СЊСЏ',
    ];
@endphp

<section class="grid gap-4 lg:grid-cols-2">
    @foreach ($battle->participants as $participant)
        @php
            $resultLabel = $participant->result ? ($resultLabels[$participant->result] ?? $participant->result) : 'Р’ Р±РѕСЋ';
            $resultTone = match ($participant->result) {
                'win' => 'border-emerald-500/40 text-emerald-100',
                'loss' => 'border-rose-500/40 text-rose-100',
                'draw' => 'border-amber-500/40 text-amber-100',
                default => 'border-sky-500/40 text-sky-100',
            };
        @endphp
        <article class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-white">{{ $participant->creature->name }}</h2>
                    <p class="mt-1 text-sm text-zinc-400">
                        {{ $participant->creature->user->name }} / СѓСЂ. {{ $participant->level_before }} -> {{ $participant->level_after }}
                    </p>
                </div>
                <span class="rounded-md border px-3 py-1 text-sm {{ $resultTone }}">
                    {{ $resultLabel }}
                </span>
            </div>

            <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                    <dt class="text-xs text-zinc-500">Power score</dt>
                    <dd class="mt-1 text-zinc-200">{{ $participant->power_score_before }}</dd>
                </div>
                <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                    <dt class="text-xs text-zinc-500">HP</dt>
                    <dd class="mt-1 text-zinc-200">{{ $participant->hp_after }}/{{ $participant->hp_before }}</dd>
                </div>
                <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                    <dt class="text-xs text-zinc-500">XP</dt>
                    <dd class="mt-1 text-zinc-200">+{{ $participant->reward_xp }}</dd>
                </div>
                <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                    <dt class="text-xs text-zinc-500">РўРѕРєРµРЅС‹</dt>
                    <dd class="mt-1 text-zinc-200">+{{ $participant->reward_tokens }}</dd>
                </div>
            </dl>

            <p class="mt-4 text-sm text-zinc-400">
                РћС‡РєРё СЂР°Р·РІРёС‚РёСЏ: +{{ $participant->reward_development_points }}.
                РњРЅРѕР¶РёС‚РµР»СЊ РЅР°РіСЂР°Рґ: x{{ $participant->reward_multiplier }}.
            </p>
            <div class="mt-4">
                @include('partials.progress-bar', [
                    'value' => $participant->hp_after,
                    'max' => max(1, $participant->hp_before),
                    'label' => 'HP',
                ])
            </div>
        </article>
    @endforeach
</section>
