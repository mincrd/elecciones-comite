<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmpleadoHabil;
use App\Models\Proceso;
use App\Models\RegistroVoto;
use App\Models\Voto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth; // Opcional, pero bueno tenerlo

class VotacionController extends Controller
{

    // app/Http/Controllers/VotacionController.php

    /**
     * Obtiene el estado completo de un votante por su cédula.
     */
    public function getEstadoVotante($cedula)
    {
        // 1. Validar que exista un proceso de votación activo
        $procesoActivo = Proceso::where('estado', 'Abierto')->first();
        if (!$procesoActivo) {
            return response()->json(['message' => 'No hay un proceso de votación activo en este momento.'], 403);
        }

        // 2. Validar que la cédula no esté vacía
        if (empty($cedula)) {
            return response()->json(['message' => 'La cédula es requerida.'], 400);
        }

        // 3. Buscar si el empleado está en la lista de votantes hábiles
        $empleadoHabil = EmpleadoHabil::where('cedula', $cedula)->first();
        if (!$empleadoHabil) {
            return response()->json(['message' => 'La cédula proporcionada no corresponde a un votante hábil.'], 404);
        }

        // 4. ✅ AJUSTE: Hacemos una consulta directa y más clara para saber si ya votó.
        // Esto responde directamente a tu pregunta 1.
        // Buscamos un registro donde la cédula coincida Y el voto ya haya sido emitido (voto_emitido = true).
        // 4. ✅ AJUSTE: Ahora 'yaVoto' es true si simplemente existe un registro con esa cédula.
        $votoYaEmitido = RegistroVoto::where('cedula', $cedula)->exists();

        // 5. Devolver toda la información relevante
        return response()->json([
            'esHabil' => true, // Indica que la persona está en el padrón electoral.
            'yaVoto' => $votoYaEmitido, // Indica si su voto ya fue finalizado.
            'nombre' => $empleadoHabil->nombre_completo,
            'grupo_ocupacional' => $empleadoHabil->grupo_ocupacional,
            'cargo' => $empleadoHabil->cargo,
            'lugar_trabajo' => $empleadoHabil->lugar_de_funciones,
        ]);
    }

    /**
     * PASO 2: Crea el registro y genera el token para iniciar la sesión de votación.
     * (Este método se mantiene igual que en la respuesta anterior).
     */
    public function registrarSesionParaVotar(Request $request)
    {
        $validator = Validator::make($request->all(), ['cedula' => 'required|string']);
        if ($validator->fails()) { return response()->json($validator->errors(), 400); }

        $cedula = $request->cedula;

        $empleadoHabil = EmpleadoHabil::where('cedula', $cedula)->first();
        if (!$empleadoHabil || RegistroVoto::where('cedula', $cedula)->exists()) {
            return response()->json(['message' => 'Acceso no autorizado o cédula ya registrada.'], 403);
        }

        $votante = RegistroVoto::create([
            'cedula' => $empleadoHabil->cedula,
            'grupo_ocupacional' => $empleadoHabil->grupo_ocupacional,
            'voto_emitido' => false // Se inicia la sesión, pero aún no ha votado
        ]);

        if (!$token = auth('api')->fromUser($votante)) {
            return response()->json(['error' => 'No se pudo crear el token'], 500);
        }

        return response()->json(['token' => $token]);
    }

    /**
     * Paso 3: Obtiene los candidatos (este método se mantiene igual).
     */
    public function getCandidatosPorGrupo(Request $request)
    {
        $votante = auth('api')->user();
        $procesoActivo = Proceso::where('estado', 'Abierto')->first();
        if (!$procesoActivo) {
            return response()->json(['message' => 'No hay un proceso de votación activo en este momento.'], 404);
        }

        $candidatos = $procesoActivo->postulantes()
            ->where('grupo_ocupacional', $votante->grupo_ocupacional)
            ->get(['id', 'nombre_completo', 'cargo', 'valores']);

        return response()->json($candidatos);
    }


    /**
     * Obtiene los candidatos del grupo ocupacional del votante autenticado.
     */

    /**
     * Registra el voto del usuario.
     */
    public function registrarVoto(Request $request)
    {
        $votante = auth('api')->user();

        $validator = Validator::make($request->all(), [
            'postulante_id' => 'required|exists:postulantes,id'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Verificar que el postulante sea del mismo grupo que el votante
        $procesoActivo = Proceso::where('estado', 'Abierto')->first();
        $postulante = $procesoActivo->postulantes()->find($request->postulante_id);

        if (!$postulante || $postulante->grupo_ocupacional !== $votante->grupo_ocupacional) {
            return response()->json(['message' => 'No puede votar por un candidato de otro grupo ocupacional.'], 403);
        }

        if (Voto::where('registro_voto_id', $votante->id)->exists()) {
            return response()->json(['message' => 'Usted ya ha emitido su voto anteriormente.'], 403);
        }

        Voto::create([
            'registro_voto_id' => $votante->id,
            'postulante_id' => $request->postulante_id
        ]);

        // Invalidar el token para que no se pueda volver a usar
        auth('api')->invalidate();

        return response()->json(['message' => '¡Voto registrado exitosamente!']);
    }
}
