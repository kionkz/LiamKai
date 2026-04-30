<?php

namespace App\Http\Requests\Orders;

use App\Models\Customer;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'customer_id' => 'required|exists:customers,id',
            'fulfillment_type' => 'required|in:delivery,pickup',
            'order_priority' => 'sometimes|in:regular,urgent',
            'scheduled_for' => 'required|date',
            'delivery_address' => 'nullable|string|max:255|required_if:fulfillment_type,delivery',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'sometimes|nullable|numeric|min:0',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $customer = Customer::find($this->input('customer_id'));
            $customerType = $customer?->type ?? 'retail';
            $items = $this->input('items', []);
            $priority = $this->input('order_priority', 'regular');

            if ($priority === 'urgent' && $this->filled('scheduled_for')) {
                $scheduledFor = Carbon::parse($this->input('scheduled_for'), config('app.timezone'));
                if (! $scheduledFor->isSameDay(now(config('app.timezone')))) {
                    $validator->errors()->add(
                        'scheduled_for',
                        'Rushed / urgent orders must be scheduled for today.'
                    );
                }
            }

            foreach ($items as $index => $item) {
                $quantity = isset($item['quantity']) ? (float) $item['quantity'] : null;
                $product = isset($item['product_id']) ? Product::find($item['product_id']) : null;
                $unitOfMeasure = $product?->unit_of_measure;

                if ($quantity === null || !$product) {
                    continue;
                }

                if ($unitOfMeasure !== 'kg') {
                    continue;
                }

                if ($customerType === 'retail' && $quantity >= 10) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        'Retail orders must be below 10kg per item.'
                    );
                }

                if ($customerType === 'wholesale' && $quantity < 10) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        'Wholesale orders must be at least 10kg per item.'
                    );
                }
            }
        });
    }
}
