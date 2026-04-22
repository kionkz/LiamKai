<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
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
            'order_id' => 'required|exists:orders,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,check,bank_transfer,credit,gcash',
            'payment_date' => 'nullable|date',
            'deposit_date' => 'required_if:payment_method,check|nullable|date',
            'check_from' => 'required_if:payment_method,check|nullable|string|max:255',
            'bank_name' => 'required_if:payment_method,bank_transfer|nullable|string|max:255',
            'reference' => 'required_if:payment_method,gcash,bank_transfer,check|nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'deposit_date.required_if' => 'Deposit date is required for check payments.',
            'check_from.required_if' => 'Check issuer is required for check payments.',
            'bank_name.required_if' => 'Bank name is required for bank transfer payments.',
            'reference.required_if' => 'Reference number is required for GCash, bank transfer, and check payments.',
        ];
    }
}
