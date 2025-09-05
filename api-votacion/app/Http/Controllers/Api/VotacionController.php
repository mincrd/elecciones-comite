<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use App\Models\Proceso;
use App\Models\RegistroVoto;
use App\Models\Voto;

class VotacionController extends Controller
{
    // 1. Identificar al votante y registrarlo si no existe
    public function identificarVotante(Request $request)
    {
        // ... (Validar que email o no_empleado y grupo_ocupacional vengan en el request)

        // Busca o crea el registro del votante
        $votante = RegistroVoto::firstOrCreate(
            ['email' => $request->email], // o 'no_empleado'
            [
                'no_empleado' => $request->no_empleado,
                'grupo_ocupacional' => $request->grupo_ocupacional
            ]
        );

        // Genera un token para las siguientes peticiones
        $token = $votante->createToken('auth_token')->plainTextToken;

        return response()->json(['token' => $token]);
    }

    // 2. Obtener candidatos SÓLO del grupo ocupacional del votante
    public function getCandidatosPorGrupo(Request $request)
    {
        $votante = $request->user(); // El votante autenticado con el token

        // Busca el proceso de votación activo
        $procesoActivo = Proceso::where('estado', 'Abierto')->first();
        if (!$procesoActivo) {
            return response()->json(['message' => 'No hay un proceso de votación activo.'], 404);
        }

        $candidatos = $procesoActivo->postulantes()
            ->where('grupo_ocupacional', $votante->grupo_ocupacional)
            ->get(['id', 'nombre_completo', 'cargo', 'valores']);

        return response()->json($candidatos);
    }

    // 3. Registrar el voto
    public function registrarVoto(Request $request)
    {
        $votante = $request->user();

        // Validar que el postulanteId exista
        $validator = Validator::make($request->all(), [
            'postulante_id' => 'required|exists:postulantes,id'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // VERIFICAR QUE NO HAYA VOTADO YA
        if (Voto::where('registro_voto_id', $votante->id)->exists()) {
            return response()->json(['message' => 'Usted ya ha emitido su voto.'], 403);
        }

        // Crear el voto
        Voto::create([
            'registro_voto_id' => $votante->id,
            'postulante_id' => $request->postulante_id
        ]);

        // Invalidar el token para que no pueda volver a usarlo
        $votante->tokens()->delete();

        return response()->json(['message' => '¡Voto registrado exitosamente!']);
    }
}
