<?php

use App\Models\Creature;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $attributes = Creature::SPECIAL_ATTRIBUTES;
        $specialPointBudget = static fn (string $style, int $level): int => $style === 'economical'
            ? 45 + ((max(1, $level) - 1) * 6)
            : 60 + ((max(1, $level) - 1) * 8);

        DB::table('creatures as creatures')
            ->join('users', 'users.id', '=', 'creatures.user_id')
            ->join('creature_species as species', 'species.id', '=', 'creatures.creature_species_id')
            ->leftJoin('bot_profiles as profiles', 'profiles.user_id', '=', 'users.id')
            ->where('users.is_bot', true)
            ->select([
                'creatures.id',
                'creatures.level',
                'creatures.current_hp',
                'creatures.max_hp',
                'profiles.style',
                ...collect($attributes)->map(fn (string $attribute): string => "creatures.{$attribute}")->all(),
                ...collect($attributes)->map(fn (string $attribute): string => "species.base_{$attribute}")->all(),
            ])
            ->orderBy('creatures.id')
            ->each(function (object $creature) use ($attributes, $specialPointBudget): void {
                $style = $creature->style ?: 'balanced';
                $targetBudget = $specialPointBudget($style, (int) $creature->level);
                $deltas = collect($attributes)->mapWithKeys(fn (string $attribute): array => [
                    $attribute => max(0, (int) $creature->{$attribute} - (int) $creature->{"base_{$attribute}"}),
                ]);
                $currentBudget = $deltas->sum();

                if ($currentBudget <= $targetBudget) {
                    return;
                }

                $scaled = $deltas->map(fn (int $delta): int => (int) floor($delta * $targetBudget / $currentBudget));
                $remaining = $targetBudget - $scaled->sum();
                $priority = $attributes;

                usort($priority, function (string $left, string $right) use ($deltas, $targetBudget, $currentBudget): int {
                    $leftFraction = ($deltas[$left] * $targetBudget / $currentBudget) - floor($deltas[$left] * $targetBudget / $currentBudget);
                    $rightFraction = ($deltas[$right] * $targetBudget / $currentBudget) - floor($deltas[$right] * $targetBudget / $currentBudget);

                    return $rightFraction <=> $leftFraction;
                });

                for ($index = 0; $index < $remaining; $index++) {
                    $attribute = $priority[$index % count($priority)];
                    $scaled->put($attribute, $scaled[$attribute] + 1);
                }

                $updates = $scaled->mapWithKeys(fn (int $delta, string $attribute): array => [
                    $attribute => (int) $creature->{"base_{$attribute}"} + $delta,
                ])->all();

                $oldMaxHp = max(1, (int) $creature->max_hp);
                $healthRate = min(1, max(0, (int) $creature->current_hp / $oldMaxHp));
                $newMaxHp = Creature::maxHpForEndurance($updates['endurance']) + ((max(1, (int) $creature->level) - 1) * 5);
                $updates['max_hp'] = $newMaxHp;
                $updates['current_hp'] = max(0, min($newMaxHp, (int) round($newMaxHp * $healthRate)));
                $updates['updated_at'] = now();

                DB::table('creatures')->where('id', $creature->id)->update($updates);
            });
    }

    public function down(): void
    {
        // Previous generated SPECIAL values cannot be reconstructed safely.
    }
};
