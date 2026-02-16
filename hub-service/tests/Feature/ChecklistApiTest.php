<?php

namespace Tests\Feature;

use App\Services\HrServiceClient;
use Tests\TestCase;

class ChecklistApiTest extends TestCase
{
    public function test_checklists_requires_country(): void
    {
        $response = $this->getJson('/api/checklists');
        $response->assertStatus(422);
    }

    public function test_checklists_returns_overall_and_employees_for_usa(): void
    {
        $this->mock(HrServiceClient::class, function ($mock) {
            $mock->shouldReceive('getAllEmployees')
                ->with('USA')
                ->once()
                ->andReturn([
                    [
                        'id' => 1,
                        'name' => 'John',
                        'last_name' => 'Doe',
                        'ssn' => '123-45-6789',
                        'salary' => 75000,
                        'address' => '123 Main St',
                        'country' => 'USA',
                    ],
                ]);
        });

        $response = $this->getJson('/api/checklists?country=USA');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'overall' => ['total', 'complete', 'percentage'],
            'employees' => [
                ['id', 'name', 'checklist', 'completion_percentage', 'complete'],
            ],
        ]);
        $this->assertSame(1, $response->json('overall.total'));
        $this->assertSame(1, $response->json('overall.complete'));
    }
}
