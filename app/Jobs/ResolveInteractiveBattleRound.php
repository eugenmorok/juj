<?php

namespace App\Jobs;

use App\Services\InteractiveBattleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class ResolveInteractiveBattleRound implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly int $battleId,
    ) {}

    public function handle(InteractiveBattleService $interactiveBattles): void
    {
        $interactiveBattles->processBattle($this->battleId);
    }
}
