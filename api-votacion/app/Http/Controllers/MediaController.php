<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function show(string $path)
    {
        $path = ltrim($path, '/');

        // Evitar traversal
        if (str_contains($path, '..')) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (!$disk->exists($path)) {
            abort(404);
        }

        $mime = $disk->mimeType($path) ?? 'application/octet-stream';
        $stream = $disk->readStream($path);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type'        => $mime,
            'Cache-Control'       => 'public, max-age=31536000',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }
}
