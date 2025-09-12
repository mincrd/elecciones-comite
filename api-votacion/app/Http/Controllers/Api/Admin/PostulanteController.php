<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Postulante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PostulanteController extends Controller
{
    public function index()
    {
        // Si quieres paginar, cámbialo por paginate()
        return response()->json(Postulante::latest('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'proceso_id'        => ['required', 'integer', 'exists:procesos,id'],
            'nombre_completo'   => ['required', 'string', 'max:255'],
            'cargo'             => ['nullable', 'string', 'max:255'],
            'email'             => ['nullable', 'email', 'max:255'],
            'telefono'          => ['nullable', 'string', 'max:50'],
            'grupo_ocupacional' => ['required', 'string', 'max:100'],
            'valores'           => ['nullable', 'array'],
            'foto'              => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], // 2MB
        ], [
            'foto.image' => 'El archivo debe ser una imagen.',
            'foto.mimes' => 'Formatos permitidos: jpg, jpeg, png, webp.',
            'foto.max'   => 'La imagen no puede exceder 2MB.',
        ]);

        // Guardar imagen si llega
        if ($request->hasFile('foto')) {
            $data['foto_path'] = $request->file('foto')->store('postulantes', 'public');
        }

        $postulante = Postulante::create($data);
        return response()->json($postulante, 201);
    }

    public function show(Postulante $postulante)
    {
        return response()->json($postulante);
    }

    public function update(Request $request, Postulante $postulante)
    {
        $data = $request->validate([
            'proceso_id'        => ['sometimes', 'required', 'integer', 'exists:procesos,id'],
            'nombre_completo'   => ['sometimes', 'required', 'string', 'max:255'],
            'cargo'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'             => ['sometimes', 'nullable', 'email', 'max:255'],
            'telefono'          => ['sometimes', 'nullable', 'string', 'max:50'],
            'grupo_ocupacional' => ['sometimes', 'required', 'string', 'max:100'],
            'valores'           => ['sometimes', 'nullable', 'array'],
            'foto'              => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('foto')) {
            // Borrar la anterior si existe
            if ($postulante->foto_path && Storage::disk('public')->exists($postulante->foto_path)) {
                Storage::disk('public')->delete($postulante->foto_path);
            }
            $data['foto_path'] = $request->file('foto')->store('postulantes', 'public');
        }

        $postulante->update($data);
        return response()->json($postulante);
    }

    public function destroy(Postulante $postulante)
    {
        // Eliminar imagen asociada
        if ($postulante->foto_path && Storage::disk('public')->exists($postulante->foto_path)) {
            Storage::disk('public')->delete($postulante->foto_path);
        }
        $postulante->delete();

        return response()->json(null, 204);
    }
    public function updateFoto(Request $request, Postulante $postulante)
    {
        // Validación: imagen JPG/PNG/WebP hasta 2MB
        $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Elimina la foto anterior si existía
        if ($postulante->foto_path && Storage::disk('public')->exists($postulante->foto_path)) {
            Storage::disk('public')->delete($postulante->foto_path);
        }

        // Guarda nueva foto en storage/app/public/postulantes
        $path = $request->file('foto')->store('postulantes', 'public');

        // Persiste path
        $postulante->foto_path = $path;
        $postulante->save();

        // Responde con URL pública (requiere 'php artisan storage:link')
        return response()->json([
            'message'    => 'Foto actualizada correctamente.',
            'postulante' => $postulante->fresh(),
            'foto_url'   => Storage::url($path),
        ]);
    }
}
