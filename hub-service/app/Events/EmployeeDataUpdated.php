<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeDataUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $country,
        public readonly string $eventType,
        public readonly ?array $data = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('employees.' . $this->country),
        ];
    }

    public function broadcastAs(): string
    {
        return 'EmployeeDataUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'country' => $this->country,
            'event_type' => $this->eventType,
            'data' => $this->data,
            'message' => "Employee data updated for {$this->country}. Refresh or refetch employees.",
        ];
    }
}
