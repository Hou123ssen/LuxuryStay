<?php

namespace App\Http\Requests\Properties;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'type' => 'sometimes|in:apartment,hotel,residence',
            'price_per_night' => 'sometimes|numeric|min:0',
            'city' => 'sometimes|string',
            'address' => 'sometimes|string',
        ];
    }
}
