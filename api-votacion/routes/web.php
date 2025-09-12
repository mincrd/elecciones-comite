<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Storage;


Route::get('/media/{path}', function (string $path) {
    $path = ltrim($path, '/');                // sanitiza
    $disk = Storage::disk('public');

    abort_unless($disk->exists($path), 404);

    return $disk->response($path, null, [
        'Cache-Control' => 'public, max-age=31536000, immutable',
        'Access-Control-Allow-Origin' => '*',
        'Cross-Origin-Resource-Policy' => 'cross-origin',   // evita el bloqueo COEP
    ]);
})->where('path', '.*');
