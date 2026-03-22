<?php

namespace App\Http\Requests;

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
            'type' => 'sometimes|in:retail,wholesale',
            'order_type' => 'sometimes|in:retail,wholesale',
            'is_urgent' => 'sometimes|boolean',
            'delivery_address' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $orderType = $this->input('order_type', $this->input('type', 'retail'));
            $items = $this->input('items', []);

            foreach ($items as $index => $item) {
                $quantity = isset($item['quantity']) ? (float) $item['quantity'] : null;

                if ($quantity === null) {
                    continue;
                }

                if ($orderType === 'retail' && $quantity >= 10) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        'Retail orders must be below 10kg per item.'
                    );
                }

                if ($orderType === 'wholesale' && $quantity < 10) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        'Wholesale orders must be at least 10kg per item.'
                    );
                }
            }
        });
    }
}
