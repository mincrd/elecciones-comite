<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Storage;

Route::get('/media/{path}', function (string $path) {
    $path = ltrim($path, '/'); // seguridad / limpieza
    $disk = Storage::disk('public');

    abort_unless($disk->exists($path), 404);

    // Devolver el archivo con headers que permiten embebido cross-origin
    return $disk->response($path, null, [
        'Cache-Control'                 => 'public, max-age=31536000, immutable',
        'Access-Control-Allow-Origin'   => '*',
        'Cross-Origin-Resource-Policy'  => 'cross-origin',   // <-- clave para COEP
        // Si quieres también: 'Timing-Allow-Origin' => '*',
    ]);
})->where('path', '.*');
Route::get('/', function () {
    return view('welcome');
});
