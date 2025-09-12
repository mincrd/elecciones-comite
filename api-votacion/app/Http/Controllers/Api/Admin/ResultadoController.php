<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proceso;
use App\Models\Postulante;
use App\Models\Voto;
use Illuminate\Support\Facades\DB;

class ResultadoController extends Controller
{
    /**
     * GET /api/admin/resultados/{proceso}
     * Devuelve resultados por grupo con nombres y ganador(es).
     * Solo disponible si el proceso está Concluido.
     */
    public function obtenerResultados(Proceso $proceso)
    {
        if ($proceso->estado !== Proceso::ESTADO_CONCLUIDO) {
            return response()->json(['message' => 'El proceso no está Concluido.'], 403);
        }

        // Traer postulantes del proceso usando nombre_completo
        $postulantes = Postulante::where('proceso_id', $proceso->id)
            ->get(['id', 'grupo_ocupacional', 'nombre_completo']);

        // Conteo de votos por postulante_id
        $conteos = Voto::whereIn('postulante_id', $postulantes->pluck('id'))
            ->select('postulante_id', DB::raw('COUNT(*) as votos'))
            ->groupBy('postulante_id')
            ->pluck('votos', 'postulante_id'); // [postulante_id => votos]

        // Mapear + agrupar por grupo
        $porGrupo = $postulantes->map(function ($p) use ($conteos) {
            $nombre = $p->nombre_completo ?: ('#' . $p->id);

            return [
                'postulante_id' => (int) $p->id,
                'nombre'        => $nombre,
                'grupo'         => $p->grupo_ocupacional ?: '—',
                'votos'         => (int) ($conteos[$p->id] ?? 0),
            ];
        })->groupBy('grupo');

        // Construir payload final
        $grupos = $porGrupo->map(function ($items, $grupo) {
            $ordenados = $items->sortByDesc('votos')->values();
            $total     = $ordenados->sum('votos');
            $top       = $ordenados->max('votos');

            return [
                'grupo'       => $grupo,
                'total'       => $total,
                'ganador_ids' => $top > 0
                    ? $ordenados->where('votos', $top)->pluck('postulante_id')->values()
                    : collect(), // si no hay votos, no hay ganador
                'candidatos'  => $ordenados->map(fn ($c) => [
                    'postulante_id' => $c['postulante_id'],
                    'nombre'        => $c['nombre'],
                    'votos'         => $c['votos'],
                ])->values(),
            ];
        })->values();

        return response()->json([
            'total'  => $grupos->sum('total'),
            'grupos' => $grupos,
        ]);
    }
}
