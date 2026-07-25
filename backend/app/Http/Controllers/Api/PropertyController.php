<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $query = Property::with('images')->withAvg('reviews', 'rating');

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
                $query->orderBy('reviews_avg_rating', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        return $query->paginate(20)->through(function ($property) {
            $property->is_favorite = Auth::check()
                ? $property->favorites()->where('user_id', Auth::id())->exists()
                : false;

            return $property;
        });
    }

    public function show($id)
    {
        return Property::with(['images', 'reviews.user'])->findOrFail($id);
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
}
