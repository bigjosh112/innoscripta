<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChecklistUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $country,
        public readonly string $eventType
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('checklist.' . $this->country),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ChecklistUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'country' => $this->country,
            'event_type' => $this->eventType,
            'message' => "Checklist data invalidated for {$this->country}. Refresh or refetch checklist.",
        ];
    }
}
