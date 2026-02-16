<?php

namespace Tests\Unit;

use App\Services\Checklist\ChecklistValidator;
use PHPUnit\Framework\TestCase;

class ChecklistValidatorTest extends TestCase
{
    private ChecklistValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ChecklistValidator();
    }

    public function test_usa_employee_complete(): void
    {
        $employee = [
            'id' => 1,
            'name' => 'John',
            'last_name' => 'Doe',
            'ssn' => '123-45-6789',
            'salary' => 75000,
            'address' => '123 Main St',
            'country' => 'USA',
        ];
        $result = $this->validator->validateEmployee($employee, 'USA');
        $this->assertTrue($result['complete']);
        $this->assertSame(100, $result['completion_percentage']);
        $this->assertCount(3, $result['fields']);
    }

    public function test_usa_employee_missing_ssn(): void
    {
        $employee = [
            'id' => 1,
            'salary' => 75000,
            'address' => '123 Main St',
            'country' => 'USA',
        ];
        $result = $this->validator->validateEmployee($employee, 'USA');
        $this->assertFalse($result['complete']);
        $ssnField = collect($result['fields'])->firstWhere('field', 'ssn');
        $this->assertNotNull($ssnField);
        $this->assertFalse($ssnField['complete']);
    }

    public function test_germany_employee_complete(): void
    {
        $employee = [
            'id' => 2,
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'goal' => 'Increase productivity',
            'tax_id' => 'DE123456789',
            'country' => 'Germany',
        ];
        $result = $this->validator->validateEmployee($employee, 'Germany');
        $this->assertTrue($result['complete']);
        $this->assertSame(100, $result['completion_percentage']);
    }

    public function test_germany_tax_id_invalid_format(): void
    {
        $employee = [
            'id' => 2,
            'salary' => 65000,
            'goal' => 'Goal',
            'tax_id' => 'DE123', // not 9 digits
            'country' => 'Germany',
        ];
        $result = $this->validator->validateEmployee($employee, 'Germany');
        $this->assertFalse($result['complete']);
        $taxField = collect($result['fields'])->firstWhere('field', 'tax_id');
        $this->assertFalse($taxField['complete']);
    }
}
