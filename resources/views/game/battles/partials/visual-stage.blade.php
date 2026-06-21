@php
    $sceneParticipants = $battle->participants
        ->map(fn (\App\Models\BattleParticipant $participant): array => \App\Support\BattlePresentation::participant($participant))
        ->values();
    $sceneEvents = $battle->events
        ->sortByDesc('id')
        ->take(12)
        ->sortBy('id')
        ->map(fn (\App\Models\BattleEvent $event): array => \App\Support\BattlePresentation::event($event))
        ->values();
    $sceneArena = \App\Support\BattlePresentation::arena($battle);
    $specialLabels = \App\Models\Creature::SPECIAL_LABELS;
    $sceneConfig = [
        ...$sceneArena,
        'participants' => $sceneParticipants,
        'events' => $sceneEvents,
    ];
@endphp

<section
    class="battle-visual-stage"
    data-battle-visualizer
    data-battle-latest-event-id="{{ $battle->events->max('id') ?? 0 }}"
    aria-label="Боевая сцена"
>
    <div class="battle-visual-stage__toolbar">
        <span class="battle-visual-stage__round">
            Раунд <strong data-battle-scene-round>{{ max(1, $battle->current_round) }}</strong>
        </span>
        <div class="battle-visual-stage__controls">
            <button type="button" data-battle-motion-toggle title="Пауза или продолжение анимации" aria-label="Пауза или продолжение анимации">Ⅱ</button>
            <button type="button" data-battle-animation-skip title="Пропустить текущую анимацию" aria-label="Пропустить текущую анимацию">≫</button>
        </div>
    </div>

    <div class="battle-visual-stage__arena-badge">
        <strong>{{ $sceneArena['name'] }}</strong>
        @if ($sceneArena['effects'] !== [])
            <span>
                @foreach ($sceneArena['effects'] as $attribute => $modifier)
                    {{ $specialLabels[$attribute] ?? strtoupper(substr($attribute, 0, 1)) }}{{ sprintf('%+d', $modifier) }}@if (! $loop->last) · @endif
                @endforeach
            </span>
        @else
            <span>Без модификаторов</span>
        @endif
    </div>

    <div class="battle-visual-stage__hud">
        @foreach ($sceneParticipants as $index => $participant)
            <article
                class="battle-fighter-hud battle-fighter-hud--{{ $index === 0 ? 'left' : 'right' }}"
                data-battle-fighter-hud="{{ $participant['creature_id'] }}"
            >
                <img src="{{ $participant['portrait_url'] }}" alt="">
                <div class="battle-fighter-hud__body">
                    <div class="battle-fighter-hud__identity">
                        <strong>{{ $participant['creature_name'] }}</strong>
                        <span>ур. {{ $participant['level_before'] }} · {{ $participant['power_score_before'] }} PS</span>
                    </div>
                    <div class="battle-fighter-hud__hp">
                        <span
                            data-battle-hud-hp-fill
                            style="width: {{ min(100, max(0, ($participant['hp_after'] / max(1, $participant['hp_before'])) * 100)) }}%"
                        ></span>
                        <b data-battle-hud-hp-text>{{ $participant['hp_after'] }}/{{ $participant['hp_before'] }}</b>
                    </div>
                    <div class="battle-fighter-hud__special">
                        <span title="Урон"><b>УРН</b>{{ $participant['combat']['damage'] ?? 0 }}</span>
                        <span title="Защита"><b>ЗАЩ</b>{{ $participant['combat']['defense'] ?? 0 }}</span>
                        @foreach ($participant['special'] as $attribute => $value)
                            <span title="{{ $attribute }}"><b>{{ $specialLabels[$attribute] ?? strtoupper(substr($attribute, 0, 1)) }}</b>{{ $value }}</span>
                        @endforeach
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <div class="battle-visual-stage__viewport" data-battle-canvas>
        <div class="battle-visual-stage__fallback" data-battle-canvas-fallback>
            <img
                class="battle-visual-stage__background"
                src="{{ $sceneConfig['background_url'] }}"
                alt=""
            >
            @foreach ($sceneParticipants as $index => $participant)
                <figure class="battle-visual-stage__fighter battle-visual-stage__fighter--{{ $index === 0 ? 'left' : 'right' }}">
                    <img src="{{ $participant['image_url'] }}" alt="{{ $participant['creature_name'] }}">
                    <figcaption>{{ $participant['creature_name'] }}</figcaption>
                </figure>
            @endforeach
        </div>
    </div>

    <div class="battle-visual-stage__announcer" data-battle-announcer aria-live="polite"></div>

    <script type="application/json" data-battle-scene-config>{!! json_encode($sceneConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</section>
