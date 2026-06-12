@php
    $equipmentBonuses = $creature->equipmentBonuses();
    $effectiveSpecial = $creature->effectiveSpecialValues();
@endphp

<section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold text-white">SPECIAL</h2>
        <span class="rounded-md border border-zinc-800 px-3 py-1 text-sm text-zinc-300">
            HP {{ $creature->current_hp }}/{{ $creature->effectiveMaxHp() }}
        </span>
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
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @foreach (\App\Models\Creature::SPECIAL_LABELS as $attribute => $label)
                    @php
                        $base = $creature->species->baseSpecialValue($attribute);
                        $allocated = (int) $creature->{$attribute} - $base;
                        $bonus = (int) ($equipmentBonuses[$attribute] ?? 0);
                    @endphp
                    <tr>
                        <th class="px-3 py-2 font-semibold text-white">{{ $label }}</th>
                        <td class="px-3 py-2 text-zinc-300">{{ $base }}</td>
                        <td class="px-3 py-2 text-zinc-300">+{{ $allocated }}</td>
                        <td class="px-3 py-2 {{ $bonus >= 0 ? 'text-emerald-200' : 'text-rose-200' }}">
                            {{ $bonus >= 0 ? '+' : '' }}{{ $bonus }}
                        </td>
                        <td class="px-3 py-2 font-semibold text-white">{{ $effectiveSpecial[$attribute] }}</td>
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
</section>
