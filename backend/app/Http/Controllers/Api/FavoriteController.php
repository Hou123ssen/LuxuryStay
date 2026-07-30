<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Support\PropertyRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function toggle(Request $request)
    {
        $favorite = Favorite::where('user_id', Auth::id())
            ->where('property_id', $request->property_id)
            ->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json(['message' => 'Removed']);
        }

        Favorite::create([
            'user_id' => Auth::id(),
            'property_id' => $request->property_id
        ]);

        return response()->json(['message' => 'Added']);
    }
    public function index(Request $request)
    {
        $favorites = Favorite::with([
                'property' => function ($query) {
                    $query
                        ->with('images')
                        ->withAvg(['reviews as reviews_avg_rating' => function ($query) {
                            $query->published();
                        }], 'rating')
                        ->withCount(['reviews as reviews_count' => function ($query) {
                            $query->published();
                        }]);
                },
            ])
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate($this->perPage($request, 12))
            ->withQueryString();

        $favorites->getCollection()->transform(function ($favorite) {
            if ($favorite->property) {
                PropertyRating::apply($favorite->property);
            }

            return $favorite;
        });

        return $this->paginatedResponse($favorites);
    }
}
