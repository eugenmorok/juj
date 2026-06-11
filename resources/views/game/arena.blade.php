@extends('layouts.app', ['title' => 'Арена'])

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium uppercase text-emerald-300">Матчи</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Арена</h1>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-6">
                <h2 class="font-semibold text-white">Быстрый бой</h2>
                <p class="mt-2 text-sm text-zinc-400">Нет доступной сущности для матча.</p>
                <button type="button" class="mt-5 rounded-md border border-zinc-700 px-4 py-2 text-zinc-200">
                    Найти бой
                </button>
            </div>
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-6">
                <h2 class="font-semibold text-white">Боты</h2>
                <p class="mt-2 text-sm text-zinc-400">PvE-очередь без активной сущности.</p>
                <button type="button" class="mt-5 rounded-md border border-zinc-700 px-4 py-2 text-zinc-200">
                    PvE бой
                </button>
            </div>
        </div>
    </div>
@endsection
