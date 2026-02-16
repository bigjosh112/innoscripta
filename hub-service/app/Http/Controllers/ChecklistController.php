<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexChecklistRequest;
use App\Http\Resources\ChecklistResource;
use App\Services\Checklist\ChecklistValidator;
use App\Services\HrServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChecklistController extends Controller
{
    public function __construct(
        private readonly HrServiceClient $hrClient,
        private readonly ChecklistValidator $validator
    ) {}

    public function index(IndexChecklistRequest $request): JsonResponse
    {
        $country = $request->validated('country');

        $cacheKey = "checklist:country:{$country}";
        try {
            $result = Cache::remember($cacheKey, 60, fn () => $this->computeChecklist($country));
        } catch (\Throwable $e) {
            Log::error('Checklist computation failed', ['country' => $country, 'exception' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to load checklist'], 503);
        }

        return (new ChecklistResource($result))->response();
    }

    private function computeChecklist(string $country): array
    {
        $employees = $this->hrClient->getAllEmployees($country);

        $employeeChecklists = [];
        $completeCount = 0;

        foreach ($employees as $emp) {
            $validation = $this->validator->validateEmployee($emp, $country);
            $employeeChecklists[] = [
                'id'                   => $emp['id'] ?? null,
                'name'                 => ($emp['name'] ?? '') . ' ' . ($emp['last_name'] ?? ''),
                'checklist'            => $validation['fields'],
                'completion_percentage' => $validation['completion_percentage'],
                'complete'             => $validation['complete'],
            ];
            if ($validation['complete']) {
                $completeCount++;
            }
        }

        $total = count($employeeChecklists);
        $overall = [
            'total'      => $total,
            'complete'   => $completeCount,
            'percentage' => $total > 0 ? (int) round(($completeCount / $total) * 100) : 0,
        ];

        return [
            'overall'   => $overall,
            'employees' => $employeeChecklists,
        ];
    }
}
