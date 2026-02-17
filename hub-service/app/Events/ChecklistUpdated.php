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

    /**
     * @param  array<int, string>  $missingFields  Messages for incomplete fields (e.g. "Salary is required or invalid")
     */
    public function __construct(
        public readonly string $country,
        public readonly string $eventType,
        public readonly array $missingFields = []
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
        $complete = empty($this->missingFields);
        $message = $complete
            ? "Checklist updated for {$this->country}. All data complete."
            : "Checklist data invalidated for {$this->country}. Some items need attention.";

        return [
            'country' => $this->country,
            'event_type' => $this->eventType,
            'message' => $message,
            'missing_fields' => $this->missingFields,
        ];
    }
}
