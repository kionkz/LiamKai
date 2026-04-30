<?php

namespace App\Http\Requests\Orders;

use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'order_type' => 'sometimes|in:retail,wholesale',
            'order_priority' => 'sometimes|in:regular,urgent',
            'fulfillment_type' => 'sometimes|in:delivery,pickup',
            'scheduled_for' => 'sometimes|nullable|date',
            'delivery_address' => 'sometimes|nullable|string|max:255',
            'delivery_date' => 'sometimes|nullable|date',
            'payment_status' => 'sometimes|in:paid,unpaid,partially_paid',
            'fulfillment_status' => 'sometimes|in:pending,in_progress,completed,cancelled',
            'delivery_status' => 'sometimes|in:pending,processing,delivered,cancelled',
            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateDeliveryAddress($validator);
            $this->validateUrgentSchedule($validator);
            $this->validateItemQuantityRules($validator);

            if (! $this->filled('order_type')) {
                return;
            }

            $order = Order::with('orderItems.product')->find($this->route('order'));

            if (! $order) {
                return;
            }

            $requestedOrderType = $this->input('order_type');

            foreach ($order->orderItems as $item) {
                if ($item->product?->unit_of_measure !== 'kg') {
                    continue;
                }

                $quantity = (float) $item->quantity;

                if ($requestedOrderType === 'retail' && $quantity >= 10) {
                    $validator->errors()->add(
                        'order_type',
                        'Cannot set order type to retail when any item has 10kg or more.'
                    );
                    return;
                }

                if ($requestedOrderType === 'wholesale' && $quantity < 10) {
                    $validator->errors()->add(
                        'order_type',
                        'Cannot set order type to wholesale when any item is below 10kg.'
                    );
                    return;
                }
            }
        });
    }

    private function validateUrgentSchedule(Validator $validator): void
    {
        $order = Order::find($this->route('order'));
        $priority = $this->input('order_priority', $order?->order_priority ?? 'regular');

        if ($priority !== 'urgent' || ! $this->filled('scheduled_for')) {
            return;
        }

        $scheduledFor = Carbon::parse($this->input('scheduled_for'), config('app.timezone'));
        if (! $scheduledFor->isSameDay(now(config('app.timezone')))) {
            $validator->errors()->add(
                'scheduled_for',
                'Rushed / urgent orders must stay scheduled for today.'
            );
        }
    }

    private function validateDeliveryAddress(Validator $validator): void
    {
        if ($this->input('fulfillment_type') !== 'delivery') {
            return;
        }

        $order = Order::find($this->route('order'));
        $deliveryAddress = $this->input('delivery_address', $order?->delivery_address);

        if (! is_string($deliveryAddress) || trim($deliveryAddress) === '') {
            $validator->errors()->add(
                'delivery_address',
                'Delivery address is required when fulfillment type is delivery.'
            );
        }
    }

    private function validateItemQuantityRules(Validator $validator): void
    {
        if (! $this->has('items')) {
            return;
        }

        $order = Order::with('customer')->find($this->route('order'));
        $customerType = $order?->customer?->type ?? $order?->order_type ?? 'retail';

        foreach ($this->input('items', []) as $index => $item) {
            $quantity = isset($item['quantity']) ? (float) $item['quantity'] : null;
            $product = isset($item['product_id']) ? Product::find($item['product_id']) : null;

            if ($quantity === null || ! $product || $product->unit_of_measure !== 'kg') {
                continue;
            }

            if ($customerType === 'retail' && $quantity >= 10) {
                $validator->errors()->add("items.{$index}.quantity", 'Retail orders must be below 10kg per item.');
            }

            if ($customerType === 'wholesale' && $quantity < 10) {
                $validator->errors()->add("items.{$index}.quantity", 'Wholesale orders must be at least 10kg per item.');
            }
        }
    }
}
