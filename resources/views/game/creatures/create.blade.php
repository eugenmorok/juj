@php
    $speciesPayload = $creatureTypes->flatMap(fn ($type) => $type->species->map(fn ($species) => [
        'id' => $species->id,
        'typeId' => $type->id,
        'name' => $species->name,
        'base' => $species->baseSpecialValues(),
    ]))->values();
    $skillsPayload = $starterSkills->mapWithKeys(fn ($skill) => [$skill->id => [
        'id' => $skill->id,
        'cost' => $skill->cost,
        'requiredTypeId' => $skill->required_creature_type_id,
        'requiredSpeciesId' => $skill->required_creature_species_id,
        'requirements' => $skill->specialRequirements(),
    ]]);
    $firstSpecies = $speciesPayload->first();
    $selectedSpeciesId = (int) old('creature_species_id', $firstSpecies['id'] ?? 0);
    $selectedSpecies = $speciesPayload->firstWhere('id', $selectedSpeciesId) ?? $firstSpecies;
    $selectedTypeId = (int) ($selectedSpecies['typeId'] ?? 0);
    $canCreateCreature = $availableCreationPoints >= $creationCost;
@endphp

@extends('layouts.app', ['title' => 'Создание сущности'])

@section('content')
    <div class="space-y-6" data-creature-builder>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase text-emerald-300">Создание сущности</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Новая сущность</h1>
            </div>
            <a href="{{ route('entities.index') }}" class="rounded-md border border-zinc-700 px-4 py-2 text-zinc-200 hover:bg-zinc-900">
                К списку
            </a>
        </div>

        @include('partials.form-errors')

        <div class="rounded-md border {{ $canCreateCreature ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100' : 'border-amber-500/40 bg-amber-500/10 text-amber-100' }} px-4 py-3 text-sm">
            Очки создания: {{ $availableCreationPoints }}/{{ $creationCost }}.
            @if (! $canCreateCreature)
                Для новой сущности накопи 100 очков создания или конвертируй XP игрока в профиле.
            @else
                При создании сущности будет списано {{ $creationCost }} очков создания.
            @endif
        </div>

        @if ($speciesPayload->isEmpty())
            <div class="rounded-md border border-zinc-800 bg-zinc-900 p-8 text-center">
                <h2 class="text-lg font-semibold text-white">Нет доступных стартовых видов</h2>
                <p class="mt-2 text-sm text-zinc-400">Администратор должен включить активный тип и хотя бы один стартовый вид.</p>
            </div>
        @else
            <form method="POST" action="{{ route('entities.store') }}" class="grid gap-6 lg:grid-cols-[1.25fr_0.75fr]">
                @csrf

                <div class="space-y-6">
                    <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                        <h2 class="font-semibold text-white">Основа</h2>

                        <div class="mt-4 grid gap-4 md:grid-cols-3">
                            <label class="space-y-2 md:col-span-3">
                                <span class="text-sm text-zinc-300">Имя</span>
                                <input
                                    name="name"
                                    value="{{ old('name') }}"
                                    class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white"
                                    maxlength="64"
                                    required
                                >
                            </label>

                            <label class="space-y-2">
                                <span class="text-sm text-zinc-300">Тип</span>
                                <select
                                    data-creature-type-select
                                    class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white"
                                >
                                    @foreach ($creatureTypes as $type)
                                        <option value="{{ $type->id }}" @selected($selectedTypeId === $type->id)>{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="space-y-2 md:col-span-2">
                                <span class="text-sm text-zinc-300">Вид</span>
                                <select
                                    name="creature_species_id"
                                    data-creature-species-select
                                    class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white"
                                    required
                                >
                                    @foreach ($creatureTypes as $type)
                                        @foreach ($type->species as $species)
                                            <option value="{{ $species->id }}" data-type-id="{{ $type->id }}" @selected($selectedSpeciesId === $species->id)>
                                                {{ $species->name }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </section>

                    <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="font-semibold text-white">SPECIAL</h2>
                            <span class="rounded-md border border-zinc-800 px-3 py-1 text-sm text-zinc-300">
                                Стартовый cap {{ \App\Models\Creature::STARTER_SPECIAL_CAP }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach (\App\Models\Creature::SPECIAL_LABELS as $attribute => $label)
                                <label class="rounded-md border border-zinc-800 bg-zinc-950 p-3">
                                    <span class="flex items-center justify-between gap-3">
                                        <span class="font-semibold text-white">{{ $label }}</span>
                                        <span class="text-xs text-zinc-400">База <span data-base-value="{{ $attribute }}">0</span></span>
                                    </span>
                                    <input
                                        type="number"
                                        name="{{ $attribute }}"
                                        value="{{ old($attribute, 1) }}"
                                        data-special-input="{{ $attribute }}"
                                        min="1"
                                        max="{{ \App\Models\Creature::STARTER_SPECIAL_CAP }}"
                                        class="mt-3 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-white"
                                        required
                                    >
                                </label>
                            @endforeach
                        </div>
                    </section>

                    <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="font-semibold text-white">Стартовые навыки</h2>
                            <span class="rounded-md border border-zinc-800 px-3 py-1 text-sm text-zinc-300">
                                <span data-selected-skill-count>0</span>/{{ \App\Models\Creature::BASE_SKILL_LIMIT }}
                            </span>
                        </div>

                        @if ($starterSkills->isEmpty())
                            <p class="mt-4 text-sm text-zinc-400">Стартовые навыки пока не добавлены.</p>
                        @else
                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                @foreach ($starterSkills as $skill)
                                    <label class="rounded-md border border-zinc-800 bg-zinc-950 p-4" data-skill-card data-skill-id="{{ $skill->id }}">
                                        <span class="flex items-start gap-3">
                                            <input
                                                type="checkbox"
                                                name="skills[]"
                                                value="{{ $skill->id }}"
                                                data-skill-checkbox
                                                class="mt-1"
                                                @checked(in_array($skill->id, old('skills', [])))
                                            >
                                            <span class="min-w-0 flex-1">
                                                <span class="flex flex-wrap items-center gap-2">
                                                    <span class="font-semibold text-white">{{ $skill->name }}</span>
                                                    <span class="rounded-md border border-emerald-500/40 px-2 py-0.5 text-xs text-emerald-200">{{ $skill->cost }} оч.</span>
                                                </span>
                                                @if ($skill->description)
                                                    <span class="mt-1 block text-sm text-zinc-400">{{ $skill->description }}</span>
                                                @endif
                                                <span class="mt-2 hidden text-xs text-amber-200" data-skill-unavailable>Недоступен для выбранного билда</span>
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </div>

                <aside class="space-y-4">
                    <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                        <h2 class="font-semibold text-white">Бюджет</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-zinc-400">Свободные очки</dt>
                                <dd class="font-semibold text-white">{{ \App\Models\Creature::CREATION_POINTS }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-zinc-400">Баланс игрока</dt>
                                <dd class="font-semibold text-white">{{ $availableCreationPoints }}/{{ $creationCost }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-zinc-400">SPECIAL</dt>
                                <dd class="font-semibold text-white" data-stats-cost>0</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-zinc-400">Навыки</dt>
                                <dd class="font-semibold text-white" data-skills-cost>0</dd>
                            </div>
                            <div class="border-t border-zinc-800 pt-3">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-zinc-400">Остаток</dt>
                                    <dd class="text-lg font-semibold text-white" data-remaining-points>{{ \App\Models\Creature::CREATION_POINTS }}</dd>
                                </div>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-md border border-zinc-800 bg-zinc-900 p-5">
                        <h2 class="font-semibold text-white">База вида</h2>
                        <dl class="mt-4 grid grid-cols-7 gap-2 text-center text-xs">
                            @foreach (\App\Models\Creature::SPECIAL_LABELS as $attribute => $label)
                                <div class="rounded-md border border-zinc-800 bg-zinc-950 px-2 py-1">
                                    <dt class="text-zinc-500">{{ $label }}</dt>
                                    <dd class="mt-0.5 font-semibold text-white" data-base-summary="{{ $attribute }}">0</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>

                    <button type="submit" data-creature-submit class="w-full rounded-md bg-emerald-500 px-4 py-3 font-medium text-zinc-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50" @disabled(! $canCreateCreature)>
                        Создать сущность
                    </button>
                </aside>
            </form>
        @endif
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-creature-builder]');

            if (!root) {
                return;
            }

            const species = @js($speciesPayload->keyBy('id'));
            const skills = @js($skillsPayload);
            const creationPoints = {{ \App\Models\Creature::CREATION_POINTS }};
            const skillLimit = {{ \App\Models\Creature::BASE_SKILL_LIMIT }};
            const canCreateCreature = @js($canCreateCreature);
            const typeSelect = root.querySelector('[data-creature-type-select]');
            const speciesSelect = root.querySelector('[data-creature-species-select]');
            const specialInputs = [...root.querySelectorAll('[data-special-input]')];
            const skillCards = [...root.querySelectorAll('[data-skill-card]')];
            const skillCheckboxes = [...root.querySelectorAll('[data-skill-checkbox]')];
            const statsCostNode = root.querySelector('[data-stats-cost]');
            const skillsCostNode = root.querySelector('[data-skills-cost]');
            const remainingNode = root.querySelector('[data-remaining-points]');
            const selectedSkillCountNode = root.querySelector('[data-selected-skill-count]');
            const submitButton = root.querySelector('[data-creature-submit]');

            const selectedSpecies = () => species[Number(speciesSelect.value)];

            const currentSpecial = () => Object.fromEntries(specialInputs.map((input) => [
                input.dataset.specialInput,
                Number(input.value || 0),
            ]));

            const skillAvailable = (skill, values, speciesData) => {
                if (!speciesData) {
                    return false;
                }

                if (skill.requiredTypeId && skill.requiredTypeId !== speciesData.typeId) {
                    return false;
                }

                if (skill.requiredSpeciesId && skill.requiredSpeciesId !== speciesData.id) {
                    return false;
                }

                return Object.entries(skill.requirements || {}).every(([attribute, required]) => values[attribute] >= required);
            };

            const applySpeciesBase = () => {
                const speciesData = selectedSpecies();

                if (!speciesData) {
                    return;
                }

                specialInputs.forEach((input) => {
                    const attribute = input.dataset.specialInput;
                    const baseValue = speciesData.base[attribute] || 1;

                    input.min = baseValue;
                    input.value = Math.max(Number(input.value || baseValue), baseValue);
                    root.querySelector(`[data-base-value="${attribute}"]`).textContent = baseValue;
                    root.querySelector(`[data-base-summary="${attribute}"]`).textContent = baseValue;
                });
            };

            const updateSpeciesOptions = () => {
                const selectedTypeId = Number(typeSelect.value);
                const visibleOptions = [...speciesSelect.options].filter((option) => Number(option.dataset.typeId) === selectedTypeId);

                [...speciesSelect.options].forEach((option) => {
                    option.hidden = Number(option.dataset.typeId) !== selectedTypeId;
                });

                if (speciesSelect.selectedOptions[0]?.hidden && visibleOptions[0]) {
                    speciesSelect.value = visibleOptions[0].value;
                }

                applySpeciesBase();
                updateSummary();
            };

            const updateSummary = () => {
                const speciesData = selectedSpecies();
                const values = currentSpecial();
                let statsCost = 0;
                let skillsCost = 0;
                let selectedSkillCount = 0;

                if (speciesData) {
                    Object.entries(speciesData.base).forEach(([attribute, baseValue]) => {
                        statsCost += Math.max(0, (values[attribute] || 0) - baseValue);
                    });
                }

                skillCards.forEach((card) => {
                    const skill = skills[Number(card.dataset.skillId)];
                    const checkbox = card.querySelector('[data-skill-checkbox]');
                    const unavailable = card.querySelector('[data-skill-unavailable]');
                    const available = skillAvailable(skill, values, speciesData);

                    checkbox.disabled = !available;
                    unavailable.classList.toggle('hidden', available);
                    card.classList.toggle('opacity-60', !available);

                    if (!available) {
                        checkbox.checked = false;
                    }

                    if (checkbox.checked) {
                        selectedSkillCount += 1;
                        skillsCost += skill.cost;
                    }
                });

                statsCostNode.textContent = statsCost;
                skillsCostNode.textContent = skillsCost;
                selectedSkillCountNode.textContent = selectedSkillCount;

                const remaining = creationPoints - statsCost - skillsCost;
                const invalid = !canCreateCreature || remaining < 0 || selectedSkillCount > skillLimit;

                remainingNode.textContent = remaining;
                remainingNode.classList.toggle('text-red-100', invalid);
                remainingNode.classList.toggle('text-white', !invalid);

                if (submitButton) {
                    submitButton.disabled = invalid;
                }
            };

            typeSelect?.addEventListener('change', updateSpeciesOptions);
            speciesSelect?.addEventListener('change', () => {
                applySpeciesBase();
                updateSummary();
            });
            specialInputs.forEach((input) => input.addEventListener('input', updateSummary));
            skillCheckboxes.forEach((input) => input.addEventListener('change', updateSummary));

            updateSpeciesOptions();
        })();
    </script>
@endsection
