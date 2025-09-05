<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proceso;
use Illuminate\Http\Request;

class ProcesoController extends Controller
{
    public function index()
    {
        return Proceso::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'ano' => 'required|digits:4',
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
            'estado' => 'required|in:Abierto,Cerrado',
        ]);

        // Si se abre un nuevo proceso, cerrar todos los demás.
        if ($request->estado === 'Abierto') {
            Proceso::where('estado', 'Abierto')->update(['estado' => 'Cerrado']);
        }

        $proceso = Proceso::create($request->all());
        return response()->json($proceso, 201);
    }

    public function show(Proceso $proceso)
    {
        return $proceso;
    }

    public function update(Request $request, Proceso $proceso)
    {
        $request->validate([
            'ano' => 'sometimes|required|digits:4',
            'desde' => 'sometimes|required|date',
            'hasta' => 'sometimes|required|date|after_or_equal:desde',
            'estado' => 'sometimes|required|in:Abierto,Cerrado',
        ]);

        // Si se abre un nuevo proceso, cerrar todos los demás que no sean este.
        if ($request->estado === 'Abierto') {
            Proceso::where('id', '!=', $proceso->id)->update(['estado' => 'Cerrado']);
        }

        $proceso->update($request->all());
        return response()->json($proceso);
    }

    public function destroy(Proceso $proceso)
    {
        $proceso->delete();
        return response()->json(null, 204);
    }
}
