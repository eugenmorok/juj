@extends('layouts.app', ['title' => 'RPG Arena'])

@section('content')
    <section class="grid gap-8 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
        <div>
            <p class="text-sm font-medium uppercase text-emerald-300">Browser RPG Arena</p>
            <h1 class="mt-3 max-w-3xl text-4xl font-semibold text-white sm:text-5xl">RPG Arena</h1>
            <p class="mt-5 max-w-2xl text-lg text-zinc-300">
                Тактическая арена сущностей с характеристиками SPECIAL, экипировкой, инвентарем и прогрессией игрока.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-md bg-emerald-500 px-5 py-3 font-medium text-zinc-950 hover:bg-emerald-400">
                        Личный кабинет
                    </a>
                @else
                    <a href="{{ route('register') }}" class="rounded-md bg-emerald-500 px-5 py-3 font-medium text-zinc-950 hover:bg-emerald-400">
                        Создать игрока
                    </a>
                    <a href="{{ route('login') }}" class="rounded-md border border-zinc-700 px-5 py-3 text-zinc-100 hover:bg-zinc-900">
                        Войти
                    </a>
                @endauth
            </div>
        </div>

        <div class="rounded-md border border-zinc-800 bg-zinc-900 p-6">
            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-md bg-zinc-950 p-4">
                    <div class="text-2xl font-semibold text-white">100</div>
                    <div class="mt-1 text-sm text-zinc-400">очков создания</div>
                </div>
                <div class="rounded-md bg-zinc-950 p-4">
                    <div class="text-2xl font-semibold text-white">SPECIAL</div>
                    <div class="mt-1 text-sm text-zinc-400">характеристики</div>
                </div>
                <div class="rounded-md bg-zinc-950 p-4">
                    <div class="text-2xl font-semibold text-white">10</div>
                    <div class="mt-1 text-sm text-zinc-400">слотов экипировки</div>
                </div>
                <div class="rounded-md bg-zinc-950 p-4">
                    <div class="text-2xl font-semibold text-white">PvE/PvP</div>
                    <div class="mt-1 text-sm text-zinc-400">режимы арены</div>
                </div>
            </div>
        </div>
    </section>
@endsection
