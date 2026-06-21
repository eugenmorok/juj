@php
    $resultLabels = [
        'win' => 'Победа',
        'loss' => 'Поражение',
        'draw' => 'Ничья',
    ];
@endphp

<section class="battle-participants-strip">
    @foreach ($battle->participants as $participant)
        @php
            $combatStats = $participant->creature->effectiveCombatStats();
            $resultLabel = $participant->result ? ($resultLabels[$participant->result] ?? $participant->result) : 'В бою';
            $resultTone = match ($participant->result) {
                'win' => 'border-emerald-500/40 text-emerald-100',
                'loss' => 'border-rose-500/40 text-rose-100',
                'draw' => 'border-amber-500/40 text-amber-100',
                default => 'border-sky-500/40 text-sky-100',
            };
        @endphp
        <article class="battle-participant-card">
            <div class="battle-participant-card__identity">
                <div class="flex min-w-0 items-center gap-3">
                    <x-game-icon
                        :icon="$participant->creature->species?->portrait_image ?? $participant->creature->species?->icon ?? $participant->creature->type?->icon"
                        :label="$participant->creature->name"
                        size="md"
                    />
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold text-white">{{ $participant->creature->name }}</h2>
                        <p class="text-xs text-zinc-400">
                            {{ $participant->creature->user->name }} · ур. {{ $participant->level_before }}
                        </p>
                        <p class="text-[11px] text-zinc-500">
                            {{ $participant->creature->type?->name }} / {{ $participant->creature->species?->name }}
                        </p>
                    </div>
                </div>
                <span class="rounded-md border px-3 py-1 text-sm {{ $resultTone }}">
                    {{ $resultLabel }}
                </span>
            </div>
            <div class="battle-participant-card__metrics">
                <span><b>HP</b> {{ $participant->hp_after }}/{{ $participant->hp_before }}</span>
                <span><b>PS</b> {{ $participant->power_score_before }}</span>
                <span><b>Урон</b> {{ $combatStats['damage']['total'] }}</span>
                <span><b>Защита</b> {{ $combatStats['defense']['total'] }}</span>
                @if ($participant->result)
                    <span><b>XP</b> +{{ $participant->reward_xp }}</span>
                    <span><b>Монеты</b> +{{ $participant->reward_tokens }}</span>
                @endif
            </div>
        </article>
    @endforeach
</section>
