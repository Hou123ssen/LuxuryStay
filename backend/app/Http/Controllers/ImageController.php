<?php

namespace App\Http\Controllers;

use App\Models\Image;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'images' => 'required',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:2048',
            'property_id' => 'required|exists:properties,id'
        ]);

        if (!$request->hasFile('images')) {
            return response()->json([
                'message' => 'No images uploaded'
            ], 422);
        }

        $paths = [];

        foreach ($request->file('images') as $file) {
            $path = $file->store('properties', 'public');

            Image::create([
                'property_id' => $request->property_id,
                'path' => $path
            ]);

            $paths[] = $path;
        }

        return response()->json([
            'images' => $paths
        ]);
    }
}
