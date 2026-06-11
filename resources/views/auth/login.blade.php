@extends('layouts.app', ['title' => 'Вход'])

@section('content')
    <div class="mx-auto max-w-md">
        <div class="rounded-md border border-zinc-800 bg-zinc-900 p-6">
            <h1 class="text-2xl font-semibold text-white">Вход</h1>

            <div class="mt-5">
                @include('partials.form-errors')
            </div>

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
                @csrf

                <label class="block">
                    <span class="text-sm text-zinc-300">Email</span>
                    <input
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
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
                        autocomplete="current-password"
                        class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white outline-none focus:border-emerald-400"
                    >
                </label>

                <label class="flex items-center gap-2 text-sm text-zinc-300">
                    <input name="remember" type="checkbox" value="1" @checked(old('remember')) class="h-4 w-4 rounded border-zinc-700 bg-zinc-950">
                    Запомнить вход
                </label>

                <button type="submit" class="w-full rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400">
                    Войти
                </button>
            </form>
        </div>
    </div>
@endsection
