<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:customers,email,' . $this->route('customer'),
            'phone' => ['sometimes', 'required', 'regex:/^\+639\d{9}$/'],
            'address' => 'sometimes|required|string',
            'type' => 'sometimes|required|in:retail,wholesale',
            // For updates: credit_limit may be provided for wholesale (capped), and must be absent for retail
            'credit_limit' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:15000',
                'required_if:type,wholesale',
                'prohibited_if:type,retail',
            ],
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
