@extends('layouts.app', ['title' => 'Личный кабинет'])

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium uppercase text-emerald-300">Личный кабинет</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">{{ $user->name }}</h1>
        </div>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="text-sm text-zinc-400">Уровень</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $user->level }}</div>
            </div>
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="text-sm text-zinc-400">Опыт</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $user->xp }}</div>
            </div>
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="text-sm text-zinc-400">Токены</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $user->tokens }}</div>
            </div>
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="text-sm text-zinc-400">Инвентарь</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $user->inventoryCapacity() }}</div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <a href="{{ route('entities.index') }}" class="rounded-md border border-zinc-800 bg-zinc-900 p-5 hover:border-emerald-500/60">
                <h2 class="font-semibold text-white">Сущности</h2>
                <p class="mt-2 text-sm text-zinc-400">Пул бойцов игрока.</p>
            </a>
            <a href="{{ route('arena') }}" class="rounded-md border border-zinc-800 bg-zinc-900 p-5 hover:border-emerald-500/60">
                <h2 class="font-semibold text-white">Арена</h2>
                <p class="mt-2 text-sm text-zinc-400">Матчи против игроков и ботов.</p>
            </a>
            <a href="{{ route('shop') }}" class="rounded-md border border-zinc-800 bg-zinc-900 p-5 hover:border-emerald-500/60">
                <h2 class="font-semibold text-white">Магазин</h2>
                <p class="mt-2 text-sm text-zinc-400">Предметы, экипировка и зелья.</p>
            </a>
        </section>
    </div>
@endsection
