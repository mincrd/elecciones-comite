<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /** Sólo admins pueden gestionar usuarios */
    protected function ensureAdmin(Request $request): void
    {
        if ($request->user()->rol !== User::ROL_ADMIN) {
            abort(403, 'Solo los administradores pueden realizar esta acción.');
        }
    }

    /** GET /api/admin/usuarios */
    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        $perPage = (int) $request->input('per_page', 15);
        $search  = trim((string) $request->input('search', ''));

        $q = User::query();

        if ($search !== '') {
            $q->where(function ($qb) use ($search) {
                $qb->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('rol', 'like', "%{$search}%");
            });
        }

        $users = $perPage > 0
            ? $q->orderByDesc('id')->paginate($perPage)
            : $q->orderByDesc('id')->get();

        return response()->json($users);
    }

    /** POST /api/admin/usuarios */
    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'], // password_confirmation
            'rol'      => ['required', Rule::in([User::ROL_ADMIN, User::ROL_SUPERVISOR])],
        ]);

        $user = new User();
        $user->name  = $data['name'];
        $user->email = $data['email'];
        $user->rol   = $data['rol'];
        $user->password = Hash::make($data['password']);
        $user->save();

        return response()->json($user, 201);
    }

    /** GET /api/admin/usuarios/{usuario} */
    public function show(Request $request, User $usuario)
    {
        $this->ensureAdmin($request);
        return response()->json($usuario);
    }

    /** PUT/PATCH /api/admin/usuarios/{usuario} */
    public function update(Request $request, User $usuario)
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'email'    => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'rol'      => ['sometimes', 'required', Rule::in([User::ROL_ADMIN, User::ROL_SUPERVISOR])],
            // Si quieres permitir cambiar la contraseña aquí, déjalo opcional:
            'password' => ['nullable', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);

        if (array_key_exists('name', $data))  $usuario->name  = $data['name'];
        if (array_key_exists('email', $data)) $usuario->email = $data['email'];
        if (array_key_exists('rol', $data))   $usuario->rol   = $data['rol'];

        if (!empty($data['password'])) {
            $usuario->password = Hash::make($data['password']);
        }

        $usuario->save();

        return response()->json($usuario);
    }

    /** DELETE /api/admin/usuarios/{usuario} */
    public function destroy(Request $request, User $usuario)
    {
        $this->ensureAdmin($request);

        if ($request->user()->id === $usuario->id) {
            return response()->json(['message' => 'No puedes eliminar tu propio usuario.'], 422);
        }

        $usuario->delete();

        return response()->json(null, 204);
    }

    /** POST /api/admin/usuarios/{usuario}/password */
    public function changePassword(Request $request, User $usuario)
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);

        $usuario->password = Hash::make($data['password']);
        $usuario->save();

        return response()->json(['ok' => true]);
    }
}
