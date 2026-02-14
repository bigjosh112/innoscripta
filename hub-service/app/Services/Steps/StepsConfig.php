<?php

namespace App\Services\Steps;

class StepsConfig
{
    /**
     * @return array<array{id: string, label: string, path: string, order: int, icon?: string}>
     */
    public function getSteps(string $country): array
    {
        $country = $this->normalizeCountry($country);

        return match ($country) {
            'USA' => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'path' => '/dashboard', 'order' => 1, 'icon' => 'dashboard'],
                ['id' => 'employees', 'label' => 'Employees', 'path' => '/employees', 'order' => 2, 'icon' => 'users'],
            ],
            'Germany' => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'path' => '/dashboard', 'order' => 1, 'icon' => 'dashboard'],
                ['id' => 'employees', 'label' => 'Employees', 'path' => '/employees', 'order' => 2, 'icon' => 'users'],
                ['id' => 'documentation', 'label' => 'Documentation', 'path' => '/documentation', 'order' => 3, 'icon' => 'document'],
            ],
            default => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'path' => '/dashboard', 'order' => 1],
                ['id' => 'employees', 'label' => 'Employees', 'path' => '/employees', 'order' => 2],
            ],
        };
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
