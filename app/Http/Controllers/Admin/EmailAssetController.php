<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmailAssetController extends Controller
{
    public function index()
    {
        $disk = Storage::disk('public');

        $files = $disk->allFiles('email-assets');

        $data = collect($files)
            ->filter(fn ($path) => preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $path))
            ->map(fn ($path) => [
                'src'  => url($disk->url($path)),
                'name' => basename($path),
            ])
            ->values();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp,svg'],
        ]);

        $disk = Storage::disk('public');

        $saved = collect($request->file('files', []))->map(function ($file) use ($disk) {
            $dir = 'email-assets/' . now()->format('Y/m');
            $name = Str::random(12) . '-' . preg_replace('/[^a-zA-Z0-9\.\-_]/', '', $file->getClientOriginalName());

            $path = $file->storeAs($dir, $name, 'public');

            return [
                'src'  => url($disk->url($path)),
                'name' => basename($path),
            ];
        })->values();

        return response()->json(['data' => $saved], 201);
    }
}
