@php
    $ownCreatureId = $ownParticipant?->creature_id;
@endphp

<section class="battle-events-card rounded-md border border-zinc-800 bg-zinc-900">
    <div class="battle-events-card__header">
        <h2>Лог боя</h2>
        <span>новые сверху</span>
    </div>
    <div class="battle-events-card__scroll">
        @foreach ($battle->events->sortByDesc('id') as $event)
            @include('game.battles.partials.event-row', [
                'event' => $event,
                'ownCreatureId' => $ownCreatureId,
            ])
        @endforeach
    </div>
</section>
