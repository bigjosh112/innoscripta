<?php

namespace App\Services\Schema;

class SchemaConfig
{
    /**
     * @return array<array{id: string, type: string, title: string, data_source?: string, channel?: string}>
     */
    public function getSchema(string $stepId, string $country): array
    {
        $country = $this->normalizeCountry($country);

        if ($stepId === 'dashboard') {
            return match ($country) {
                'USA' => [
                    ['id' => 'employee_count', 'type' => 'stat', 'title' => 'Employee Count', 'data_source' => '/api/checklists?country=USA', 'channel' => 'checklist.USA'],
                    ['id' => 'average_salary', 'type' => 'stat', 'title' => 'Average Salary', 'data_source' => '/api/checklists?country=USA', 'channel' => 'checklist.USA'],
                    ['id' => 'completion_rate', 'type' => 'stat', 'title' => 'Completion Rate', 'data_source' => '/api/checklists?country=USA', 'channel' => 'checklist.USA'],
                ],
                'Germany' => [
                    ['id' => 'employee_count', 'type' => 'stat', 'title' => 'Employee Count', 'data_source' => '/api/checklists?country=Germany', 'channel' => 'checklist.Germany'],
                    ['id' => 'goal_tracking', 'type' => 'list', 'title' => 'Goal Tracking', 'data_source' => '/api/employees?country=Germany', 'channel' => 'employees.Germany'],
                ],
                default => [
                    ['id' => 'employee_count', 'type' => 'stat', 'title' => 'Employee Count', 'data_source' => "/api/checklists?country={$country}", 'channel' => "checklist.{$country}"],
                ],
            };
        }

        if ($stepId === 'employees') {
            return [
                ['id' => 'employee_list', 'type' => 'table', 'title' => 'Employees', 'data_source' => "/api/employees?country={$country}", 'channel' => "employees.{$country}"],
            ];
        }

        return [];
    }

    private function normalizeCountry(string $country): string
    {
        return match (strtoupper($country)) {
            'USA' => 'USA',
            'DE', 'DEU', 'GERMANY' => 'Germany',
            default => $country,
        };
    }
}
