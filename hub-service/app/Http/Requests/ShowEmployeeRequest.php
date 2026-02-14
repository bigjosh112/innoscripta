<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'country' => ['required', 'string', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country' => $this->query('country'),
        ]);
    }
}
