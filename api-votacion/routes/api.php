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

    // --- Rutas Públicas ---
    // No requieren token de autenticación.

    // Paso 1: Obtiene el estado de un votante (si es hábil, si ya votó, etc.)
    Route::get('estado-votante/{cedula}', [VotacionController::class, 'getEstadoVotante']);

    // Paso 2: Inicia la sesión de votación y genera el token JWT
    Route::post('registrar-sesion', [VotacionController::class, 'registrarSesionParaVotar']);


    // --- Rutas Protegidas ---
    // Requieren un token JWT válido para acceder.
    Route::middleware('auth:api')->group(function () {

        // Paso 3: Obtiene la lista de candidatos para el grupo del votante
        Route::get('candidatos', [VotacionController::class, 'getCandidatosPorGrupo']);

        // Paso 4: Registra el voto final del usuario
        Route::post('votar', [VotacionController::class, 'registrarVoto']);
    });
});
