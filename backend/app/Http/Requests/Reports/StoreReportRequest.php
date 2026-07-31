<?php

namespace App\Http\Requests\Reports;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'category' => ['required', 'string', Rule::in(Report::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
