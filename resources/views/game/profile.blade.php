@extends('layouts.app', ['title' => 'Профиль игрока'])

@section('content')
    @php
        $xpToNextLevel = \App\Services\PlayerProgressService::xpToNextLevel($user->level);
        $support = $user->battleSupportBonus();
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Профиль игрока</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">{{ $user->name }}</h1>
                <p class="mt-1 text-sm text-zinc-400">{{ $user->email }}</p>
            </div>
            <span class="rounded-md border border-zinc-800 px-3 py-2 text-sm text-zinc-300">
                {{ $user->is_admin ? 'Администратор' : 'Игрок' }}
            </span>
        </div>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="text-sm text-zinc-400">Уровень</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $user->level }}</div>
            </div>
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="text-sm text-zinc-400">XP игрока</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $user->xp }}</div>
            </div>
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="text-sm text-zinc-400">Очки создания</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $user->creature_creation_points }}</div>
            </div>
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="text-sm text-zinc-400">Скидка магазина</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $user->shopDiscountPercent() }}%</div>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                @include('partials.progress-bar', [
                    'value' => $user->xp,
                    'max' => $xpToNextLevel,
                    'label' => 'Опыт до следующего уровня игрока',
                    'tone' => 'sky',
                ])
                <p class="mt-3 text-sm text-zinc-400">
                    Следующий уровень: {{ $xpToNextLevel }} XP. Уровень игрока повышает вместимость общего инвентаря,
                    дает скидку в магазине и командные бонусы всем твоим сущностям в бою.
                </p>
            </div>

            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <h2 class="font-semibold text-white">Конвертация XP</h2>
                <p class="mt-2 text-sm text-zinc-400">
                    Курс: {{ \App\Models\User::CREATURE_CREATION_POINT_XP_COST }} XP игрока за 1 очко создания.
                    Для новой сущности нужно {{ \App\Models\User::CREATURE_CREATION_COST }} очков создания.
                </p>
                <form method="POST" action="{{ route('profile.creation-points.convert') }}" class="mt-4 flex flex-wrap gap-3">
                    @csrf
                    <input
                        type="number"
                        name="points"
                        min="1"
                        max="100"
                        value="10"
                        class="w-32 rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100"
                    >
                    <button type="submit" class="rounded-md bg-emerald-500 px-4 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400">
                        Конвертировать
                    </button>
                </form>
            </div>
        </section>

        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            <h2 class="font-semibold text-white">Преимущества уровня игрока</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                    <div class="text-sm text-zinc-400">Общий инвентарь</div>
                    <div class="mt-1 text-xl font-semibold text-white">{{ $user->inventoryCapacity() }} ячеек</div>
                </div>
                <div class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                    <div class="text-sm text-zinc-400">Тактическая поддержка</div>
                    <div class="mt-2 flex flex-wrap gap-2 text-sm">
                        <span class="rounded-md border border-zinc-700 px-2 py-1 text-zinc-200">P +{{ $support['perception'] }}</span>
                        <span class="rounded-md border border-zinc-700 px-2 py-1 text-zinc-200">C +{{ $support['charisma'] }}</span>
                        <span class="rounded-md border border-zinc-700 px-2 py-1 text-zinc-200">I +{{ $support['intelligence'] }}</span>
                    </div>
                </div>
                <div class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                    <div class="text-sm text-zinc-400">Новая сущность</div>
                    <div class="mt-1 text-xl font-semibold text-white">
                        {{ $user->canCreateCreature() ? 'Доступна' : 'Нужно еще '.max(0, \App\Models\User::CREATURE_CREATION_COST - $user->creature_creation_points) }}
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
