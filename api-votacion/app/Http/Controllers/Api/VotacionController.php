<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $validator = Validator::make($request->all(), [
            'email' => 'required_without:no_empleado|nullable|email',
            'no_empleado' => 'required_without:email|nullable|string',
            'grupo_ocupacional' => 'required|in:I,II,III,IV,V',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $credentials = $request->only('email', 'no_empleado');
        // Eliminar claves nulas para que la búsqueda funcione con email O no_empleado
        $credentials = array_filter($credentials, fn($value) => !is_null($value));

        $votante = RegistroVoto::firstOrCreate($credentials, [
            'email' => $request->email,
            'no_empleado' => $request->no_empleado,
            'grupo_ocupacional' => $request->grupo_ocupacional
        ]);

        // --- LA CORRECCIÓN ESTÁ AQUÍ ---
        // Generamos un token para este 'votante' usando el guard de la API (JWT)
        // En lugar de: $votante->createToken('auth_token')->plainTextToken;
        if (!$token = auth('api')->fromUser($votante)) {
            return response()->json(['error' => 'No se pudo crear el token'], 500);
        }

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
