<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class DeleteEmployeeAction
{
    public function execute(Employee $employee): void
    {
        DB::transaction(function () use ($employee) {
            $employee->delete();
            // EmployeeObserver::deleted() runs automatically and publishes to RabbitMQ
        });
    }
}

