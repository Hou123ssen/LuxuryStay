<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
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