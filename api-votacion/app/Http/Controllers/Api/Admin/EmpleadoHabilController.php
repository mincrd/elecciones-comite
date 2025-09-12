<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmpleadoHabil;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmpleadoHabilController extends Controller
{
    /**
     * Muestra una lista de todos los empleados hábiles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10); // 10 por defecto
        $search  = trim((string) $request->input('search', ''));
        $sortBy  = $request->input('sort_by', 'id');      // opcional
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $q = EmpleadoHabil::query();

        if ($search !== '') {
            $q->where(function ($qb) use ($search) {
                $qb->where('nombre_completo', 'like', "%{$search}%")
                    ->orWhere('cedula', 'like', "%{$search}%")
                    ->orWhere('cargo', 'like', "%{$search}%")
                    ->orWhere('grupo_ocupacional', 'like', "%{$search}%")
                    ->orWhere('lugar_de_funciones', 'like', "%{$search}%");
            });
        }

        $q->orderBy($sortBy, $sortDir);

        if ($perPage > 0) {
            return response()->json($q->paginate($perPage));
        }

        // Si per_page=0 → sin paginar
        return response()->json($q->get());
    }

    /**
     * Guarda un nuevo empleado hábil en la base de datos.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nombre_completo' => 'required|string|max:255',
            'cedula' => 'required|string|max:20|unique:empleados_habiles,cedula',
            'grupo_ocupacional' => 'required|string|max:50',
            'cargo' => 'required|string|max:255',
            'lugar_de_funciones' => 'required|string|max:255',
        ]);

        $empleado = EmpleadoHabil::create($validatedData);

        return response()->json($empleado, 201); // 201 Created
    }

    /**
     * Muestra un empleado hábil específico.
     *
     * @param  \App\Models\EmpleadoHabil  $empleadoHabil
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(EmpleadoHabil $empleadoHabil)
    {
        return response()->json($empleadoHabil);
    }

    /**
     * Actualiza un empleado hábil existente.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\EmpleadoHabil  $empleadoHabil
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, EmpleadoHabil $empleadoHabil)
    {
        $validatedData = $request->validate([
            'nombre_completo' => 'required|string|max:255',
            'cedula' => [
                'required',
                'string',
                'max:20',
                // Asegura que la cédula sea única, ignorando el registro actual
                Rule::unique('empleados_habiles')->ignore($empleadoHabil->id),
            ],
            'grupo_ocupacional' => 'required|string|max:50',
            'cargo' => 'required|string|max:255',
            'lugar_de_funciones' => 'required|string|max:255',
        ]);

        $empleadoHabil->update($validatedData);

        return response()->json($empleadoHabil);
    }

    /**
     * Elimina un empleado hábil de la base de datos.
     *
     * @param  \App\Models\EmpleadoHabil  $empleadoHabil
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(EmpleadoHabil $empleadoHabil)
    {
        $empleadoHabil->delete();

        return response()->json(null, 204); // 204 No Content
    }
}
