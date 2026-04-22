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
            'email' => 'required|email|unique:customers,email',
            'phone' => ['required', 'regex:/^\+639\d{9}$/'],
            'address' => 'required|string',
            'type' => 'required|in:retail,wholesale',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('phone')) {
            return;
        }

        $digits = preg_replace('/\D+/', '', (string) $this->input('phone'));

        if (str_starts_with($digits, '09') && strlen($digits) === 11) {
            $digits = '63' . substr($digits, 1);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '63' . $digits;
        }

        $this->merge([
            'phone' => str_starts_with($digits, '63') ? '+' . $digits : $this->input('phone'),
        ]);
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone number must be in Philippine format (+639XXXXXXXXX).',
        ];
    }
}
