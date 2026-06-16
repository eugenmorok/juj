<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('battle_events')) {
            return;
        }

        DB::table('battle_events')
            ->select(['id', 'text_log'])
            ->whereNotNull('text_log')
            ->orderBy('id')
            ->chunkById(100, function ($events): void {
                foreach ($events as $event) {
                    $fixed = $this->repairMojibake((string) $event->text_log);

                    if ($fixed === $event->text_log) {
                        continue;
                    }

                    DB::table('battle_events')
                        ->where('id', $event->id)
                        ->update(['text_log' => $fixed]);
                }
            });
    }

    public function down(): void
    {
        // Data repair is intentionally not reversible.
    }

    private function repairMojibake(string $value): string
    {
        $fixed = $value;

        for ($i = 0; $i < 3 && $this->looksLikeMojibake($fixed); $i++) {
            $candidate = @iconv('UTF-8', 'Windows-1251//IGNORE', $fixed);

            if (! is_string($candidate) || $candidate === '' || ! mb_check_encoding($candidate, 'UTF-8')) {
                break;
            }

            $fixed = $candidate;
        }

        return $fixed;
    }

    private function looksLikeMojibake(string $value): bool
    {
        foreach (['Рџ', 'Р ', 'РЎ', 'Р‘', 'Р’', 'Рђ', 'РЁ', 'Р—', 'Р­', 'СЃ', 'С‚', 'СЊ', 'СЏ', 'С‹', 'С€'] as $fragment) {
            if (str_contains($value, $fragment)) {
                return true;
            }
        }

        return false;
    }
};
