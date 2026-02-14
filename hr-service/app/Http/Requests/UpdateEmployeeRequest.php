<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');
        $country = $this->input('country', $employee->country);

        $rules = [
            'name'      => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'country'   => 'sometimes|in:USA,Germany',
            'salary'    => 'sometimes|numeric|min:0.01',
            'ssn'       => 'sometimes|nullable|string|max:255',
            'address'   => 'sometimes|nullable|string|max:255',
            'goal'      => 'sometimes|nullable|string|max:255',
            'tax_id'    => 'sometimes|nullable|string|max:255',
        ];

        if ($country === 'Germany') {
            $rules['tax_id'] = 'sometimes|nullable|string|regex:/^DE\d{9}$/';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'tax_id.regex' => 'The tax ID must be DE followed by exactly 9 digits.',
        ];
    }
}
