<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Proceso;
use App\Models\EmpleadoHabil;
use App\Models\Postulante;
use App\Models\Voto;

class VotosAdminController extends Controller
{
    /**
     * Devuelve estadísticas de participación del proceso:
     * - total_votantes: total de empleados hábiles (padron)
     * - votos_emitidos: cantidad de votos COMPLETOS (fila en "votos") del proceso
     * - pendientes, porcentaje
     * - por_grupo (opcional, útil si luego quieres barras por grupo)
     *
     * GET /api/admin/votos/estadisticas?proceso_id=ID
     */
    public function estadisticas(Request $request)
    {
        $data = $request->validate([
            'proceso_id' => ['required', 'exists:procesos,id'],
        ]);

        $procesoId = (int) $data['proceso_id'];

        // 1) Total de votantes hábiles (padrón)
        $totalVotantes = EmpleadoHabil::count();

        // 2) Votos COMPLETOS emitidos en el proceso:
        //    contamos filas en "votos" cuyos postulantes pertenezcan al proceso.
        $postulantesIds = Postulante::where('proceso_id', $procesoId)->pluck('id');
        $votosEmitidos  = Voto::whereIn('postulante_id', $postulantesIds)->count();

        // 3) (Opcional) Desglose por grupo ocupacional
        //    total votantes por grupo y votos emitidos por grupo (completos)
        $totalesPorGrupo = EmpleadoHabil::select('grupo_ocupacional')
            ->selectRaw('COUNT(*) as total_votantes')
            ->groupBy('grupo_ocupacional')
            ->get();

        $porGrupo = $totalesPorGrupo->map(function ($row) use ($postulantesIds) {
            $votosGrupo = Voto::whereIn('postulante_id', $postulantesIds)
                ->whereHas('votante', function ($q) use ($row) {
                    $q->where('grupo_ocupacional', $row->grupo_ocupacional);
                })
                ->count();

            $total = (int) $row->total_votantes;
            $pend = max($total - $votosGrupo, 0);

            return [
                'grupo'        => $row->grupo_ocupacional,
                'total_votantes' => $total,
                'votos_emitidos' => $votosGrupo,
                'pendientes'     => $pend,
                'porcentaje'     => $total > 0 ? round($votosGrupo * 100 / $total, 1) : 0.0,
            ];
        })->values();

        return response()->json([
            'proceso_id'      => $procesoId,
            'total_votantes'  => $totalVotantes,
            'votos_emitidos'  => $votosEmitidos,
            'pendientes'      => max($totalVotantes - $votosEmitidos, 0),
            'porcentaje'      => $totalVotantes > 0 ? round($votosEmitidos * 100 / $totalVotantes, 1) : 0.0,
            'por_grupo'       => $porGrupo, // quítalo si no lo necesitas
        ]);
    }
}
