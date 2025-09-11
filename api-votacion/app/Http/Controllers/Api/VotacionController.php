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
    /**
     * Identifica a un votante, lo registra si es necesario y le devuelve un token JWT.
     */
    public function identificarVotante(Request $request)
    {
        // 1. Validar que la cédula venga en la petición
        $validator = Validator::make($request->all(), [
            'cedula' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $cedula = $request->cedula;

        // 2. Verificar si esta cédula ya fue registrada para votar
        // Esta es la comprobación más importante para evitar re-emisión de tokens.
        if (RegistroVoto::where('cedula', $cedula)->exists()) {
            return response()->json(['message' => 'Esta cédula ya fue utilizada en este proceso de votación.'], 403);
        }

        // 3. Verificar si el empleado está en la lista de votantes hábiles
        $empleadoHabil = EmpleadoHabil::where('cedula', $cedula)->first();

        if (!$empleadoHabil) {
            return response()->json(['message' => 'La cédula proporcionada no corresponde a un votante hábil.'], 404);
        }

        // 4. Si es hábil y no ha sido registrado, se crea su registro de voto
        // Este registro marcará que ya inició el proceso.
        $votante = RegistroVoto::create([
            'cedula' => $empleadoHabil->cedula,
            'grupo_ocupacional' => $empleadoHabil->grupo_ocupacional
        ]);

        // 5. Generar el token JWT para el registro recién creado
        if (!$token = auth('api')->fromUser($votante)) {
            return response()->json(['error' => 'No se pudo crear el token'], 500);
        }

        // 6. Devolver el token para que pueda continuar a los siguientes pasos
        return response()->json(['token' => $token]);
    }

    /**
     * Obtiene los candidatos del grupo ocupacional del votante autenticado.
     */
    public function getCandidatosPorGrupo(Request $request)
    {
        // auth('api')->user() obtiene el modelo RegistroVoto a partir del token
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
