<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'RPG Arena') }}</title>

        <script>
            try {
                document.documentElement.dataset.theme = localStorage.getItem('rpg-arena-theme') || 'dark';
            } catch {
                document.documentElement.dataset.theme = 'dark';
            }
        </script>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
        @php
            $navItems = [
                ['label' => 'Главная', 'route' => 'dashboard'],
                ['label' => 'Профиль', 'route' => 'profile'],
                ['label' => 'Сущности', 'route' => 'entities.index', 'active' => 'entities.*'],
                ['label' => 'Арена', 'route' => 'arena'],
                ['label' => 'Магазин', 'route' => 'shop'],
                ['label' => 'Инвентарь', 'route' => 'inventory'],
                ['label' => 'Справка', 'route' => 'help'],
            ];
        @endphp

        <div class="min-h-screen">
            <header class="border-b border-zinc-800 bg-zinc-950/95">
                <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <a href="{{ route(Auth::check() ? 'dashboard' : 'home') }}" class="flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-md bg-emerald-500 text-sm font-bold text-zinc-950">RA</span>
                            <span class="text-lg font-semibold text-white">RPG Arena</span>
                        </a>

                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            <button
                                type="button"
                                data-theme-toggle
                                class="theme-toggle inline-flex items-center gap-2 rounded-md px-3 py-2"
                                aria-pressed="false"
                            >
                                <span class="theme-toggle-dot h-2.5 w-2.5 rounded-full"></span>
                                <span data-theme-toggle-label>Тёмная</span>
                            </button>

                            @auth
                                <span class="rounded-md border border-zinc-800 px-3 py-2 text-zinc-300">
                                    {{ Auth::user()->name }}
                                </span>

                                @if (Auth::user()->is_admin)
                                    <a href="{{ route('filament.admin.pages.dashboard') }}" class="rounded-md border border-amber-500/50 px-3 py-2 text-amber-200 hover:bg-amber-500/10">
                                        Админ
                                    </a>
                                @endif

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-zinc-700 px-3 py-2 text-zinc-200 hover:bg-zinc-900">
                                        Выйти
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="rounded-md border border-zinc-700 px-3 py-2 text-zinc-200 hover:bg-zinc-900">
                                    Войти
                                </a>
                                <a href="{{ route('register') }}" class="rounded-md bg-emerald-500 px-3 py-2 font-medium text-zinc-950 hover:bg-emerald-400">
                                    Регистрация
                                </a>
                            @endauth
                        </div>
                    </div>

                    @auth
                        <nav class="flex gap-2 overflow-x-auto pb-1 text-sm">
                            @foreach ($navItems as $item)
                                <a
                                    href="{{ route($item['route']) }}"
                                    class="whitespace-nowrap rounded-md px-3 py-2 {{ request()->routeIs($item['active'] ?? $item['route']) ? 'bg-emerald-500 text-zinc-950' : 'text-zinc-300 hover:bg-zinc-900 hover:text-white' }}"
                                >
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </nav>
                    @endauth
                </div>
            </header>

            <main
                class="{{ ($wide ?? false) ? 'mx-auto w-[99%] max-w-none px-2 sm:px-3' : 'mx-auto max-w-7xl px-4 sm:px-6 lg:px-8' }} py-8"
                @if (isset($contentWidth)) style="width: {{ $contentWidth }}; max-width: none;" @endif
            >
                @if (session('status'))
                    <div class="mb-6 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                        {{ session('status') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </body>
</html>
