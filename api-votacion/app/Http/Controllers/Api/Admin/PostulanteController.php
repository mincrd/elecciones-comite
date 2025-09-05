<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Postulante;
use Illuminate\Http\Request;

class PostulanteController extends Controller
{
    public function index()
    {
        return Postulante::with('proceso')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'proceso_id' => 'required|exists:procesos,id',
            'nombre_completo' => 'required|string|max:255',
            'cargo' => 'required|string|max:255',
            'email' => 'required|email|unique:postulantes,email',
            'telefono' => 'nullable|string',
            'grupo_ocupacional' => 'required|in:I,II,III,IV,V',
            'valores' => 'required|array'
        ]);

        $postulante = Postulante::create($request->all());
        return response()->json($postulante, 201);
    }

    public function show(Postulante $postulante)
    {
        return $postulante->load('proceso');
    }

    public function update(Request $request, Postulante $postulante)
    {
        $request->validate([
            'proceso_id' => 'sometimes|required|exists:procesos,id',
            'nombre_completo' => 'sometimes|required|string|max:255',
            'cargo' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:postulantes,email,' . $postulante->id,
            'grupo_ocupacional' => 'sometimes|required|in:I,II,III,IV,V',
            'valores' => 'sometimes|required|array'
        ]);

        $postulante->update($request->all());
        return response()->json($postulante);
    }

    public function destroy(Postulante $postulante)
    {
        $postulante->delete();
        return response()->json(null, 204);
    }
}
