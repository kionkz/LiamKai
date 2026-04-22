<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255|unique:products,name,' . $this->route('product'),
            'category_id' => 'sometimes|required|integer|exists:categories,id',
            'description' => 'nullable|string',
            'unit_of_measure' => 'sometimes|required|string|in:kg,Per pack',
            'retail_price' => 'sometimes|numeric|min:0',
            'discount_percent' => 'sometimes|nullable|numeric|min:0|max:100',
        ];
    }
}
