<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class CreateEmployeeAction
{
    public function execute(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            return Employee::create($data);
            // EmployeeObserver::created() runs automatically and publishes to RabbitMQ
        });
    }
}


