<?php

namespace Tests\Fakes;

use App\Enums\EmployeeEventTypeEnum;
use App\Models\Employee;
use App\Services\RabbitMQ\EmployeeEventPublisher;

class NullEmployeeEventPublisher implements EmployeeEventPublisher
{
    public function publish(
        EmployeeEventTypeEnum $type,
        Employee $employee,
        array $changedFields
    ): void {
        // No-op for tests (no RabbitMQ required)
    }
}
