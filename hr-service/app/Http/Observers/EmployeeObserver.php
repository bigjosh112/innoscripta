<?php

namespace App\Http\Observers;

use App\Enums\EmployeeEventTypeEnum;
use App\Models\Employee;
use App\Services\StreamConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

class EmployeeObserver
{
    private const minutes = 5;

    /**
     * Handle the Employee "created" event.
     */
    public function created(Employee $employee): void
    {
        $this->sendMessage(EmployeeEventTypeEnum::CREATED, $employee);
    }

    /**
     * Handle the Employee "updated" event.
     */
    public function updated(Employee $employee): void
    {
        // Get the changed fields
        $changed = $employee->getChanges();

        $this->sendMessage(EmployeeEventTypeEnum::UPDATED, $employee, $changed);
    }

    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        $this->sendMessage(EmployeeEventTypeEnum::DELETED, $employee);
    }

    /**
     * Sends the payload to RabbitMQ.
     */
    private function sendMessage(
        EmployeeEventTypeEnum $type,
        Employee $employee,
        array $changedFields = []
    ): void {
        $payload = [
            'event_type' => $type->value,
            'event_id'   => (string) Str::uuid(),
            'timestamp'  => now()->toIso8601String(),
            'country'    => $employee->country,
            'data'       => [
                'employee_id'    => $employee->id,
                'changed_fields' => $changedFields,
                'employee'       => $employee->toArray(),
            ],
        ];

        $message = json_encode($payload);

        try {
            app(StreamConnection::class)->sendMessage('employee_events', $message);
        } catch (\Exception $e) {
            Log::error('Failed to send employee event to RabbitMQ: ' . $e->getMessage());
        }
    }
}
