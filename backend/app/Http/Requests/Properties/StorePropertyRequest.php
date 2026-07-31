<?php

namespace App\Http\Requests\Properties;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:apartment,hotel,residence',
            'price_per_night' => 'required|numeric|min:0',
            'city' => 'required|string',
            'address' => 'required|string',
        ];
    }
}
