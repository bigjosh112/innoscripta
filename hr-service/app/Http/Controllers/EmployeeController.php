<?php

namespace App\Http\Controllers;

use App\Actions\Employees\CreateEmployeeAction;
use App\Actions\Employees\DeleteEmployeeAction;
use App\Actions\Employees\UpdateEmployeeAction;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::query()->orderBy('id');

        if ($request->filled('country')) {
            $query->where('country', $request->string('country'));
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        return EmployeeResource::collection($query->paginate($perPage));
    }

    public function show(Employee $employee)
    {
        return new EmployeeResource($employee);
    }

    public function store(
        StoreEmployeeRequest $request,
        CreateEmployeeAction $action
    ) {
        $employee = $action->execute($request->validated());

        return new EmployeeResource($employee);
    }

    public function update(
        UpdateEmployeeRequest $request,
        Employee $employee,
        UpdateEmployeeAction $action
    ) {
        $employee = $action->execute($employee, $request->validated());

        return new EmployeeResource($employee);
    }

    public function destroy(
        Employee $employee,
        DeleteEmployeeAction $action
    ) {
        $action->execute($employee);

        return response()->noContent();
    }
}

