@extends('layouts.app', ['title' => 'Магазин'])

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium uppercase text-emerald-300">Экономика</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Магазин</h1>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            @foreach (['Обычный', 'Редкий', 'Элитный', 'Уникальный'] as $rarity)
                <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                    <div class="text-sm text-zinc-400">Ранг</div>
                    <div class="mt-2 font-semibold text-white">{{ $rarity }}</div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
