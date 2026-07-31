<?php

namespace App\Http\Requests\Conversations;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'other_user_id' => 'required_without:property_id|integer|exists:users,id',
            'property_id' => 'required_without:other_user_id|integer|exists:properties,id',
            'user_one_id' => 'prohibited',
            'user_two_id' => 'prohibited',
            'sender_id' => 'prohibited',
        ];
    }
}
