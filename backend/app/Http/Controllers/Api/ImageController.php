<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Property;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'images'   => 'required|array|min:1',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'property_id' => 'required|integer|exists:properties,id',
        ]);

        $property = Property::findOrFail($request->property_id);

        if ($property->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $uploadedImages = [];

        foreach ($request->file('images') as $file) {
            $path = $file->store('images', 'public');

            Image::create([
                'property_id' => $request->property_id,
                'path'        => $path,
            ]);

            $uploadedImages[] = $path;
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images'  => $uploadedImages,
        ], 201);
    }
}
