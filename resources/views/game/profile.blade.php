@extends('layouts.app', ['title' => 'Профиль игрока'])

@section('content')
    @php
        $xpToNextLevel = \App\Services\PlayerProgressService::xpToNextLevel($user->level);
        $support = $user->battleSupportBonus();
        $doctrineAttributes = $user->doctrineAttributes();
        $ownedPerks = $user->playerPerks();
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

        <section class="grid gap-4 md:grid-cols-6">
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
                <div class="text-sm text-zinc-400">Очки доктрины</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $user->doctrine_points }}</div>
            </div>
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <div class="text-sm text-zinc-400">Очки перков</div>
                <div class="mt-2 text-3xl font-semibold text-white">{{ $user->perk_points }}</div>
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
                    дает скидку в магазине, открывает очки доктрины и командные бонусы всем твоим сущностям в бою.
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
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-white">Доктрина игрока</h2>
                    <p class="mt-2 text-sm text-zinc-400">
                        Доктрина — это стиль управления всеми сущностями. Очки выдаются за уровни игрока:
                        по одному за уровень и дополнительное очко на каждом пятом уровне.
                    </p>
                </div>
                <div class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-100">
                    Свободно: <strong>{{ $user->doctrine_points }}</strong>
                </div>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                @foreach (\App\Models\User::DOCTRINE_ATTRIBUTES as $attribute => $meta)
                    @php
                        $value = $doctrineAttributes[$attribute] ?? 0;
                    @endphp
                    <article class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-white">{{ $meta['label'] }}</div>
                                <div class="text-xs uppercase text-zinc-500">{{ $meta['short'] }}</div>
                            </div>
                            <div class="text-2xl font-semibold text-emerald-100">{{ $value }}</div>
                        </div>
                        <p class="mt-3 min-h-12 text-xs text-zinc-400">{{ $meta['description'] }}</p>
                        <form method="POST" action="{{ route('profile.doctrine.increase', $attribute) }}" class="mt-4">
                            @csrf
                            <button
                                type="submit"
                                class="w-full rounded-md border border-emerald-500/40 px-3 py-2 text-sm font-medium text-emerald-100 hover:bg-emerald-500/10 disabled:cursor-not-allowed disabled:border-zinc-800 disabled:text-zinc-600"
                                @disabled($user->doctrine_points < 1 || $value >= \App\Models\User::MAX_DOCTRINE_ATTRIBUTE)
                            >
                                Вложить очко
                            </button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-white">Перки игрока</h2>
                    <p class="mt-2 text-sm text-zinc-400">
                        Перки — редкие аккаунтные умения. Очки перков выдаются на уровнях 3, 5, 8, 11, 14, 17, 20 и дальше каждые 4 уровня.
                    </p>
                </div>
                <div class="rounded-md border border-sky-500/30 bg-sky-500/10 px-3 py-2 text-sm text-sky-100">
                    Свободно: <strong>{{ $user->perk_points }}</strong>
                </div>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                @foreach (\App\Models\User::PLAYER_PERKS as $perk => $meta)
                    @php
                        $branch = $meta['branch'];
                        $branchLabel = \App\Models\User::DOCTRINE_ATTRIBUTES[$branch]['label'] ?? $branch;
                        $branchValue = $doctrineAttributes[$branch] ?? 0;
                        $owned = in_array($perk, $ownedPerks, true);
                        $available = $user->canBuyPlayerPerk($perk);
                    @endphp
                    <article class="rounded-md border {{ $owned ? 'border-sky-500/40 bg-sky-500/10' : 'border-zinc-800 bg-zinc-950' }} p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-white">{{ $meta['label'] }}</div>
                                <div class="mt-1 text-xs text-zinc-500">{{ $branchLabel }} {{ $branchValue }}/{{ $meta['required_doctrine'] }}, ур. {{ $meta['required_level'] }}</div>
                            </div>
                            @if ($owned)
                                <span class="rounded-md border border-sky-500/40 px-2 py-1 text-xs text-sky-100">Взят</span>
                            @endif
                        </div>
                        <p class="mt-3 min-h-12 text-xs text-zinc-400">{{ $meta['description'] }}</p>
                        <form method="POST" action="{{ route('profile.perks.buy', $perk) }}" class="mt-4">
                            @csrf
                            <button
                                type="submit"
                                class="w-full rounded-md border border-sky-500/40 px-3 py-2 text-sm font-medium text-sky-100 hover:bg-sky-500/10 disabled:cursor-not-allowed disabled:border-zinc-800 disabled:text-zinc-600"
                                @disabled(! $available)
                            >
                                Получить перк
                            </button>
                        </form>
                    </article>
                @endforeach
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
                        <span class="rounded-md border border-zinc-700 px-2 py-1 text-zinc-200">A +{{ $support['agility'] }}</span>
                        <span class="rounded-md border border-zinc-700 px-2 py-1 text-zinc-200">E +{{ $support['endurance'] }}</span>
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
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                    <div class="text-sm text-zinc-400">Инженерия предметов</div>
                    <div class="mt-1 text-xl font-semibold text-white">+{{ $user->equipmentCombatBonusPercent() }}%</div>
                    <p class="mt-1 text-xs text-zinc-500">К прямым бонусам Урона и Защиты от экипировки.</p>
                </div>
                <div class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                    <div class="text-sm text-zinc-400">Селекция</div>
                    <div class="mt-1 text-xl font-semibold text-white">+{{ $user->creationPointRewardBonusPercent() }}%</div>
                    <p class="mt-1 text-xs text-zinc-500">К шансу и размеру добычи очков создания за победы.</p>
                </div>
                <div class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                    <div class="text-sm text-zinc-400">Торговля</div>
                    <div class="mt-1 text-xl font-semibold text-white">+{{ $user->tokenRewardBonusPercent() }}%</div>
                    <p class="mt-1 text-xs text-zinc-500">К жетонам за бой; скидка магазина уже показана выше.</p>
                </div>
            </div>
        </section>
    </div>
@endsection
