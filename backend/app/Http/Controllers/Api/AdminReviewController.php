<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\ModerateReviewRequest;
use App\Http\Resources\AdminReviewResource;
use App\Models\Review;
use App\Services\Reviews\ReviewAdminGuard;
use App\Services\Reviews\ReviewModerationService;
use App\Services\Reviews\ReviewQueryService;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    public function index(Request $request, ReviewAdminGuard $guard, ReviewQueryService $reviews)
    {
        $guard->authorize($request->user());

        $paginator = $reviews->paginate($request, $this->perPage($request, 15));
        $paginator->getCollection()->transform(function (Review $review) {
            return (new AdminReviewResource($review))->resolve();
        });

        return $this->paginatedResponse($paginator);
    }

    public function show(Request $request, Review $review, ReviewAdminGuard $guard, ReviewQueryService $reviews)
    {
        $guard->authorize($request->user());

        return response()->json([
            'data' => (new AdminReviewResource($reviews->find($review->id)))->resolve(),
        ]);
    }

    public function publish(
        ModerateReviewRequest $request,
        Review $review,
        ReviewAdminGuard $guard,
        ReviewModerationService $moderation
    ) {
        $guard->authorize($request->user());

        return $this->moderationResponse(
            $moderation->publish($review, $request->user(), $request->validated('reason')),
            'Review published.'
        );
    }

    public function reject(
        ModerateReviewRequest $request,
        Review $review,
        ReviewAdminGuard $guard,
        ReviewModerationService $moderation
    ) {
        $guard->authorize($request->user());

        return $this->moderationResponse(
            $moderation->reject($review, $request->user(), $request->validated('reason')),
            'Review rejected.'
        );
    }

    private function moderationResponse(Review $review, string $message)
    {
        return response()->json([
            'message' => $message,
            'data' => (new AdminReviewResource($review))->resolve(),
        ]);
    }
}
