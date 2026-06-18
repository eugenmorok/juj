<section class="battle-events-card rounded-md border border-zinc-800 bg-zinc-900 p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-white">Лог боя</h2>
        <span class="rounded-md border border-zinc-700 px-3 py-1 text-sm text-zinc-300">
            Новые сверху
        </span>
    </div>
    <div class="battle-events-card__scroll mt-3 space-y-2 overflow-y-auto pr-1">
        @foreach ($battle->events->sortByDesc('id') as $event)
            @include('game.battles.partials.event-row', ['event' => $event])
        @endforeach
    </div>
</section>
