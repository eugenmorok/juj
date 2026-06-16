<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BattleStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $battleId,
        public readonly string $status,
        public readonly int $currentRound,
        public readonly ?string $turnDeadlineAt,
        public readonly ?int $latestEventId,
    ) {}

    public function broadcastAs(): string
    {
        return 'battle.state.updated';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('battle.'.$this->battleId),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function broadcastWith(): array
    {
        return [
            'battle_id' => $this->battleId,
            'status' => $this->status,
            'current_round' => $this->currentRound,
            'turn_deadline_at' => $this->turnDeadlineAt,
            'latest_event_id' => $this->latestEventId,
        ];
    }
}
