<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string'
        ]);

        $hasBooking = Booking::where('user_id', Auth::id())
            ->where('property_id', $request->property_id)
            ->exists();

        if (!$hasBooking) {
            return response()->json(['error' => 'You must book first'], 403);
        }
        $review = Review::create([
            'user_id' => Auth::id(),
            'property_id' => $request->property_id,
            'rating' => $request->rating,
            'comment' => $request->comment
        ]);

        return response()->json($review);
    }
}
