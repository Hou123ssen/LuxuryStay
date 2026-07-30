<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Services\Reviews\CreateReviewService;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, CreateReviewService $reviews)
    {
        $review = $reviews->create($request->user(), $request->validated());

        return response()->json([
            'message' => 'Review submitted.',
            'data' => $review,
        ], 201);
    }
}
