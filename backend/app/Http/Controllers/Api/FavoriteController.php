<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Favorite;
use App\Services\Properties\PropertyQueryService;
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
    public function index(Request $request, PropertyQueryService $propertyQueryService)
    {
        $favorites = Favorite::with([
                'property' => function ($query) use ($propertyQueryService) {
                    $propertyQueryService->withRatingAggregates($query->with('images'));
                },
            ])
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate($this->perPage($request, 12))
            ->withQueryString();

        $properties = $favorites->getCollection()
            ->pluck('property')
            ->filter();

        $propertyQueryService->prepareFavoriteProperties($properties);

        $favorites->getCollection()->transform(function ($favorite) {
            $favoritePayload = $favorite->toArray();

            if ($favorite->property) {
                $favoritePayload['property'] = (new PropertyResource($favorite->property))->resolve();
            }

            return $favoritePayload;
        });

        return $this->paginatedResponse($favorites);
    }
}
