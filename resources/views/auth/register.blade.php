@extends('layouts.app', ['title' => 'Регистрация'])

@section('content')
    <div class="mx-auto max-w-md">
        <div class="rounded-md border border-zinc-800 bg-zinc-900 p-6">
            <h1 class="text-2xl font-semibold text-white">Регистрация</h1>

            <div class="mt-5">
                @include('partials.form-errors')
            </div>

            <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-5">
                @csrf

                <label class="block">
                    <span class="text-sm text-zinc-300">Имя игрока</span>
                    <input
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        required
                        autofocus
                        autocomplete="name"
                        class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white outline-none focus:border-emerald-400"
                    >
                </label>

                <label class="block">
                    <span class="text-sm text-zinc-300">Email</span>
                    <input
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white outline-none focus:border-emerald-400"
                    >
                </label>

                <label class="block">
                    <span class="text-sm text-zinc-300">Пароль</span>
                    <input
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white outline-none focus:border-emerald-400"
                    >
                </label>

                <label class="block">
                    <span class="text-sm text-zinc-300">Повтор пароля</span>
                    <input
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white outline-none focus:border-emerald-400"
                    >
                </label>

                <button type="submit" class="w-full rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400">
                    Создать аккаунт
                </button>
            </form>
        </div>
    </div>
@endsection
