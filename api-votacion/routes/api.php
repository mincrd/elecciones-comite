<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\ProcesoController;
use App\Http\Controllers\Api\Admin\PostulanteController;
use App\Http\Controllers\Api\VotacionController;
use App\Http\Controllers\Api\ResultadoController;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// rutas para el login
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['jwt.auth'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

// Rutas para el Panel de Administración
Route::prefix('admin')->name('admin.')->group(function () {
    Route::apiResource('procesos', ProcesoController::class);
    Route::apiResource('postulantes', PostulanteController::class);
    Route::get('resultados/{proceso}', [ResultadoController::class, 'obtenerResultados'])->name('resultados');
});

// Rutas para la Plataforma de Votación Pública
Route::prefix('votacion')->name('votacion.')->group(function () {
    // Endpoint para que el votante se identifique y obtenga un token
    Route::post('identificar', [VotacionController::class, 'identificarVotante']);

    // Endpoints protegidos por el guard 'api' (que ahora usa JWT).
    // Requieren un token JWT válido en la cabecera 'Authorization'.
    Route::middleware('auth:api')->group(function () {
        Route::get('candidatos', [VotacionController::class, 'getCandidatosPorGrupo']);
        Route::post('votar', [VotacionController::class, 'registrarVoto']);
    });
});
