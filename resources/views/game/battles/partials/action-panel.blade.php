@if ($isInteractiveRunning && $activeRound && $ownParticipant)
    <section class="battle-tactics-card rounded-md border border-emerald-500/30 bg-zinc-900">
        <header class="battle-tactics-card__header">
            <div>
                <span>Шаг {{ $activeRound->round_number }}</span>
                <h2>Выбор тактики</h2>
            </div>
            <div class="battle-tactics-card__timer">
                <small>Осталось</small>
                <b data-battle-countdown>00:00</b>
            </div>
        </header>

        <p class="battle-tactics-card__tempo">
            Первый ход: <strong>{{ $activeRound->firstActor?->name ?? 'не определён' }}</strong>
        </p>

        @if ($ownAction)
            <div class="battle-tactics-card__accepted">
                <strong>Тактика принята</strong>
                <span>Атака: {{ $zones[$ownAction->attack_zone] ?? $ownAction->attack_zone }}</span>
                <span>Защита: {{ $zones[$ownAction->defense_zone] ?? $ownAction->defense_zone }}</span>
                <small>Ожидаем соперника</small>
            </div>
        @else
            <form method="POST" action="{{ route('arena.battles.actions.store', $battle) }}" class="battle-tactics-form" data-battle-action-form>
                @csrf

                <fieldset>
                    <legend>Атака</legend>
                    <div class="battle-zone-grid">
                        @foreach ($zones as $zone => $label)
                            <label>
                                <input type="radio" name="attack_zone" value="{{ $zone }}" @checked(old('attack_zone', 'body') === $zone)>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Защита</legend>
                    <div class="battle-zone-grid">
                        @foreach ($zones as $zone => $label)
                            <label>
                                <input type="radio" name="defense_zone" value="{{ $zone }}" @checked(old('defense_zone', 'body') === $zone)>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <label class="battle-tactics-form__consumable" for="inventory_item_id">
                    <span>Расходник</span>
                    <select id="inventory_item_id" name="inventory_item_id">
                        <option value="">Не применять</option>
                        @foreach ($availableConsumables as $inventoryItem)
                            @php($item = $inventoryItem->itemInstance?->item)
                            @if ($item)
                                <option value="{{ $inventoryItem->id }}" @selected((string) old('inventory_item_id') === (string) $inventoryItem->id)>
                                    {{ $item->name }}
                                    @if ($item->effectSummary(false))
                                        · {{ $item->effectSummary(false) }}
                                    @endif
                                    · {{ $inventoryItem->itemInstance->remainingUses() }} шт.
                                </option>
                            @endif
                        @endforeach
                    </select>
                </label>

                @if ($availableConsumables->isNotEmpty())
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($availableConsumables as $inventoryItem)
                            @php($item = $inventoryItem->itemInstance?->item)
                            @if ($item)
                                <article class="rounded-md border border-zinc-700 bg-zinc-950/70 p-3 text-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <strong class="text-zinc-100">{{ $item->name }}</strong>
                                        <span class="text-xs text-emerald-300">{{ $inventoryItem->itemInstance->remainingUses() }} шт.</span>
                                    </div>
                                    @if ($item->description)
                                        <p class="mt-1 text-xs text-zinc-400">{{ $item->description }}</p>
                                    @endif
                                    <x-item-effects :item="$item" />
                                </article>
                            @endif
                        @endforeach
                    </div>
                @endif

                <button type="submit">Подтвердить шаг</button>
            </form>
        @endif
    </section>
@endif
