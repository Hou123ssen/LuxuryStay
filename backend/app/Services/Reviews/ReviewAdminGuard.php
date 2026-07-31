<?php

namespace App\Services\Reviews;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReviewAdminGuard
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
