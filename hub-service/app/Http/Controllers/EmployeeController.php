<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexEmployeesRequest;
use App\Http\Requests\ShowEmployeeRequest;
use App\Services\HrServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly HrServiceClient $hrClient
    ) {}

    public function index(IndexEmployeesRequest $request): JsonResponse
    {
        $country = $request->validated('country');
        $page = $request->integer('page', 1);
        $perPage = $request->integer('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $cacheKey = "employees:{$country}:{$page}:{$perPage}";
        try {
            $result = Cache::remember($cacheKey, 60, function () use ($country, $page, $perPage) {
                return $this->fetchAndTransform($country, $page, $perPage);
            });
        } catch (\Throwable $e) {
            Log::error('Employee list fetch failed', ['country' => $country, 'exception' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to load employees'], 503);
        }

        return response()->json($result);
    }

    public function show(ShowEmployeeRequest $request, int $id): JsonResponse
    {
        $country = $request->validated('country');

        $cacheKey = "employees:{$country}:{$id}";
        try {
            $result = Cache::remember($cacheKey, 60, function () use ($id, $country) {
                $emp = $this->hrClient->getEmployee($id, $country);
                $columns = $this->getColumnsForCountry($country);
                return [
                    'data'    => $this->transformEmployee($emp, $country),
                    'columns' => $columns,
                ];
            });
        } catch (\Throwable $e) {
            Log::error('Employee fetch failed', ['id' => $id, 'country' => $country, 'exception' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to load employee'], 503);
        }

        return response()->json($result);
    }

    private function fetchAndTransform(string $country, int $page, int $perPage): array
    {
        $response = $this->hrClient->getEmployees($country, $page, $perPage);
        $data = $response['data'] ?? [];
        $meta = $response['meta'] ?? [];

        $columns = $this->getColumnsForCountry($country);
        $transformed = array_map(fn (array $emp) => $this->transformEmployee($emp, $country), $data);

        return [
            'data'       => $transformed,
            'meta'       => $meta,
            'columns'    => $columns,
        ];
    }

    private function getColumnsForCountry(string $country): array
    {
        $normalized = strtoupper($country) === 'USA' ? 'USA' : 'Germany';

        return match ($normalized) {
            'USA' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'string'],
                ['key' => 'last_name', 'label' => 'Last Name', 'type' => 'string'],
                ['key' => 'salary', 'label' => 'Salary', 'type' => 'number'],
                ['key' => 'ssn_masked', 'label' => 'SSN', 'type' => 'string'],
            ],
            'Germany' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'string'],
                ['key' => 'last_name', 'label' => 'Last Name', 'type' => 'string'],
                ['key' => 'salary', 'label' => 'Salary', 'type' => 'number'],
                ['key' => 'goal', 'label' => 'Goal', 'type' => 'string'],
            ],
            default => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'string'],
                ['key' => 'last_name', 'label' => 'Last Name', 'type' => 'string'],
                ['key' => 'salary', 'label' => 'Salary', 'type' => 'number'],
            ],
        };
    }

    private function transformEmployee(array $emp, string $country): array
    {
        $normalized = strtoupper($country) === 'USA' ? 'USA' : 'Germany';

        $base = [
            'id'        => $emp['id'] ?? null,
            'name'      => $emp['name'] ?? '',
            'last_name' => $emp['last_name'] ?? '',
            'salary'    => $emp['salary'] ?? null,
            'country'   => $emp['country'] ?? $country,
        ];

        if ($normalized === 'USA') {
            $ssn = $emp['ssn'] ?? '';
            $base['ssn_masked'] = $this->maskSsn($ssn);
        }

        if ($normalized === 'Germany') {
            $base['goal'] = $emp['goal'] ?? '';
        }

        return $base;
    }

    private function maskSsn(string $ssn): string
    {
        $digits = preg_replace('/\D/', '', $ssn);
        if (strlen($digits) < 4) {
            return '***-**-****';
        }
        return '***-**-' . substr($digits, -4);
    }
}
