@php
    use App\Models\EquipmentSlot;
    use App\Support\MediaUrl;
    use Illuminate\Support\Str;

    $slots = EquipmentSlot::query()
        ->active()
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get();

    $shortLabels = [
        'head' => 'Г',
        'body' => 'К',
        'front-limbs' => 'П',
        'rear-limbs' => 'З',
        'primary-weapon' => 'О',
        'secondary-weapon' => 'Д',
        'defense' => 'Щ',
        'neural' => 'Н',
        'artifact' => 'А',
        'accessory' => 'Ч',
    ];

    $sideLabels = [
        'left' => 'Левая сторона',
        'right' => 'Правая сторона',
        'challenger' => 'Инициатор',
        'defender' => 'Соперник',
    ];
@endphp

<section class="battle-equipment-maps rounded-md border border-zinc-800 bg-zinc-900 p-3">
    <div class="battle-equipment-maps__header">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-emerald-300">Снаряжение в бою</p>
            <h2 class="mt-1 text-lg font-semibold text-white">Схема экипировки участников</h2>
        </div>
        <p class="max-w-2xl text-xs text-zinc-400">
            Наведи на занятый слот, чтобы увидеть характеристики предмета: урон, защиту, эффекты и доступные слоты.
        </p>
    </div>

    <div class="battle-equipment-maps__grid mt-3">
        @foreach ($battle->participants->sortBy('side')->values() as $participant)
            @php
                $creature = $participant->creature;
                $equipmentBySlot = $creature?->equipmentRows?->keyBy('slot_key') ?? collect();
                $equippedCount = $equipmentBySlot->count();
                $creatureImage = MediaUrl::resolve(
                    $creature?->species?->portrait_image
                    ?? $creature?->species?->battle_image
                    ?? $creature?->species?->icon
                    ?? $creature?->type?->icon
                );
            @endphp

            <article class="battle-equipment-card">
                <div class="battle-equipment-card__title">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-zinc-500">
                            {{ $sideLabels[$participant->side] ?? $participant->side }}
                            @if ($participant->is_bot)
                                · бот
                            @endif
                        </p>
                        <h3>{{ $creature?->name ?? 'Участник' }}</h3>
                    </div>
                    <span>{{ $equippedCount }}/{{ $slots->count() }}</span>
                </div>

                <div class="battle-equipment-card__body" aria-label="Схема экипировки {{ $creature?->name ?? 'участника' }}">
                    <div class="battle-equipment-silhouette" aria-hidden="true">
                        <span class="battle-equipment-silhouette__aura"></span>
                        <span class="battle-equipment-silhouette__ring battle-equipment-silhouette__ring--outer"></span>
                        <span class="battle-equipment-silhouette__ring battle-equipment-silhouette__ring--inner"></span>
                        @if ($creatureImage)
                            <img class="battle-equipment-silhouette__portrait" src="{{ $creatureImage }}" alt="">
                        @endif
                        <span class="battle-equipment-silhouette__head"></span>
                        <span class="battle-equipment-silhouette__body"></span>
                        <span class="battle-equipment-silhouette__limb battle-equipment-silhouette__limb--front-left"></span>
                        <span class="battle-equipment-silhouette__limb battle-equipment-silhouette__limb--front-right"></span>
                        <span class="battle-equipment-silhouette__limb battle-equipment-silhouette__limb--rear-left"></span>
                        <span class="battle-equipment-silhouette__limb battle-equipment-silhouette__limb--rear-right"></span>
                    </div>

                    @foreach ($slots as $slot)
                        @php
                            $equipmentRow = $equipmentBySlot->get($slot->code);
                            $itemInstance = $equipmentRow?->itemInstance;
                            $item = $itemInstance?->item;
                            $slotClass = 'battle-equipment-slot--'.Str::slug($slot->code);
                            $slotLabel = $shortLabels[$slot->code] ?? Str::upper(Str::substr($slot->name, 0, 1));
                            $damageBonus = $item?->damageBonus() ?? 0;
                            $defenseBonus = $item?->defenseBonus() ?? 0;
                            $bonusSummaries = $item?->bonusSummaries() ?? [];
                            $compactEffects = collect($bonusSummaries)
                                ->reject(fn (string $summary): bool => str_starts_with($summary, 'Урон ') || str_starts_with($summary, 'Защита '))
                                ->take(4)
                                ->values()
                                ->all();
                            $slotSummary = $item
                                ? collect($item->equipmentSlotKeys())
                                    ->map(fn (string $slotKey): string => $slots->firstWhere('code', $slotKey)?->name ?? $slotKey)
                                    ->join(', ')
                                : null;
                        @endphp

                        <div
                            class="battle-equipment-slot {{ $slotClass }} {{ $item ? 'is-equipped' : 'is-empty' }}"
                            tabindex="0"
                            aria-label="{{ $slot->name }}: {{ $item?->name ?? 'пусто' }}"
                        >
                            @if ($item)
                                <x-game-icon :icon="$item->icon" :label="$item->name" size="sm" class="battle-equipment-slot__icon" />
                            @else
                                <span class="battle-equipment-slot__mark">{{ $slotLabel }}</span>
                            @endif
                            <span class="battle-equipment-slot__name">{{ $slot->name }}</span>

                            <div class="battle-equipment-tooltip" role="tooltip">
                                @if ($item)
                                    <p class="battle-equipment-tooltip__slot">{{ $slot->name }}</p>
                                    <div class="battle-equipment-tooltip__head">
                                        <x-game-icon :icon="$item->icon" :label="$item->name" size="sm" class="battle-equipment-tooltip__icon" />
                                        <div>
                                            <h4>{{ $item->name }}</h4>
                                            <p>{{ \App\Models\Item::RARITIES[$item->rarity] ?? $item->rarity }} · {{ \App\Models\Item::TYPES[$item->item_type] ?? $item->item_type }} · ур. {{ $item->required_level }}</p>
                                        </div>
                                    </div>

                                    <div class="battle-equipment-tooltip__stats">
                                        <span class="battle-equipment-tooltip__stat battle-equipment-tooltip__stat--damage">Урон {{ $damageBonus > 0 ? '+' : '' }}{{ $damageBonus }}</span>
                                        <span class="battle-equipment-tooltip__stat battle-equipment-tooltip__stat--defense">Защита {{ $defenseBonus > 0 ? '+' : '' }}{{ $defenseBonus }}</span>
                                    </div>

                                    @if ($compactEffects !== [])
                                        <p class="battle-equipment-tooltip__effects">
                                            @foreach ($compactEffects as $effect)
                                                <span>{{ $effect }}</span>
                                            @endforeach
                                        </p>
                                    @endif

                                    @if ($slotSummary)
                                        <p class="battle-equipment-tooltip__line"><b>Слоты:</b> {{ $slotSummary }}</p>
                                    @endif

                                    @if ($item->description)
                                        <p class="battle-equipment-tooltip__description">{{ Str::limit($item->description, 92) }}</p>
                                    @endif
                                @else
                                    <p class="battle-equipment-tooltip__slot">{{ $slot->name }}</p>
                                    <h4>Пустой слот</h4>
                                    <p class="mt-1 text-xs text-zinc-400">На этой части тела предмет не установлен.</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="battle-equipment-card__legend">
                    @forelse ($equipmentBySlot->sortKeys() as $slotKey => $equipmentRow)
                        @php
                            $item = $equipmentRow?->itemInstance?->item;
                            $slot = $slots->firstWhere('code', $slotKey);
                        @endphp

                        @if ($item)
                            <span title="{{ $slot?->name ?? $slotKey }}">
                                <x-game-icon :icon="$item->icon" :label="$item->name" size="sm" class="battle-equipment-card__legend-icon" />
                                {{ $shortLabels[$slotKey] ?? '•' }} {{ $item->name }}
                            </span>
                        @endif
                    @empty
                        <span>Экипировка не установлена</span>
                    @endforelse
                </div>
            </article>
        @endforeach
    </div>
</section>
