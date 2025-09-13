<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegistroVoto;
use App\Models\Voto;
use App\Models\EmpleadoHabil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GestionVotosController extends Controller
{
    /**
     * Listado de registros de voto (completos e incompletos).
     * Filtros:
     *  - search: por cedula, nombre, cargo, grupo, lugar
     *  - estado: completo|incompleto|todos (default: todos)
     *  - per_page: int (default 15; 0 = sin paginar)
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);
        $search  = trim((string) $request->input('search', ''));
        $estado  = strtolower((string) $request->input('estado', 'todos')); // completo|incompleto|todos

        // Armamos un query con LEFT JOIN a votos y empleados_habiles para enriquecer
        $q = RegistroVoto::query()
            ->leftJoin('votos', 'votos.registro_voto_id', '=', 'registro_votos.id')
            ->leftJoin('empleados_habiles', 'empleados_habiles.cedula', '=', 'registro_votos.cedula')
            ->select([
                'registro_votos.id',
                'registro_votos.cedula',
                'registro_votos.grupo_ocupacional',
                'registro_votos.created_at',
                'registro_votos.updated_at',
                'empleados_habiles.nombre_completo',
                'empleados_habiles.grupo_ocupacional as emp_grupo_ocupacional',
                'empleados_habiles.cargo as emp_cargo',
                'empleados_habiles.lugar_de_funciones as emp_lugar',
                DB::raw('CASE WHEN votos.id IS NULL THEN 0 ELSE 1 END AS completo'),
            ]);

        // Filtro por estado
        if ($estado === 'completo') {
            $q->whereNotNull('votos.id');
        } elseif ($estado === 'incompleto') {
            $q->whereNull('votos.id');
        }

        // Búsqueda
        if ($search !== '') {
            $q->where(function ($qb) use ($search) {
                $qb->where('registro_votos.cedula', 'like', "%{$search}%")
                    ->orWhere('empleados_habiles.nombre_completo', 'like', "%{$search}%")
                    ->orWhere('empleados_habiles.cargo', 'like', "%{$search}%")
                    ->orWhere('empleados_habiles.grupo_ocupacional', 'like', "%{$search}%")
                    ->orWhere('empleados_habiles.lugar_de_funciones', 'like', "%{$search}%");
            });
        }

        $q->orderByDesc('registro_votos.id');

        if ($perPage > 0) {
            return response()->json($q->paginate($perPage));
        }

        return response()->json($q->get());
    }
    public function pendientes() {
        $listado = EmpleadoHabil::query()
            ->leftJoin('registro_votos as rv', 'rv.cedula', '=', 'empleados_habiles.cedula')
            ->whereNull('rv.cedula')
            ->orderBy('empleados_habiles.grupo_ocupacional')
            ->orderBy('empleados_habiles.nombre_completo')
            ->get(['empleados_habiles.*']);

        $resumen = DB::table('empleados_habiles as eh')
            ->leftJoin('registro_votos as rv', 'rv.cedula', '=', 'eh.cedula')
            ->whereNull('rv.cedula')
            ->groupBy('eh.grupo_ocupacional')
            ->select('eh.grupo_ocupacional', DB::raw('COUNT(*) as pendientes'))
            ->orderBy('eh.grupo_ocupacional')
            ->get();

        return response()->json([
            'total_pendientes' => $listado->count(),
            'resumen' => $resumen,
            'listado' => $listado,
        ]);
    }
    /**
     * Totales de completos vs incompletos.
     */
    public function stats(Request $request)
    {
        $base = RegistroVoto::query();

        $totales = $base->count();

        $completos = RegistroVoto::query()
            ->whereHas('voto')
            ->count();

        $incompletos = $totales - $completos;

        return response()->json([
            'totales'     => $totales,
            'completos'   => $completos,
            'incompletos' => $incompletos,
        ]);
    }

    /**
     * Detalle de un registro de voto + flag de completo.
     */
    public function show(RegistroVoto $registro)
    {
        $empleado = EmpleadoHabil::where('cedula', $registro->cedula)->first();
        $hasVoto  = (bool) $registro->voto; // solo booleano

        return response()->json([
            'registro'  => $registro->only(['id','cedula','grupo_ocupacional','created_at','updated_at']),
            'empleado'  => $empleado ? $empleado->only(['cedula','nombre_completo','grupo_ocupacional','cargo','lugar_de_funciones']) : null,
            'completo'  => $hasVoto,
        ]);
    }


    /**
     * Anula un voto INCOMPLETO, para "rehabilitar" al empleado.
     * Acepta:
     *  - registro_voto_id  (opcional)
     *  - cedula            (opcional)
     *  - all               (bool, opcional) => si true y se usa 'cedula', anula TODOS los registros incompletos de esa cédula
     *
     * Reglas:
     *  - SOLO se puede anular si NO existe 'votos' asociado (incompleto).
     *  - Si es completo, retorna 422.
     */
    public function anularIncompleto(Request $request)
    {
        $data = $request->validate([
            'registro_voto_id' => ['nullable', 'integer', 'min:1'],
            'cedula'           => ['nullable', 'string'],
            'all'              => ['sometimes', 'boolean'],
        ]);

        if (empty($data['registro_voto_id']) && empty($data['cedula'])) {
            return response()->json(['message' => 'Debe enviar "registro_voto_id" o "cedula".'], 422);
        }

        // Construye colección objetivo
        if (!empty($data['registro_voto_id'])) {
            $reg = RegistroVoto::find($data['registro_voto_id']);
            if (!$reg) {
                return response()->json(['message' => 'Registro de voto no encontrado.'], 404);
            }
            $objetivo = collect([$reg]);
        } else {
            $objetivo = RegistroVoto::where('cedula', $data['cedula'])
                ->orderByDesc('id')
                ->get();

            if ($objetivo->isEmpty()) {
                return response()->json(['message' => 'No hay registros de voto para esa cédula.'], 404);
            }

            // Si no se pidió "all", nos quedamos con el más reciente
            if (!($data['all'] ?? false)) {
                $objetivo = collect([$objetivo->first()]);
            }
        }

        // Filtra SOLO los incompletos (sin 'voto')
        $incompletos = $objetivo->filter(fn ($r) => !$r->voto);

        if ($incompletos->isEmpty()) {
            return response()->json(['message' => 'No hay registros incompletos para anular (ya existe voto completo).'], 422);
        }

        $ids = $incompletos->pluck('id')->all();

        DB::transaction(function () use ($ids) {
            RegistroVoto::whereIn('id', $ids)->delete();
        });

        return response()->json([
            'ok'         => true,
            'anulados'   => count($ids),
            'ids'        => $ids,
            'mensaje'    => 'Registro(s) incompleto(s) anulado(s). El empleado podrá volver a votar.',
        ]);
    }
}
