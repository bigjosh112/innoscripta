<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Services\RabbitMQ\EmployeeEventPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\NullEmployeeEventPublisher;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(EmployeeEventPublisher::class, new NullEmployeeEventPublisher);
    }

    public function test_can_list_employees_empty(): void
    {
        $response = $this->getJson('/api/employees');

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_can_list_employees_with_country_filter(): void
    {
        Employee::factory()->count(2)->create(['country' => 'USA']);
        Employee::factory()->create(['country' => 'Germany']);

        $response = $this->getJson('/api/employees?country=USA');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $emp) {
            $this->assertSame('USA', $emp['country']);
        }
    }

    public function test_can_show_employee(): void
    {
        $employee = Employee::factory()->create([
            'name' => 'John',
            'last_name' => 'Doe',
            'country' => 'USA',
        ]);

        $response = $this->getJson("/api/employees/{$employee->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'John')
            ->assertJsonPath('data.last_name', 'Doe')
            ->assertJsonPath('data.country', 'USA');
    }

    public function test_can_create_usa_employee(): void
    {
        $payload = [
            'name'    => 'John',
            'last_name' => 'Doe',
            'country' => 'USA',
            'salary'  => 75000,
            'ssn'     => '123-45-6789',
            'address' => '123 Main St, New York, NY',
        ];

        $response = $this->postJson('/api/employees', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'John')
            ->assertJsonPath('data.country', 'USA')
            ->assertJsonPath('data.ssn', '123-45-6789');
        $this->assertDatabaseHas('employees', ['name' => 'John', 'country' => 'USA']);
    }

    public function test_can_create_germany_employee(): void
    {
        $payload = [
            'name'    => 'Hans',
            'last_name' => 'Mueller',
            'country' => 'Germany',
            'salary'  => 65000,
            'goal'    => 'Increase team productivity by 20%',
            'tax_id'  => 'DE123456789',
        ];

        $response = $this->postJson('/api/employees', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Hans')
            ->assertJsonPath('data.country', 'Germany')
            ->assertJsonPath('data.tax_id', 'DE123456789');
        $this->assertDatabaseHas('employees', ['name' => 'Hans', 'country' => 'Germany']);
    }

    public function test_create_usa_employee_requires_ssn_and_address(): void
    {
        $response = $this->postJson('/api/employees', [
            'name'      => 'John',
            'last_name' => 'Doe',
            'country'   => 'USA',
            'salary'    => 75000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ssn', 'address']);
    }

    public function test_create_germany_employee_requires_goal_and_tax_id(): void
    {
        $response = $this->postJson('/api/employees', [
            'name'      => 'Hans',
            'last_name' => 'Mueller',
            'country'   => 'Germany',
            'salary'    => 65000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['goal', 'tax_id']);
    }

    public function test_can_update_employee(): void
    {
        $employee = Employee::factory()->create([
            'name' => 'John',
            'salary' => 70000,
            'country' => 'USA',
        ]);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'salary' => 80000,
        ]);

        $response->assertStatus(200);
        $this->assertSame(80000, (int) $response->json('data.salary'));
        $employee->refresh();
        $this->assertSame(80000.0, (float) $employee->salary);
    }

    public function test_can_delete_employee(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->deleteJson("/api/employees/{$employee->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }

    public function test_show_returns_404_for_missing_employee(): void
    {
        $response = $this->getJson('/api/employees/99999');

        $response->assertStatus(404);
    }
}
