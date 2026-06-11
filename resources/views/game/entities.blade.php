@extends('layouts.app', ['title' => 'Сущности'])

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Пул сущностей</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Сущности</h1>
            </div>
            <button type="button" class="rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400">
                Создать сущность
            </button>
        </div>

        <div class="rounded-md border border-zinc-800 bg-zinc-900 p-8 text-center">
            <h2 class="text-lg font-semibold text-white">Пул пуст</h2>
            <p class="mt-2 text-sm text-zinc-400">Сущности не созданы.</p>
        </div>
    </div>
@endsection
