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
                ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'home', 'order' => 1, 'path' => '/dashboard'],
                ['id' => 'employees', 'label' => 'Employees', 'icon' => 'users', 'order' => 2, 'path' => '/employees'],
            ],
            'Germany' => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'home', 'order' => 1, 'path' => '/dashboard'],
                ['id' => 'employees', 'label' => 'Employees', 'icon' => 'users', 'order' => 2, 'path' => '/employees'],
                ['id' => 'documentation', 'label' => 'Documentation', 'icon' => 'book', 'order' => 3, 'path' => '/documentation'],
            ],
            default => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'home', 'order' => 1, 'path' => '/dashboard'],
                ['id' => 'employees', 'label' => 'Employees', 'icon' => 'users', 'order' => 2, 'path' => '/employees'],
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
