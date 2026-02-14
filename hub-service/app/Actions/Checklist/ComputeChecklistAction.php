<?php

namespace App\Actions\Checklist;

use App\Services\Checklist\ChecklistValidator;
use App\Services\HrServiceClient;

class ComputeChecklistAction
{
    public function __construct(
        private readonly HrServiceClient $hrClient,
        private readonly ChecklistValidator $validator
    ) {}

    /**
     * @return array{overall: array{total: int, complete: int, percentage: int}, employees: array<int, array>}
     */
    public function execute(string $country): array
    {
        $employees = $this->hrClient->getAllEmployees($country);

        $employeeChecklists = [];
        $completeCount = 0;

        foreach ($employees as $emp) {
            $validation = $this->validator->validateEmployee($emp, $country);
            $employeeChecklists[] = [
                'id'                   => $emp['id'] ?? null,
                'name'                 => trim(($emp['name'] ?? '') . ' ' . ($emp['last_name'] ?? '')),
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
