<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // Asegúrate que extiende de Authenticatable
use Tymon\JWTAuth\Contracts\JWTSubject; // Importa el contrato de JWT

class RegistroVoto extends Authenticatable implements JWTSubject // Implementa el contrato
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    // AÑADE ESTO:
    protected $fillable = [
        'cedula',
        'grupo_ocupacional',
        'postulante_id' // Y uno para el ID del postulante
    ];

    // ... el resto de tu modelo

    // IMPORTANTE: Para que la autenticación con JWT funcione, necesitas estos dos métodos
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
