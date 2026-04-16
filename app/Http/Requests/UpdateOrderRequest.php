<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Order;

class UpdateOrderRequest extends FormRequest
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
            'type' => 'sometimes|in:retail,wholesale',
            'order_type' => 'sometimes|in:retail,wholesale',
            'notes' => 'nullable|string',
            'delivery_address' => 'sometimes|nullable|string|max:255',
            'delivery_date' => 'sometimes|nullable|date',
            'payment_status' => 'sometimes|in:pending,partial,paid,overdue',
            'delivery_status' => 'sometimes|in:pending,processing,delivered,cancelled',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $requestedType = $this->input('order_type', $this->input('type'));
            if (!$requestedType) {
                return;
            }

            $orderId = $this->route('order');
            $order = Order::with('orderItems')->find($orderId);
            if (!$order) {
                return;
            }

            foreach ($order->orderItems as $index => $item) {
                $quantity = (float) $item->quantity;

                if ($requestedType === 'retail' && $quantity >= 10) {
                    $validator->errors()->add(
                        'order_type',
                        'Cannot set order type to retail when any item has 10kg or more.'
                    );
                    return;
                }

                if ($requestedType === 'wholesale' && $quantity < 10) {
                    $validator->errors()->add(
                        'order_type',
                        'Cannot set order type to wholesale when any item is below 10kg.'
                    );
                    return;
                }
            }
        });
    }
}
