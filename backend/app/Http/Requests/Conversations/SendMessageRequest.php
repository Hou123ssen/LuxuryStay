<?php

namespace App\Http\Requests\Conversations;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conversation_id' => 'required|integer|exists:conversations,id',
            'body' => 'required|string|max:2000',
            'sender_id' => 'prohibited',
        ];
    }
}
