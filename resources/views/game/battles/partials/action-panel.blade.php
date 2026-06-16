@if ($isInteractiveRunning && $activeRound && $ownParticipant)
    <section class="rounded-md border border-emerald-500/30 bg-zinc-900 p-5">
        <div class="grid gap-5 lg:grid-cols-[1fr_1.4fr]">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">РЁР°Рі {{ $activeRound->round_number }}</p>
                <h2 class="mt-2 text-xl font-semibold text-white">Р’С‹Р±РѕСЂ С‚Р°РєС‚РёРєРё</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                        <dt class="text-zinc-500">Р”РµРґР»Р°Р№РЅ</dt>
                        <dd class="mt-1 text-zinc-100">
                            {{ $activeRound->deadline_at?->format('H:i:s') }}
                            <span class="ml-2 rounded-md border border-emerald-500/40 px-2 py-0.5 text-xs text-emerald-100" data-battle-countdown>
                                00:00
                            </span>
                        </dd>
                    </div>
                    <div class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2">
                        <dt class="text-zinc-500">РџРµСЂРІС‹Р№ С‚РµРјРї</dt>
                        <dd class="mt-1 text-zinc-100">{{ $activeRound->firstActor?->name ?? 'РЅРµ РѕРїСЂРµРґРµР»РµРЅ' }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-sm text-zinc-400">
                    РћР±Р° СѓС‡Р°СЃС‚РЅРёРєР° РІС‹Р±РёСЂР°СЋС‚ Р°С‚Р°РєСѓ Рё Р·Р°С‰РёС‚Сѓ. Р•СЃР»Рё РёРіСЂРѕРє РЅРµ СѓСЃРїРµРµС‚, СЃРёСЃС‚РµРјР° РїРѕРґСЃС‚Р°РІРёС‚ РѕСЃС‚РѕСЂРѕР¶РЅСѓСЋ Р°РІС‚РѕС‚Р°РєС‚РёРєСѓ.
                </p>
            </div>

            @if ($ownAction)
                <div class="rounded-md border border-zinc-800 bg-zinc-950 p-5">
                    <h3 class="text-lg font-semibold text-white">РўРІРѕСЏ С‚Р°РєС‚РёРєР° РїСЂРёРЅСЏС‚Р°</h3>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-md border border-zinc-800 px-3 py-2">
                            <div class="text-xs text-zinc-500">РђС‚Р°РєР°</div>
                            <div class="mt-1 text-zinc-100">{{ $zones[$ownAction->attack_zone] ?? $ownAction->attack_zone }}</div>
                        </div>
                        <div class="rounded-md border border-zinc-800 px-3 py-2">
                            <div class="text-xs text-zinc-500">Р—Р°С‰РёС‚Р°</div>
                            <div class="mt-1 text-zinc-100">{{ $zones[$ownAction->defense_zone] ?? $ownAction->defense_zone }}</div>
                        </div>
                        <div class="rounded-md border border-zinc-800 px-3 py-2">
                            <div class="text-xs text-zinc-500">РўРёРї</div>
                            <div class="mt-1 text-zinc-100">{{ $ownAction->is_auto ? 'РђРІС‚Рѕ' : 'РРіСЂРѕРє' }}</div>
                        </div>
                    </div>
                    <p class="mt-4 text-sm text-zinc-400">Р–РґРµРј РґРµР№СЃС‚РІРёРµ РІС‚РѕСЂРѕРіРѕ СѓС‡Р°СЃС‚РЅРёРєР° РёР»Рё РёСЃС‚РµС‡РµРЅРёРµ С‚Р°Р№РјРµСЂР°.</p>
                </div>
            @else
                <form method="POST" action="{{ route('arena.battles.actions.store', $battle) }}" class="rounded-md border border-zinc-800 bg-zinc-950 p-5" data-battle-action-form>
                    @csrf
                    <div class="grid gap-5 lg:grid-cols-2">
                        <fieldset>
                            <legend class="text-sm font-semibold text-white">РђС‚Р°РєР°</legend>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                @foreach ($zones as $zone => $label)
                                    <label class="cursor-pointer rounded-md border border-zinc-800 px-3 py-2 text-sm text-zinc-200 hover:border-emerald-500/50">
                                        <input type="radio" name="attack_zone" value="{{ $zone }}" class="mr-2" @checked(old('attack_zone', 'body') === $zone)>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="text-sm font-semibold text-white">Р—Р°С‰РёС‚Р°</legend>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                @foreach ($zones as $zone => $label)
                                    <label class="cursor-pointer rounded-md border border-zinc-800 px-3 py-2 text-sm text-zinc-200 hover:border-sky-500/50">
                                        <input type="radio" name="defense_zone" value="{{ $zone }}" class="mr-2" @checked(old('defense_zone', 'body') === $zone)>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>

                    <label class="mt-5 block text-sm font-semibold text-white" for="inventory_item_id">Р Р°СЃС…РѕРґРЅРёРє РЅР° С€Р°Рі</label>
                    <select id="inventory_item_id" name="inventory_item_id" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                        <option value="">РќРµ РїСЂРёРјРµРЅСЏС‚СЊ</option>
                        @foreach ($availableConsumables as $inventoryItem)
                            @php
                                $item = $inventoryItem->itemInstance?->item;
                            @endphp
                            @if ($item)
                                <option value="{{ $inventoryItem->id }}" @selected((string) old('inventory_item_id') === (string) $inventoryItem->id)>
                                    {{ $item->name }} / РїСЂРёРјРµРЅРµРЅРёР№: {{ $inventoryItem->itemInstance->remainingUses() }}
                                </option>
                            @endif
                        @endforeach
                    </select>

                    <button type="submit" class="mt-5 rounded-md bg-emerald-500 px-5 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400">
                        РџРѕРґС‚РІРµСЂРґРёС‚СЊ С€Р°Рі
                    </button>
                </form>
            @endif
        </div>
    </section>
@endif
