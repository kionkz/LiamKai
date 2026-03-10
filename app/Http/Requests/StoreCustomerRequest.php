<?php

namespace App\Http\Requests;

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
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'type' => 'required|in:retail,wholesale',
            // credit_limit is required for wholesale, prohibited for retail, and capped at 15000
            'credit_limit' => [
                'nullable',
                'numeric',
                'min:0',
                'max:15000',
                'required_if:type,wholesale',
                'prohibited_if:type,retail',
            ],
        ];
    }
}
