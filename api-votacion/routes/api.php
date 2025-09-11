<?php

use App\Http\Controllers\Api\Admin\EmpleadoHabilController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\ProcesoController;
use App\Http\Controllers\Api\Admin\PostulanteController;
use App\Http\Controllers\Api\VotacionController;
use App\Http\Controllers\Api\ResultadoController;
use App\Http\Controllers\AuthController;
use Tymon\JWTAuth\Http\Middleware\Authenticate as JwtAuthenticate;
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
    // <-- 2. AÑADIR ESTA LÍNEA PARA LOS EMPLEADOS HÁBILES
    Route::apiResource('empleados-habiles', EmpleadoHabilController::class);
});


Route::prefix('votacion')->group(function () {
    Route::get('estado-votante/{cedula}', [VotacionController::class, 'getEstadoVotante'])
        ->withoutMiddleware([JwtAuthenticate::class, 'jwt.auth', 'auth:api']);

    Route::get('candidatos/{grupo}', [VotacionController::class, 'getCandidatosPorGrupo'])
        ->withoutMiddleware([JwtAuthenticate::class, 'jwt.auth', 'auth:api']);

    Route::post('votar', [VotacionController::class, 'registrarVoto'])
        ->withoutMiddleware([JwtAuthenticate::class, 'jwt.auth', 'auth:api']);
});
