<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmpleadoHabil;
use App\Models\Postulante;
use App\Models\Proceso;
use App\Models\RegistroVoto;
use App\Models\Voto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class VotacionController extends Controller
{
    /**
     * Verifica estado del votante por cédula.
     * yaVoto = true si existe cualquier fila en registro_votos con esa cédula.
     */
    public function getEstadoVotante($cedula)
    {
        // 1) Debe haber proceso Abierto
        $procesoActivo = Proceso::where('estado', 'Abierto')->first();
        if (!$procesoActivo) {
            return response()->json(['message' => 'No hay un proceso de votación activo en este momento.'], 403);
        }

        // 2) Validar cédula
        if (!filled($cedula)) {
            return response()->json(['message' => 'La cédula es requerida.'], 400);
        }

        // 3) Debe ser hábil
        $empleadoHabil = EmpleadoHabil::where('cedula', $cedula)->first();
        if (!$empleadoHabil) {
            return response()->json(['message' => 'La cédula proporcionada no corresponde a un votante hábil.'], 404);
        }

        // 4) ¿Ya votó? (bloqueo global por cédula)
        $yaVoto = RegistroVoto::where('cedula', $cedula)->exists();

        return response()->json([
            'esHabil'           => true,
            'yaVoto'            => $yaVoto,
            'nombre'            => $empleadoHabil->nombre_completo,
            'grupo_ocupacional' => $empleadoHabil->grupo_ocupacional,
            'cargo'             => $empleadoHabil->cargo,
            'lugar_trabajo'     => $empleadoHabil->lugar_de_funciones,
        ]);
    }

    /**
     * Candidatos por grupo del proceso abierto.
     * Incluye foto_url absoluta para evitar mixed-content/404.
     */
    public function getCandidatosPorGrupo($grupo)
    {
        $procesoActivo = Proceso::where('estado', 'Abierto')->first();
        if (!$procesoActivo) {
            return response()->json(['message' => 'No hay un proceso de votación activo.'], 404);
        }

        $candidatos = Postulante::where('proceso_id', $procesoActivo->id)
            ->where('grupo_ocupacional', $grupo)
            ->get(['id', 'nombre_completo', 'cargo', 'valores', 'foto_path']);

        $candidatos->transform(function ($p) {
            $p->foto_url = $p->foto_path ? URL::to(Storage::url($p->foto_path)) : null;
            return $p;
        });

        return response()->json($candidatos);
    }

    /**
     * Registra el voto final.
     * Regla: si ya existe un RegistroVoto para la cédula → bloquear.
     */
    public function registrarVoto(Request $request)
    {
        // Validación de entrada
        $validator = Validator::make($request->all(), [
            'cedula'        => 'required|string|exists:empleados_habiles,cedula',
            'postulante_id' => 'required|integer|exists:postulantes,id',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Debe haber proceso abierto
        $procesoActivo = Proceso::where('estado', 'Abierto')->first();
        if (!$procesoActivo) {
            return response()->json(['message' => 'No hay un proceso de votación activo.'], 403);
        }

        $cedula     = $request->cedula;
        $postulante = Postulante::find($request->postulante_id);
        $votante    = EmpleadoHabil::where('cedula', $cedula)->first();

        // El candidato debe pertenecer al proceso abierto
        if ((int)$postulante->proceso_id !== (int)$procesoActivo->id) {
            return response()->json(['message' => 'El candidato no pertenece al proceso activo.'], 403);
        }

        // Grupo debe coincidir
        if ($votante->grupo_ocupacional !== $postulante->grupo_ocupacional) {
            return response()->json(['message' => 'Conflicto de grupo. No puede votar por este candidato.'], 403);
        }

        // BLOQUEAR si ya existe un registro por cédula (sin proceso_id)
        if (RegistroVoto::where('cedula', $cedula)->exists()) {
            return response()->json([
                'message' => 'Ya existe un registro de votación para esta cédula. '
                    . 'Si hubo un fallo, solicite la anulación en mesa para reintentar.'
            ], 403);
        }

        // Transacción: crear registro + voto
        return DB::transaction(function () use ($votante, $postulante) {
            $registro = RegistroVoto::create([
                'cedula'            => $votante->cedula,
                'grupo_ocupacional' => $votante->grupo_ocupacional,
            ]);

            Voto::create([
                'registro_voto_id' => $registro->id,
                'postulante_id'    => $postulante->id,
            ]);

            return response()->json(['message' => '¡Voto registrado exitosamente!'], 201);
        });
    }
}
