<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


use App\Models\Proceso;
use Illuminate\Support\Facades\DB;

class ResultadoController extends Controller
{
    public function obtenerResultados(Proceso $proceso)
    {
        $resultados = DB::table('postulantes')
            ->join('votos', 'postulantes.id', '=', 'votos.postulante_id')
            ->where('postulantes.proceso_id', $proceso->id)
            ->select('postulantes.id', 'postulantes.nombre_completo', DB::raw('count(votos.id) as total_votos'))
            ->groupBy('postulantes.id', 'postulantes.nombre_completo')
            ->orderBy('total_votos', 'desc')
            ->get();

        return response()->json($resultados);
    }
}
