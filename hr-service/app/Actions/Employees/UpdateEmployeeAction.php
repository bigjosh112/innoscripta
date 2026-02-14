<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class UpdateEmployeeAction
{
    public function execute(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee->update($data);
            return $employee->fresh();
            // EmployeeObserver::updated() runs automatically and publishes to RabbitMQ
        });
    }
}

