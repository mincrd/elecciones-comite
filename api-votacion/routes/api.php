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


Route::prefix('votacion')->name('votacion.')->group(function () {
    // NUEVA RUTA para obtener el estado del votante (pública)
    Route::get('estado-votante/{cedula}', [VotacionController::class, 'getEstadoVotante']);

    // Ruta para iniciar la sesión de votación (pública)
    Route::post('registrar-sesion', [VotacionController::class, 'registrarSesionParaVotar']);

    // Rutas que requieren el token (protegidas)
    Route::middleware('auth:api')->group(function () {
        Route::get('candidatos', [VotacionController::class, 'getCandidatosPorGrupo']);
         Route::post('votar', [VotacionController::class, 'registrarVoto']);
    });
});
