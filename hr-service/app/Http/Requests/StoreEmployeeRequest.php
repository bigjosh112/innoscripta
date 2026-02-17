<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
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
        $country = $this->input('country');

        $rules = [
            'name'    => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'country' => 'required|in:USA,Germany',
            'salary'  => 'nullable|numeric|min:0.01',
            'ssn'     => 'nullable|string|max:255|unique:employees,ssn',
            'address' => 'nullable|string|max:255',
            'goal'    => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255|unique:employees,tax_id',

        ];

        // if ($country === 'USA') {
        //     $rules['ssn'] = 'required|string|max:255';
        //     $rules['address'] = 'required|string|max:255';
        // }

        // if ($country === 'Germany') {
        //     $rules['goal'] = 'required|string|max:255';
        //     $rules['tax_id'] = 'required|string|regex:/^DE\d{9}$/';
        // }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'tax_id.regex' => 'The tax ID must be DE followed by exactly 9 digits.',
        ];
    }
}
