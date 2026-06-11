@extends('layouts.app', ['title' => 'Профиль игрока'])

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium uppercase text-emerald-300">Профиль игрока</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">{{ $user->name }}</h1>
        </div>

        <div class="rounded-md border border-zinc-800 bg-zinc-900">
            <dl class="divide-y divide-zinc-800">
                <div class="grid gap-2 px-5 py-4 sm:grid-cols-3">
                    <dt class="text-sm text-zinc-400">Email</dt>
                    <dd class="sm:col-span-2">{{ $user->email }}</dd>
                </div>
                <div class="grid gap-2 px-5 py-4 sm:grid-cols-3">
                    <dt class="text-sm text-zinc-400">Роль</dt>
                    <dd class="sm:col-span-2">{{ $user->is_admin ? 'Администратор' : 'Игрок' }}</dd>
                </div>
                <div class="grid gap-2 px-5 py-4 sm:grid-cols-3">
                    <dt class="text-sm text-zinc-400">Бот</dt>
                    <dd class="sm:col-span-2">{{ $user->is_bot ? 'Да' : 'Нет' }}</dd>
                </div>
            </dl>
        </div>
    </div>
@endsection
