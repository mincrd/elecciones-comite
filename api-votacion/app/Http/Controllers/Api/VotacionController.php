<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmpleadoHabil;
use App\Models\LogAuditoria;
use App\Models\Postulante;
use App\Models\Proceso;
use App\Models\RegistroVoto;
use App\Models\Voto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth; // Opcional, pero bueno tenerlo

class VotacionController extends Controller
{

    public function getEstadoVotante($cedula)
    {
        // 1. Validar que exista un proceso de votación activo
        $procesoActivo = Proceso::where('estado', 'Abierto')->first();
        if (!$procesoActivo) {
            return response()->json(['message' => 'No hay un proceso de votación activo en este momento.'], 403);
        }

        // 2. Validar que la cédula no esté vacía
        if (empty($cedula)) {
            return response()->json(['message' => 'La cédula es requerida.'], 400);
        }

        // 3. Buscar si el empleado está en la lista de votantes hábiles
        $empleadoHabil = EmpleadoHabil::where('cedula', $cedula)->first();
        if (!$empleadoHabil) {
            return response()->json(['message' => 'La cédula proporcionada no corresponde a un votante hábil.'], 404);
        }

        $votoYaEmitido = RegistroVoto::where('cedula', $cedula)->exists();

        // 5. Devolver toda la información relevante
        return response()->json([
            'esHabil' => true, // Indica que la persona está en el padrón electoral.
            'yaVoto' => $votoYaEmitido, // Indica si su voto ya fue finalizado.
            'nombre' => $empleadoHabil->nombre_completo,
            'grupo_ocupacional' => $empleadoHabil->grupo_ocupacional,
            'cargo' => $empleadoHabil->cargo,
            'lugar_trabajo' => $empleadoHabil->lugar_de_funciones,
        ]);
    }

    /**
     * PASO 2: Crea el registro y genera el token para iniciar la sesión de votación.
     * (Este método se mantiene igual que en la respuesta anterior).
     */
    public function registrarSesionParaVotar(Request $request)
    {
        $validator = Validator::make($request->all(), ['cedula' => 'required|string']);
        if ($validator->fails()) { return response()->json($validator->errors(), 400); }

        $cedula = $request->cedula;

        $empleadoHabil = EmpleadoHabil::where('cedula', $cedula)->first();
        if (!$empleadoHabil || RegistroVoto::where('cedula', $cedula)->exists()) {
            return response()->json(['message' => 'Acceso no autorizado o cédula ya registrada.'], 403);
        }

        $votante = RegistroVoto::create([
            'cedula' => $empleadoHabil->cedula,
            'grupo_ocupacional' => $empleadoHabil->grupo_ocupacional,
            'voto_emitido' => false // Se inicia la sesión, pero aún no ha votado
        ]);

        if (!$token = auth('api')->fromUser($votante)) {
            return response()->json(['error' => 'No se pudo crear el token'], 500);
        }

        return response()->json(['token' => $token]);
    }

    /**
     * Paso 3: Obtiene los candidatos (este método se mantiene igual).
     */
    public function getCandidatosPorGrupo($grupo)
    {
        $procesoActivo = Proceso::where('estado', 'Abierto')->first();
        if (!$procesoActivo) {
            return response()->json(['message' => 'No hay un proceso de votación activo.'], 404);
        }

        $candidatos = $procesoActivo->postulantes()
            ->where('grupo_ocupacional', $grupo)
            ->get(['id', 'nombre_completo', 'cargo', 'valores']);

        return response()->json($candidatos);
    }

    /**
     * ✅ AJUSTADO: Registra el voto final.
     * Ya no usa token, recibe la cédula y el ID del postulante.
     */
    public function registrarVoto(Request $request)
    {
        // 1. Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'cedula' => 'required|string|exists:empleados_habiles,cedula',
            'postulante_id' => 'required|integer|exists:postulantes,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $cedula = $request->cedula;

        // 2. Seguridad: Verificar que esta cédula no haya votado ya
        if (RegistroVoto::where('cedula', $cedula)->exists()) {
            return response()->json(['message' => 'Este usuario ya ha emitido su voto.'], 403);
        }

        // 3. Seguridad: Verificar que el candidato y el votante son del mismo grupo
        $votanteHabil = EmpleadoHabil::where('cedula', $cedula)->first();
        $postulante = Postulante::find($request->postulante_id);

        if ($votanteHabil->grupo_ocupacional !== $postulante->grupo_ocupacional) {
            return response()->json(['message' => 'Conflicto de grupo. No puede votar por este candidato.'], 403);
        }

        // 4. Si todo es correcto, registrar el voto
        RegistroVoto::create([
            'cedula' => $cedula,
            'postulante_id' => $request->postulante_id,
            'grupo_ocupacional' => $votanteHabil->grupo_ocupacional,
            'voto_fecha' => now(),
        ]);

        return response()->json(['message' => '¡Voto registrado exitosamente!']);
    }

    public function anularVoto(Request $request, Voto $voto)
    {
        $request->validate([
            'razon' => 'required|string|min:10|max:500',
        ]);

        // Lógica para anular el voto (ej. marcarlo como anulado)
        $voto->anulado = true;
        $voto->save();

        $empleado = $voto->empleado->nombre_completo; // Asumiendo relación

        // Registrar en auditoría
        LogAuditoria::create([
            'user_id' => Auth::id(),
            'accion' => 'ANULACION_VOTO',
            'descripcion' => "Se anuló el voto del empleado: {$empleado}. Razón: " . $request->razon,
            'auditable_id' => $voto->id,
            'auditable_type' => Voto::class,
        ]);

        return response()->json(['message' => 'Voto anulado correctamente.']);
    }
}
