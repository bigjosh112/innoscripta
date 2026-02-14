<?php

namespace App\Http\Controllers;

use App\Services\Checklist\ChecklistValidator;
use App\Services\HrServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ChecklistController extends Controller
{
    public function __construct(
        private readonly HrServiceClient $hrClient,
        private readonly ChecklistValidator $validator
    ) {}

    public function index(Request $request): JsonResponse
    {
        $country = $request->query('country');
        if (empty($country)) {
            return response()->json(['error' => 'country query parameter is required'], 422);
        }

        $cacheKey = "checklist:country:{$country}";
        $result = Cache::remember($cacheKey, 60, fn () => $this->computeChecklist($country));

        return response()->json($result);
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
