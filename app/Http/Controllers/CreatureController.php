<?php

namespace App\Http\Controllers;

use App\Models\Creature;
use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\Skill;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatureController extends Controller
{
    public function index(Request $request): View
    {
        return view('game.entities', [
            'creatures' => $request->user()
                ->creatures()
                ->with(['type', 'species', 'skills'])
                ->latest()
                ->get(),
            'creatureTypes' => CreatureType::query()
                ->active()
                ->with(['species' => fn ($query) => $query->active()->orderBy('name')])
                ->withCount(['species' => fn ($query) => $query->active()])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $creatureTypes = CreatureType::query()
            ->active()
            ->whereHas('species', fn ($query) => $query->active()->starterAvailable())
            ->with(['species' => fn ($query) => $query->active()->starterAvailable()->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('game.creatures.create', [
            'creatureTypes' => $creatureTypes,
            'starterSkills' => Skill::query()
                ->active()
                ->starterAvailable()
                ->with(['requiredType', 'requiredSpecies'])
                ->orderBy('cost')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $request->validate($this->creationRules(), $this->creationMessages());
        $species = $this->starterSpecies((int) $attributes['creature_species_id']);
        $skillIds = $this->skillIds($attributes['skills'] ?? []);
        $skills = $this->starterSkills($skillIds);
        $special = $this->validatedSpecialValues($attributes, $species);
        $skillCost = $skills->sum('cost');
        $statsCost = $this->statsCost($special, $species);

        $this->ensureCreationBudget($statsCost, $skillCost);

        $maxHp = Creature::maxHpForEndurance($special['endurance']);

        $creature = DB::transaction(function () use ($request, $attributes, $species, $skills, $special, $maxHp): Creature {
            $creature = Creature::query()->create([
                'user_id' => $request->user()->id,
                'creature_type_id' => $species->creature_type_id,
                'creature_species_id' => $species->id,
                'name' => $attributes['name'],
                'level' => 1,
                'xp' => 0,
                'development_points' => 0,
                ...$special,
                'current_hp' => $maxHp,
                'max_hp' => $maxHp,
                'inventory_slots' => Creature::STARTER_INVENTORY_SLOTS,
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'is_available_for_battle' => true,
            ]);

            $this->attachCreationSkills($creature, $skills);
            $creature->ensureInventory();

            return $creature;
        });

        return redirect()
            ->route('entities.show', $creature)
            ->with('status', 'Сущность создана.');
    }

    public function show(Request $request, Creature $creature): View
    {
        $this->authorizeCreatureOwner($request, $creature);

        $creature->ensureInventory();
        $creature->load([
            'type',
            'species',
            'skills',
            'inventory.inventoryItems.itemInstance.item',
            'equipmentRows.slot',
            'equipmentRows.itemInstance.item',
        ]);

        return view('game.creatures.show', [
            'creature' => $creature,
            'availableSkills' => Skill::query()
                ->active()
                ->with(['requiredType', 'requiredSpecies'])
                ->orderBy('cost')
                ->orderBy('name')
                ->get()
                ->reject(fn (Skill $skill): bool => $creature->skills->contains($skill))
                ->filter(fn (Skill $skill): bool => $skill->isAvailableFor($creature))
                ->values(),
            'playerInventory' => $request->user()
                ->ensureInventory()
                ->load('inventoryItems.itemInstance.item'),
        ]);
    }

    public function buySkill(Request $request, Creature $creature, Skill $skill): RedirectResponse
    {
        $this->authorizeCreatureOwner($request, $creature);

        $creature->load('skills');

        if ($creature->skills->contains($skill)) {
            throw ValidationException::withMessages([
                'skill' => 'Эта сущность уже знает выбранный навык.',
            ]);
        }

        if (! $creature->hasSkillCapacity()) {
            throw ValidationException::withMessages([
                'skill' => 'Лимит навыков для текущего уровня уже исчерпан.',
            ]);
        }

        if (! $skill->isAvailableFor($creature)) {
            throw ValidationException::withMessages([
                'skill' => 'Навык недоступен для этой сущности.',
            ]);
        }

        if ($creature->development_points < $skill->cost) {
            throw ValidationException::withMessages([
                'skill' => 'Недостаточно очков развития для покупки навыка.',
            ]);
        }

        DB::transaction(function () use ($creature, $skill): void {
            $creature->decrement('development_points', $skill->cost);
            $creature->skills()->attach($skill->id, [
                'cost_paid' => $skill->cost,
                'source' => 'development',
            ]);
        });

        return back()->with('status', 'Навык куплен.');
    }

    /**
     * @return array<string, list<string>>
     */
    private function creationRules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:64'],
            'creature_species_id' => ['required', 'integer', 'exists:creature_species,id'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['integer', 'distinct', 'exists:skills,id'],
        ];

        foreach (Creature::SPECIAL_ATTRIBUTES as $attribute) {
            $rules[$attribute] = ['required', 'integer', 'min:1', 'max:'.Creature::STARTER_SPECIAL_CAP];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function creationMessages(): array
    {
        $messages = [
            'name.required' => 'Укажи имя сущности.',
            'name.min' => 'Имя сущности должно быть не короче 2 символов.',
            'name.max' => 'Имя сущности должно быть не длиннее 64 символов.',
            'creature_species_id.required' => 'Выбери вид сущности.',
            'creature_species_id.exists' => 'Выбранный вид сущности не найден.',
            'skills.array' => 'Навыки должны быть переданы списком.',
            'skills.*.distinct' => 'Один и тот же навык нельзя выбрать дважды.',
            'skills.*.exists' => 'Один из выбранных навыков не найден.',
        ];

        foreach (Creature::SPECIAL_ATTRIBUTES as $attribute) {
            $label = Creature::SPECIAL_LABELS[$attribute] ?? strtoupper($attribute);
            $messages[$attribute.'.required'] = "Укажи значение {$label}.";
            $messages[$attribute.'.integer'] = "{$label} должен быть целым числом.";
            $messages[$attribute.'.min'] = "{$label} не может быть меньше базы выбранного вида.";
            $messages[$attribute.'.max'] = "{$label} не может быть выше стартового cap ".Creature::STARTER_SPECIAL_CAP.'.';
        }

        return $messages;
    }

    private function starterSpecies(int $speciesId): CreatureSpecies
    {
        $species = CreatureSpecies::query()
            ->with('type')
            ->findOrFail($speciesId);

        if (! $species->is_active || ! $species->is_starter_available || ! $species->type?->is_active) {
            throw ValidationException::withMessages([
                'creature_species_id' => 'Выбранный вид недоступен для создания.',
            ]);
        }

        return $species;
    }

    /**
     * @param  array<int|string, mixed>  $rawSkillIds
     * @return Collection<int, int>
     */
    private function skillIds(array $rawSkillIds): Collection
    {
        return collect($rawSkillIds)
            ->map(fn (mixed $skillId): int => (int) $skillId)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, int>  $skillIds
     * @return Collection<int, Skill>
     */
    private function starterSkills(Collection $skillIds): Collection
    {
        if ($skillIds->isEmpty()) {
            return collect();
        }

        $skills = Skill::query()
            ->whereIn('id', $skillIds)
            ->get();

        if ($skills->count() !== $skillIds->count()) {
            throw ValidationException::withMessages([
                'skills' => 'Один из выбранных навыков не найден.',
            ]);
        }

        return $skills;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int>
     */
    private function validatedSpecialValues(array $attributes, CreatureSpecies $species): array
    {
        $special = [];

        foreach (Creature::SPECIAL_ATTRIBUTES as $attribute) {
            $value = (int) $attributes[$attribute];
            $baseValue = $species->baseSpecialValue($attribute);

            if ($value < $baseValue) {
                throw ValidationException::withMessages([
                    $attribute => 'Характеристика не может быть ниже базы выбранного вида.',
                ]);
            }

            $special[$attribute] = $value;
        }

        return $special;
    }

    /**
     * @param  array<string, int>  $special
     */
    private function statsCost(array $special, CreatureSpecies $species): int
    {
        return collect(Creature::SPECIAL_ATTRIBUTES)
            ->sum(fn (string $attribute): int => $special[$attribute] - $species->baseSpecialValue($attribute));
    }

    private function ensureCreationBudget(int $statsCost, int $skillCost): void
    {
        if (($statsCost + $skillCost) > Creature::CREATION_POINTS) {
            throw ValidationException::withMessages([
                'points' => 'Сумма потраченных очков не может быть больше '.Creature::CREATION_POINTS.'.',
            ]);
        }
    }

    /**
     * @param  Collection<int, Skill>  $skills
     */
    private function attachCreationSkills(Creature $creature, Collection $skills): void
    {
        if ($skills->count() > $creature->maxSkills()) {
            throw ValidationException::withMessages([
                'skills' => 'Выбрано слишком много навыков для первого уровня.',
            ]);
        }

        foreach ($skills as $skill) {
            if (! $skill->isAvailableFor($creature, duringCreation: true)) {
                throw ValidationException::withMessages([
                    'skills' => 'Один из выбранных навыков недоступен для этой сущности.',
                ]);
            }

            $creature->skills()->attach($skill->id, [
                'cost_paid' => $skill->cost,
                'source' => 'creation',
            ]);
        }
    }

    private function authorizeCreatureOwner(Request $request, Creature $creature): void
    {
        abort_unless($creature->user_id === $request->user()->id, 404);
    }
}
