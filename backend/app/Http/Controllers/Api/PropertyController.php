<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Services\Bookings\BookingAvailabilityService;
use App\Support\OwnerReliability;
use App\Support\PropertyRating;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $query = Property::with('images')
            ->withAvg(['reviews as reviews_avg_rating' => function ($query) {
                $query->published();
            }], 'rating')
            ->withCount(['reviews as reviews_count' => function ($query) {
                $query->published();
            }])
            ->withCount(['reviews as pending_high_risk_reviews_count' => function ($query) {
                $query
                    ->where('status', Review::STATUS_PENDING_REVIEW)
                    ->where('risk_score', '>=', config('reviews.risk.high_risk_threshold'));
            }]);

        if ($request->city) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }
        if ($request->min_price) {
            $query->where('price_per_night', '>=', $request->min_price);
        }
        if ($request->max_price) {
            $query->where('price_per_night', '<=', $request->max_price);
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }

        switch ($request->sort) {
            case 'price_asc':
                $query->orderBy('price_per_night', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price_per_night', 'desc');
                break;
            case 'rating':
                $query->orderByRaw(PropertyRating::weightedScoreSql('reviews_avg_rating', 'reviews_count').' desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $paginator = $query
            ->paginate($this->perPage($request, 12))
            ->withQueryString();

        $collection = $paginator->getCollection();
        OwnerReliability::applyToCollection($collection);

        $collection->transform(function ($property) {
            $property->is_favorite = Auth::check()
                ? $property->favorites()->where('user_id', Auth::id())->exists()
                : false;

            return PropertyRating::apply($property);
        });

        return $this->paginatedResponse($paginator);
    }

    public function show($id)
    {
        $property = Property::with([
                'images',
                'reviews' => function ($query) {
                    $query->published()->with('user')->latest();
                },
            ])
            ->withAvg(['reviews as reviews_avg_rating' => function ($query) {
                $query->published();
            }], 'rating')
            ->withCount(['reviews as reviews_count' => function ($query) {
                $query->published();
            }])
            ->withCount(['reviews as pending_high_risk_reviews_count' => function ($query) {
                $query
                    ->where('status', Review::STATUS_PENDING_REVIEW)
                    ->where('risk_score', '>=', config('reviews.risk.high_risk_threshold'));
            }])
            ->findOrFail($id);

        PropertyRating::apply($property);
        OwnerReliability::apply($property);
        $property->setAttribute('review_eligible_bookings', $this->eligibleReviewBookings($property));

        return $property;
    }

    public function availability(Property $property, BookingAvailabilityService $availability)
    {
        $payload = $availability->unavailableForProperty($property);

        return response()->json([
            'property_id' => $property->id,
            'unavailable_ranges' => $payload['unavailable_ranges'],
            'unavailable_dates' => $payload['unavailable_dates'],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'required|string',
            'type'            => 'required|in:apartment,hotel,residence',
            'price_per_night' => 'required|numeric|min:0',
            'city'            => 'required|string',
            'address'         => 'required|string',
        ]);

        $property = Property::create([
            'user_id'         => Auth::id(),
            'title'           => $request->title,
            'description'     => $request->description,
            'type'            => $request->type,
            'price_per_night' => $request->price_per_night,
            'city'            => $request->city,
            'address'         => $request->address,
        ]);

        return response()->json(['data' => $property], 201);
    }

    public function update(Request $request, $id)
    {
        $property = Property::findOrFail($id); // ✅ إصلاح typo

        if ($property->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title'           => 'sometimes|string|max:255',
            'description'     => 'sometimes|string',
            'type'            => 'sometimes|in:apartment,hotel,residence',
            'price_per_night' => 'sometimes|numeric|min:0',
            'city'            => 'sometimes|string',
            'address'         => 'sometimes|string',
        ]);

        $property->update($validated);

        return response()->json(['data' => $property]);
    }

    public function destroy($id)
    {
        $property = Property::findOrFail($id);

        if ($property->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $property->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    private function eligibleReviewBookings(Property $property)
    {
        if (! Auth::check() || (int) $property->user_id === (int) Auth::id()) {
            return [];
        }

        return Booking::where('user_id', Auth::id())
            ->where('property_id', $property->id)
            ->whereIn('status', ['accepted', 'completed'])
            ->whereDate('end_date', '<', today())
            ->whereDoesntHave('review')
            ->latest('end_date')
            ->get(['id', 'start_date', 'end_date'])
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'start_date' => Carbon::parse($booking->start_date)->toDateString(),
                    'end_date' => Carbon::parse($booking->end_date)->toDateString(),
                ];
            })
            ->values();
    }
}
