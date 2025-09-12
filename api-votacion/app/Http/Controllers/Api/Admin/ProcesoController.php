<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proceso;
use App\Models\LogAuditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProcesoController extends Controller
{

    public function index()
    {
        $procesos = Proceso::all();
        return response()->json($procesos);
    }

    public function store(Request $request)
    {
        $request->validate([
            'ano' => 'required|digits:4|unique:procesos,ano',
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $data = $request->all();
        // Todos los procesos nuevos inician en estado 'Nuevo'
        $data['estado'] = Proceso::ESTADO_NUEVO;

        $proceso = Proceso::create($data);

        // Registrar en auditoría la creación
        LogAuditoria::create([
            'user_id' => Auth::id(),
            'accion' => 'CREACION_PROCESO',
            'descripcion' => "Se creó el proceso para el año {$proceso->ano}.",
            'auditable_id' => $proceso->id,
            'auditable_type' => Proceso::class,
        ]);

        return response()->json($proceso, 201);
    }

    public function show(Proceso $proceso)
    {
        return $proceso;
    }

    public function update(Request $request, Proceso $proceso)
    {
        // El update solo modifica datos, no el estado.
        $request->validate([
            'ano' => ['sometimes', 'required', 'digits:4', Rule::unique('procesos')->ignore($proceso->id)],
            'desde' => 'sometimes|required|date',
            'hasta' => 'sometimes|required|date|after_or_equal:desde',
        ]);

        $proceso->update($request->only(['ano', 'desde', 'hasta']));
        return response()->json($proceso);
    }

    /**
     * Método dedicado para cambiar el estado de un proceso.
     */


    public function cambiarEstado(Request $request, Proceso $proceso)
    {
        $estadosValidos = [
            Proceso::ESTADO_ABIERTO,
            Proceso::ESTADO_CERRADO,
            Proceso::ESTADO_CANCELADO,
            Proceso::ESTADO_CONCLUIDO,
        ];

        // normaliza por si llega en minúsculas
        $map = [
            'nuevo'     => Proceso::ESTADO_NUEVO,
            'abierto'   => Proceso::ESTADO_ABIERTO,
            'cerrado'   => Proceso::ESTADO_CERRADO,
            'concluido' => Proceso::ESTADO_CONCLUIDO,
            'cancelado' => Proceso::ESTADO_CANCELADO,
        ];
        $raw = (string) $request->input('estado', '');
        $request->merge(['estado' => $map[strtolower($raw)] ?? $raw]);

        $request->validate([
            'estado' => ['required', Rule::in($estadosValidos)],
            'descripcion' => [
                Rule::requiredIf(fn () => $request->input('estado') === Proceso::ESTADO_CANCELADO),
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $nuevoEstado    = $request->string('estado');
        $estadoAnterior = $proceso->estado;

        if ($nuevoEstado === Proceso::ESTADO_ABIERTO) {
            Proceso::where('estado', Proceso::ESTADO_ABIERTO)
                ->where('id', '!=', $proceso->id)
                ->update(['estado' => Proceso::ESTADO_CERRADO]);
        }

        $proceso->estado = $nuevoEstado;
        $proceso->save();

        $descripcionLog = $request->input('descripcion')
            ?: "El proceso cambió de estado '{$estadoAnterior}' a '{$nuevoEstado}'.";

        LogAuditoria::create([
            'user_id'        => Auth::id(),
            'accion'         => 'CAMBIO_ESTADO_PROCESO',
            'descripcion'    => $descripcionLog,
            'auditable_id'   => $proceso->id,
            'auditable_type' => Proceso::class,
        ]);

        return response()->json($proceso);
    }


    public function destroy(Proceso $proceso)
    {
        // Considerar si realmente se deben poder eliminar procesos o solo cancelarlos.
        // Si se elimina, se podría registrar en la auditoría también.
        $proceso->delete();
        return response()->json(null, 204);
    }
}
