@php
    $equipmentBonuses = $creature->equipmentBonuses();
    $effectiveSpecial = $creature->effectiveSpecialValues();
    $combatStats = $creature->effectiveCombatStats();
@endphp

<section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold text-white">SPECIAL</h2>
        <span class="rounded-md border border-zinc-800 px-3 py-1 text-sm text-zinc-300">
            HP {{ $creature->current_hp }}/{{ $creature->effectiveMaxHp() }}
        </span>
    </div>

    <div class="mt-4 grid gap-3 md:grid-cols-2">
        <div class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-semibold text-white">Урон</h3>
                <span class="text-2xl font-semibold text-rose-100">{{ $combatStats['damage']['total'] }}</span>
            </div>
            <dl class="mt-3 grid grid-cols-3 gap-2 text-xs text-zinc-400">
                <div>
                    <dt>От SPECIAL</dt>
                    <dd class="mt-1 text-sm font-semibold text-zinc-100">{{ $combatStats['damage']['base'] }}</dd>
                </div>
                <div>
                    <dt>Предметы</dt>
                    <dd class="mt-1 text-sm font-semibold {{ $combatStats['damage']['equipment'] >= 0 ? 'text-emerald-200' : 'text-rose-200' }}">
                        {{ $combatStats['damage']['equipment'] >= 0 ? '+' : '' }}{{ $combatStats['damage']['equipment'] }}
                    </dd>
                </div>
                <div>
                    <dt>Итог</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">{{ $combatStats['damage']['total'] }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-zinc-500">База зависит от S, A и I; оружие может добавлять прямой бонус к урону.</p>
        </div>

        <div class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-semibold text-white">Защита</h3>
                <span class="text-2xl font-semibold text-sky-100">{{ $combatStats['defense']['total'] }}</span>
            </div>
            <dl class="mt-3 grid grid-cols-3 gap-2 text-xs text-zinc-400">
                <div>
                    <dt>От SPECIAL</dt>
                    <dd class="mt-1 text-sm font-semibold text-zinc-100">{{ $combatStats['defense']['base'] }}</dd>
                </div>
                <div>
                    <dt>Предметы</dt>
                    <dd class="mt-1 text-sm font-semibold {{ $combatStats['defense']['equipment'] >= 0 ? 'text-emerald-200' : 'text-rose-200' }}">
                        {{ $combatStats['defense']['equipment'] >= 0 ? '+' : '' }}{{ $combatStats['defense']['equipment'] }}
                    </dd>
                </div>
                <div>
                    <dt>Итог</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">{{ $combatStats['defense']['total'] }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-zinc-500">База зависит от E, C и I; броня и щиты могут добавлять прямой бонус к защите.</p>
        </div>
    </div>

    <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-zinc-500">
                <tr>
                    <th class="px-3 py-2">Параметр</th>
                    <th class="px-3 py-2">База</th>
                    <th class="px-3 py-2">Распределено</th>
                    <th class="px-3 py-2">Экипировка</th>
                    <th class="px-3 py-2">Итог</th>
                    <th class="px-3 py-2">Развитие</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @foreach (\App\Models\Creature::SPECIAL_LABELS as $attribute => $label)
                    @php
                        $base = $creature->species->baseSpecialValue($attribute);
                        $allocated = (int) $creature->{$attribute} - $base;
                        $bonus = (int) ($equipmentBonuses[$attribute] ?? 0);
                        $canImprove = $creature->is_available_for_battle
                            && $creature->development_points >= \App\Models\Creature::SPECIAL_DEVELOPMENT_COST
                            && (int) $creature->{$attribute} < \App\Models\Creature::DEVELOPMENT_SPECIAL_CAP;
                    @endphp
                    <tr>
                        <th class="px-3 py-2 font-semibold text-white">{{ $label }}</th>
                        <td class="px-3 py-2 text-zinc-300">{{ $base }}</td>
                        <td class="px-3 py-2 text-zinc-300">+{{ $allocated }}</td>
                        <td class="px-3 py-2 {{ $bonus >= 0 ? 'text-emerald-200' : 'text-rose-200' }}">
                            {{ $bonus >= 0 ? '+' : '' }}{{ $bonus }}
                        </td>
                        <td class="px-3 py-2 font-semibold text-white">{{ $effectiveSpecial[$attribute] }}</td>
                        <td class="px-3 py-2">
                            <form method="POST" action="{{ route('entities.special.increase', $creature) }}">
                                @csrf
                                <input type="hidden" name="attribute" value="{{ $attribute }}">
                                <button
                                    type="submit"
                                    class="rounded-md border border-emerald-500/50 px-2 py-1 text-xs text-emerald-100 hover:bg-emerald-500/10 disabled:cursor-not-allowed disabled:opacity-50"
                                    @disabled(! $canImprove)
                                >
                                    +1 / {{ \App\Models\Creature::SPECIAL_DEVELOPMENT_COST }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if (($equipmentBonuses['hp'] ?? 0) !== 0)
        <p class="mt-3 text-sm text-emerald-200">
            Бонус HP от экипировки: {{ $equipmentBonuses['hp'] > 0 ? '+' : '' }}{{ $equipmentBonuses['hp'] }}.
        </p>
    @endif

    <p class="mt-3 text-xs text-zinc-500">
        Повышение SPECIAL стоит {{ \App\Models\Creature::SPECIAL_DEVELOPMENT_COST }} очков развития за +1. Максимум развития: {{ \App\Models\Creature::DEVELOPMENT_SPECIAL_CAP }}.
    </p>
</section>
