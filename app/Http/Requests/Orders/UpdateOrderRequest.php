<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('payment_status') === 'utang') {
            $this->merge(['payment_status' => 'partially_paid']);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notes' => 'nullable|string',
            'fulfillment_type' => 'sometimes|in:delivery,pickup',
            'scheduled_for' => 'sometimes|nullable|date',
            'delivery_address' => 'sometimes|nullable|string|max:255',
            'delivery_date' => 'sometimes|nullable|date',
            'payment_status' => 'sometimes|in:paid,unpaid,partially_paid',
            'fulfillment_status' => 'sometimes|in:pending,in_progress,completed,cancelled',
            'delivery_status' => 'sometimes|in:pending,processing,delivered,cancelled',
        ];
    }
}
