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
    $sceneConfig = [
        'background_url' => \App\Support\BattlePresentation::arenaBackground(),
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
