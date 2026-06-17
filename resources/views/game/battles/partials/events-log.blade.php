<section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-white">Лог боя</h2>
        <span class="rounded-md border border-zinc-700 px-3 py-1 text-sm text-zinc-300">
            Новые сверху
        </span>
    </div>
    <div class="mt-4 max-h-[38rem] space-y-2 overflow-y-auto pr-1">
        @foreach ($battle->events->sortByDesc('id') as $event)
            @include('game.battles.partials.event-row', ['event' => $event])
        @endforeach
    </div>
</section>
