<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Postulante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostulanteController extends Controller
{
    /**
     * GET /api/admin/postulantes?proceso_id=1&q=texto
     */
    public function index(Request $request)
    {
        $q = Postulante::query()->latest('id');

        if ($request->filled('proceso_id')) {
            $q->where('proceso_id', $request->integer('proceso_id'));
        }

        if ($request->filled('q')) {
            $term = trim($request->get('q'));
            $q->where(function ($qq) use ($term) {
                $qq->where('nombre_completo', 'like', "%{$term}%")
                    ->orWhere('grupo_ocupacional', 'like', "%{$term}%")
                    ->orWhere('cargo', 'like', "%{$term}%");
            });
        }

        return response()->json($q->get());
    }

    public function store(Request $request)
    {
        // Si 'valores' viene como string JSON en FormData, decodificar antes de validar
        if ($request->has('valores') && is_string($request->valores)) {
            $decoded = json_decode($request->valores, true);
            $request->merge(['valores' => is_array($decoded) ? $decoded : []]);
        }

        $data = $request->validate([
            'proceso_id'        => ['required', 'integer', 'exists:procesos,id'],
            'nombre_completo'   => ['required', 'string', 'max:255'],
            'cargo'             => ['nullable', 'string', 'max:255'],
            'email'             => ['nullable', 'email', 'max:255'],
            'telefono'          => ['nullable', 'string', 'max:50'],
            'grupo_ocupacional' => ['required', 'string', 'max:100'],
            'valores'           => ['nullable', 'array'],
            'foto'              => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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

        // Incluir accessor (foto_url) con fresh()
        return response()->json($postulante->fresh(), 201);
    }

    public function show(Postulante $postulante)
    {
        return response()->json($postulante);
    }

    public function update(Request $request, Postulante $postulante)
    {
        // Decodifica 'valores' si viene como string JSON antes de validar
        if ($request->has('valores') && is_string($request->valores)) {
            $decoded = json_decode($request->valores, true);
            $request->merge(['valores' => is_array($decoded) ? $decoded : []]);
        }

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

        // Si llegó nueva foto en este mismo update
        if ($request->hasFile('foto')) {
            // Elimina la anterior si existe
            if ($postulante->foto_path && Storage::disk('public')->exists($postulante->foto_path)) {
                Storage::disk('public')->delete($postulante->foto_path);
            }
            $data['foto_path'] = $request->file('foto')->store('postulantes', 'public');
        }

        // Actualiza resto de campos (solo los presentes)
        $postulante->fill($data);
        $postulante->save();

        return response()->json($postulante->fresh());
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

    /**
     * POST /api/admin/postulantes/{postulante}/foto
     * Subida/cambio de foto individual.
     */
    public function updateFoto(Request $request, Postulante $postulante)
    {
        $request->validate([
            'foto' => ['required','image','mimes:jpg,jpeg,png,webp','max:10240'],
        ], [
            'foto.image' => 'El archivo debe ser una imagen.',
            'foto.mimes' => 'Formatos permitidos: jpg, jpeg, png, webp.',
            'foto.max'   => 'La imagen no puede exceder 2MB.',
        ]);

        // Elimina la foto anterior si existía
        if ($postulante->foto_path && Storage::disk('public')->exists($postulante->foto_path)) {
            Storage::disk('public')->delete($postulante->foto_path);
        }

        // Guarda nueva foto
        $path = $request->file('foto')->store('postulantes', 'public');
        $postulante->foto_path = $path;
        $postulante->save();

        // URL pública vía controlador /media (evita symlink)
        $publicUrl = url('media/'.$path);

        return response()->json([
            'message'    => 'Foto actualizada correctamente.',
            'postulante' => $postulante->fresh(),
            'foto_url'   => $publicUrl,
        ]);
    }
}
