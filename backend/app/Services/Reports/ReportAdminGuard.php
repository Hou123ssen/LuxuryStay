<?php

namespace App\Services\Reports;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReportAdminGuard
{
    public function authorize(User $user): void
    {
        if ($user->role === 'admin') {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => 'This action is unauthorized.',
        ], 403));
    }
}
