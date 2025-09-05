<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject; // <-- ¡Importante!

// El modelo de votante ahora es compatible con JWT
class RegistroVoto extends Model implements JWTSubject
{
    use HasFactory;

    protected $fillable = [
        'email',
        'no_empleado',
        'grupo_ocupacional',
    ];

    public function voto()
    {
        return $this->hasOne(Voto::class);
    }

    // --- MÉTODOS REQUERIDOS POR JWTSubject ---

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
