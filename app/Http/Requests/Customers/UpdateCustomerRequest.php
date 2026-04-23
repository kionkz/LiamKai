<?php

namespace App\Http\Requests\Customers;

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
            'email' => 'sometimes|nullable|email|unique:customers,email,' . $this->route('customer'),
            'phone' => ['sometimes', 'required', 'regex:/^\d{11}$/'],
            'address' => 'sometimes|required|string',
            'type' => 'sometimes|required|in:retail,wholesale',
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = strtolower((string) $this->input('type', ''));

        $updates = [];

        if ($this->has('phone')) {
            $updates['phone'] = preg_replace('/\D+/', '', (string) $this->input('phone'));
        }

        if ($this->has('type')) {
            $updates['type'] = in_array($type, ['retail', 'wholesale'], true) ? $type : $this->input('type');
        }

        if ($updates !== []) {
            $this->merge($updates);
        }
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone number must contain exactly 11 digits.',
            'type.in' => 'Customer type must be Retail or Wholesale.',
        ];
    }
}
