<?php

namespace App\Http\Requests;

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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255|unique:products,name,' . $this->route('product'),
            'category' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'unit_of_measure' => 'sometimes|required|string|max:50',
            'retail_price' => 'sometimes|nullable|numeric|min:0',
            'wholesale_price' => 'sometimes|nullable|numeric|min:0',
        ];
    }
}
