<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class HrServiceClient
{
    public function __construct(
        private readonly string $baseUrl
    ) {}

    /**
     * @return array{data: array, meta?: array}
     */
    public function getEmployees(?string $country = null, int $page = 1, int $perPage = 15): array
    {
        $response = Http::timeout(10)->get("{$this->baseUrl}/api/employees", [
            'country'   => $country,
            'page'      => $page,
            'per_page'  => $perPage,
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function getEmployee(int $id, ?string $country = null): array
    {
        $url = "{$this->baseUrl}/api/employees/{$id}";
        $query = $country !== null ? ['country' => $country] : [];
        $response = Http::timeout(10)->get($url, $query);

        $response->throw();

        return $response->json('data') ?? $response->json();
    }

    /**
     * @return array
     */
    public function getAllEmployees(?string $country = null): array
    {
        $employees = [];
        $page = 1;

        do {
            $result = $this->getEmployees($country, $page, 100);
            $data = $result['data'] ?? [];
            $employees = array_merge($employees, $data);
            $meta = $result['meta'] ?? [];
            $lastPage = $meta['last_page'] ?? 1;
            $page++;
        } while ($page <= $lastPage);

        return $employees;
    }
}
