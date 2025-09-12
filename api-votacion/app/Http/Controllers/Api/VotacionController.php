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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VotacionController extends Controller
{
    public function getEstadoVotante($cedula)
    {
        // 1) Debe haber proceso Abierto
        $procesoActivo = Proceso::where('estado', 'Abierto')->first();
        if (!$procesoActivo) {
            return response()->json(['message' => 'No hay un proceso de votación activo en este momento.'], 403);
        }

        if (empty($cedula)) {
            return response()->json(['message' => 'La cédula es requerida.'], 400);
        }

        // 2) Debe ser hábil
        $empleadoHabil = EmpleadoHabil::where('cedula', $cedula)->first();
        if (!$empleadoHabil) {
            return response()->json(['message' => 'La cédula proporcionada no corresponde a un votante hábil.'], 404);
        }

        // 3) Ya votó = existe un Voto asociado a un RegistroVoto de esta cédula (y opcionalmente de este proceso)
        $regQuery = RegistroVoto::where('cedula', $cedula);
        if (Schema::hasColumn('registro_votos', 'proceso_id')) {
            $regQuery->where('proceso_id', $procesoActivo->id);
        }
        $registro = $regQuery->latest('id')->first();

        $yaVoto = false;
        if ($registro) {
            $yaVoto = Voto::where('registro_voto_id', $registro->id)->exists();
        }

        return response()->json([
            'esHabil'           => true,
            'yaVoto'            => $yaVoto,
            'nombre'            => $empleadoHabil->nombre_completo,
            'grupo_ocupacional' => $empleadoHabil->grupo_ocupacional,
            'cargo'             => $empleadoHabil->cargo,
            'lugar_trabajo'     => $empleadoHabil->lugar_de_funciones,
        ]);
    }

    public function getCandidatosPorGrupo($grupo)
    {
        $procesoActivo = Proceso::where('estado', 'Abierto')->first();
        if (!$procesoActivo) {
            return response()->json(['message' => 'No hay un proceso de votación activo.'], 404);
        }

        $candidatos = Postulante::where('proceso_id', $procesoActivo->id)
            ->where('grupo_ocupacional', $grupo)
            ->get(['id', 'nombre_completo', 'cargo', 'valores', 'foto_path']);

        // Agregar URL pública de la foto
        $candidatos->transform(function ($p) {
            $p->foto_url = $p->foto_path ? Storage::url($p->foto_path) : null;
            return $p;
        });

        return response()->json($candidatos);
    }

    public function registrarVoto(Request $request)
    {
        // Validación de entrada
        $validator = Validator::make($request->all(), [
            'cedula'         => 'required|string|exists:empleados_habiles,cedula',
            'postulante_id'  => 'required|integer|exists:postulantes,id',
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

        // Seguridad: postulante debe pertenecer al proceso activo
        if ($postulante->proceso_id != $procesoActivo->id) {
            return response()->json(['message' => 'El candidato no pertenece al proceso activo.'], 403);
        }

        // Seguridad: grupo del votante debe coincidir con el del postulante
        if ($votante->grupo_ocupacional !== $postulante->grupo_ocupacional) {
            return response()->json(['message' => 'Conflicto de grupo. No puede votar por este candidato.'], 403);
        }

        // Ejecutar en transacción: crear (o reutilizar) registro y crear voto
        return DB::transaction(function () use ($cedula, $votante, $postulante, $procesoActivo) {
            // Buscar si ya existe un registro para esta cédula (y proceso si existe la columna)
            $regQuery = RegistroVoto::where('cedula', $cedula);
            if (Schema::hasColumn('registro_votos', 'proceso_id')) {
                $regQuery->where('proceso_id', $procesoActivo->id);
            }
            $registro = $regQuery->latest('id')->first();

            if ($registro && Voto::where('registro_voto_id', $registro->id)->exists()) {
                return response()->json(['message' => 'Este usuario ya ha emitido su voto.'], 403);
            }

            // Si no hay registro o el que hay fue anulado, crear uno nuevo
            if (!$registro) {
                $payload = [
                    'cedula'            => $votante->cedula,
                    'grupo_ocupacional' => $votante->grupo_ocupacional,
                ];
                if (Schema::hasColumn('registro_votos', 'proceso_id')) {
                    $payload['proceso_id'] = $procesoActivo->id;
                }
                $registro = RegistroVoto::create($payload);
            }

            // Crear el voto asociado
            Voto::create([
                'registro_voto_id' => $registro->id,
                'postulante_id'    => $postulante->id,
            ]);

            return response()->json(['message' => '¡Voto registrado exitosamente!'], 201);
        });
    }
}
