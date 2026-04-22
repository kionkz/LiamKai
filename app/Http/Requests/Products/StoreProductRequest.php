<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // In production, check if user has permission
    }

    protected function prepareForValidation(): void
    {
        $unitOfMeasure = $this->input('unit_of_measure');

        $normalizedUnit = match ($unitOfMeasure) {
            'by kg' => 'kg',
            'per pack', 'Per Pack' => 'Per pack',
            default => $unitOfMeasure,
        };

        $this->merge([
            'unit_of_measure' => $normalizedUnit,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:products,name',
            'category_id' => 'required|integer|exists:categories,id',
            'description' => 'nullable|string',
            'unit_of_measure' => 'required|string|in:kg,Per pack',
            'retail_price' => 'required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
