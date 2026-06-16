@extends('layouts.app', ['title' => 'Справка'])

@section('content')
    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Правила</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Справка по арене</h1>
                <p class="mt-1 max-w-3xl text-sm text-zinc-400">
                    Короткая памятка по SPECIAL, созданию сущности, боям, наградам, предметам и инвентарю.
                </p>
            </div>
            <a href="{{ route('arena') }}" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-900">
                К арене
            </a>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'S', 'title' => 'Strength', 'text' => 'Увеличивает базовый урон атаки.'],
                ['label' => 'P', 'title' => 'Perception', 'text' => 'Помогает попадать и участвует в инициативе.'],
                ['label' => 'E', 'title' => 'Endurance', 'text' => 'Дает запас HP и место в инвентаре сущности.'],
                ['label' => 'C', 'title' => 'Charisma', 'text' => 'Заложена для социальных и лидерских эффектов.'],
                ['label' => 'I', 'title' => 'Intelligence', 'text' => 'Открывает аналитические навыки и часть боевых эффектов.'],
                ['label' => 'A', 'title' => 'Agility', 'text' => 'Повышает инициативу и снижает шанс попадания по сущности.'],
                ['label' => 'L', 'title' => 'Luck', 'text' => 'Влияет на критические удары и редкие эффекты.'],
                ['label' => 'PS', 'title' => 'Power Score', 'text' => 'Суммарная сила по SPECIAL, уровню, навыкам и экипировке.'],
            ] as $rule)
                <article class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                    <div class="flex h-10 w-10 items-center justify-center rounded-md border border-emerald-500/40 bg-zinc-950 text-sm font-semibold text-emerald-100">
                        {{ $rule['label'] }}
                    </div>
                    <h2 class="mt-4 font-semibold text-white">{{ $rule['title'] }}</h2>
                    <p class="mt-2 text-sm text-zinc-400">{{ $rule['text'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <article class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Создание сущности</h2>
                <div class="mt-4 space-y-3 text-sm text-zinc-300">
                    <p>Вид задает базовые SPECIAL. Ниже этой базы опускаться нельзя.</p>
                    <p>Поверх базы доступно {{ \App\Models\Creature::CREATION_POINTS }} очков на характеристики и стартовые навыки.</p>
                    <p>На 1 уровне нельзя поднять характеристику выше {{ \App\Models\Creature::STARTER_SPECIAL_CAP }}.</p>
                    <p>Стартовый лимит навыков: {{ \App\Models\Creature::BASE_SKILL_LIMIT }}. На следующих уровнях лимит растет.</p>
                </div>
            </article>

            <article class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Арена и награды</h2>
                <div class="mt-4 space-y-3 text-sm text-zinc-300">
                    <p>Система подбирает соперников по уровню и power score. Если подходящих ботов мало, она создает новых.</p>
                    <p>Игрок может бросить вызов: бот принимает сразу, реальный игрок отвечает в течение 2 минут.</p>
                    <p>После принятия вызова открывается пошаговый бой: на каждом шаге игрок выбирает зоны атаки и защиты, а также может применить доступный расходник.</p>
                    <p>На выбор шага отводится 6 секунд. Если участник не успел, система подставляет автотактику.</p>
                    <p>Страница боя обновляется сама через polling, а кнопка Replay открывает таймлайн раундов, тактик и событий.</p>
                    <p>За победу, ничью и поражение начисляются XP, очки развития и токены по активным настройкам баланса.</p>
                    <p>Если соперник намного слабее или бой часто повторяется с тем же противником, награды могут снижаться.</p>
                </div>
            </article>

            <article class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Предметы и редкость</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach (array_keys(\App\Models\Item::RARITIES) as $rarity)
                        @include('partials.rarity-badge', ['rarity' => $rarity])
                    @endforeach
                </div>
                <div class="mt-4 space-y-3 text-sm text-zinc-300">
                    <p>Предмет может быть экипировкой, модулем, артефактом, зельем, расходником или услугой.</p>
                    <p>Некоторые предметы требуют уровень, тип сущности, вид сущности или свободный слот экипировки.</p>
                    <p>Зелья и расходники применяются из общего инвентаря или инвентаря сущности и могут лечить, повышать SPECIAL или max HP.</p>
                    <p>Уникальный предмет нельзя купить второй раз, пока он уже есть у игрока.</p>
                </div>
            </article>

            <article class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Инвентарь</h2>
                <div class="mt-4 space-y-3 text-sm text-zinc-300">
                    <p>У игрока есть общий инвентарь, а у каждой сущности есть собственный небольшой инвентарь.</p>
                    <p>Предметы можно переносить между общим инвентарем и сущностью, если есть свободные ячейки.</p>
                    <p>Во время боя перенос и смена экипировки заблокированы.</p>
                    <p>Дополнительные ячейки общего инвентаря покупаются в магазине за токены.</p>
                </div>
            </article>
        </section>
    </div>
@endsection
