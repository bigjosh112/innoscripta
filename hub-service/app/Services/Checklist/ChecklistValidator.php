<?php

namespace App\Services\Checklist;

class ChecklistValidator
{
    /**
     * Country-specific required fields and validation rules.
     * USA: ssn, salary > 0, address
     * Germany: salary > 0, goal, tax_id (DE + 9 digits)
     *
     * @return array{complete: bool, fields: array<array{field: string, complete: bool, message: string}>}
     */
    public function validateEmployee(array $employee, string $country): array
    {
        $country = $this->normalizeCountry($country);
        $fields = [];

        if ($country === 'USA') {
            $fields = [
                $this->checkField($employee, 'ssn', 'SSN', fn ($v) => !empty(trim((string) $v))),
                $this->checkField($employee, 'salary', 'Salary', fn ($v) => isset($v) && (float) $v > 0),
                $this->checkField($employee, 'address', 'Address', fn ($v) => !empty(trim((string) $v))),
            ];
        }

        if ($country === 'Germany') {
            $fields = [
                $this->checkField($employee, 'salary', 'Salary', fn ($v) => isset($v) && (float) $v > 0),
                $this->checkField($employee, 'goal', 'Goal', fn ($v) => !empty(trim((string) $v))),
                $this->checkField($employee, 'tax_id', 'Tax ID', fn ($v) => preg_match('/^DE\d{9}$/', (string) $v) === 1),
            ];
        }

        $complete = count(array_filter($fields, fn ($f) => $f['complete'])) === count($fields);

        return [
            'complete' => $complete,
            'fields'   => $fields,
            'completion_percentage' => count($fields) > 0
                ? (int) round((count(array_filter($fields, fn ($f) => $f['complete'])) / count($fields)) * 100)
                : 0,
        ];
    }

    private function checkField(array $employee, string $key, string $label, callable $valid): array
    {
        $value = $employee[$key] ?? null;
        $complete = $valid($value);

        return [
            'field'    => $key,
            'label'    => $label,
            'complete' => $complete,
            'message'  => $complete ? "{$label} is complete" : "{$label} is required or invalid",
        ];
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
