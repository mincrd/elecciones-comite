<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MediaController;

Route::get('media/{path}', [MediaController::class, 'show'])
    ->where('path', '.*');


Route::get('/', function () {
    return view('welcome');
});
