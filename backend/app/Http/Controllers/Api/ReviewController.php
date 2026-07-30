<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Models\Review;
use App\Services\Reviews\CreateReviewService;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, CreateReviewService $reviews)
    {
        $review = $reviews->create($request->user(), $request->validated(), [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => $review->status === Review::STATUS_PENDING_REVIEW
                ? 'Your review was received and is being checked before publication.'
                : 'Review submitted.',
            'data' => $review,
        ], 201);
    }
}
