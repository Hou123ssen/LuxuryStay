<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class AdminDashboardGuard
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
