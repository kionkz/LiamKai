<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => ['required', 'regex:/^\d{11}$/'],
            'address' => 'required|string',
            'type' => 'required|in:retail,wholesale',
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = strtolower((string) $this->input('type', 'retail'));

        $this->merge([
            'phone' => preg_replace('/\D+/', '', (string) $this->input('phone')),
            'type' => in_array($type, ['retail', 'wholesale'], true) ? $type : $this->input('type'),
        ]);
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone number must contain exactly 11 digits.',
            'type.in' => 'Customer type must be Retail or Wholesale.',
        ];
    }
}
