<?php

use App\Http\Controllers\Api\Admin\EmpleadoHabilController;
use App\Http\Controllers\Api\Admin\GestionVotosController;
use App\Http\Controllers\Api\Admin\PostulanteController;
use App\Http\Controllers\Api\Admin\ProcesoController;
use App\Http\Controllers\Api\Admin\ResultadoController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VotosAdminController;
use App\Http\Controllers\Api\VotacionController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Tymon\JWTAuth\Http\Middleware\Authenticate as JwtAuthenticate;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// rutas para el login


// Rutas para el Panel de Administración
Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['jwt.auth'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('votos/estadisticas', [VotosAdminController::class, 'estadisticas']);
        Route::apiResource('procesos', ProcesoController::class);
        Route::apiResource('postulantes', PostulanteController::class);
        Route::post('postulantes/{postulante}/foto', [PostulanteController::class, 'updateFoto']);

        Route::get('resultados/{proceso}', [ResultadoController::class, 'obtenerResultados'])->name('resultados');
        // <-- 2. AÑADIR ESTA LÍNEA PARA LOS EMPLEADOS HÁBILES
        Route::apiResource('empleados-habiles', EmpleadoHabilController::class);
        // RUTAS PERSONALIZADAS 👇
        Route::post('procesos/{proceso}/cambiar-estado', [ProcesoController::class, 'cambiarEstado']);
        Route::get('procesos/{proceso}/logs', [ProcesoController::class, 'logs']); // opcional si implementas logs
        // Usuarios (CRUD)
        Route::apiResource('usuarios', UserController::class);

        // Cambiar contraseña de un usuario concreto
        Route::post('usuarios/{usuario}/password', [UserController::class, 'changePassword']);
// routes/api.php (dentro del grupo admin protegido con jwt)
        Route::get('resultados/{proceso}', [ResultadoController::class, 'obtenerResultados'])
            ->name('admin.resultados.show');

        // Gestión de votos
        Route::get('votos', [GestionVotosController::class, 'index']);            // listado
        Route::get('votos/stats', [GestionVotosController::class, 'stats']);      // totales
        Route::post('votos/anular-incompleto', [GestionVotosController::class, 'anularIncompleto']); // anulación
        Route::get('votos/{registro}', [GestionVotosController::class, 'show']);
        Route::get('votantes/pendientes', [GestionVotosController::class, 'pendientes']);

    });

});


Route::prefix('votacion')->group(function () {
    Route::get('estado-votante/{cedula}', [VotacionController::class, 'getEstadoVotante'])
        ->withoutMiddleware([JwtAuthenticate::class, 'jwt.auth', 'auth:api']);

    Route::get('candidatos/{grupo}', [VotacionController::class, 'getCandidatosPorGrupo'])
        ->withoutMiddleware([JwtAuthenticate::class, 'jwt.auth', 'auth:api']);

    Route::post('votar', [VotacionController::class, 'registrarVoto'])
        ->withoutMiddleware([JwtAuthenticate::class, 'jwt.auth', 'auth:api']);
});
