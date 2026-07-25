<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
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
    public function index()
    {
        return Favorite::with('property.images')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();
    }
}
