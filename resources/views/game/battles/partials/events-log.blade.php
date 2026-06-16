<section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
    <h2 class="text-xl font-semibold text-white">Р›РѕРі Р±РѕСЏ</h2>
    <div class="mt-4 space-y-2">
        @foreach ($battle->events as $event)
            @include('game.battles.partials.event-row', ['event' => $event])
        @endforeach
    </div>
</section>
