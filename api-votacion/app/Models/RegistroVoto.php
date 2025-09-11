<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroVoto extends Model
{
    use HasFactory;

    // Si tu tabla se llama 'registro_votos' (plural por convención), puedes omitir $table.
    // Si se llama distinto, descomenta y ajusta:
    // protected $table = 'registro_votos';

    // Si usaras otro schema/base de datos explícito:
    // protected $table = 'elecciones_comite_db.registro_votos';

    protected $fillable = [
        'cedula',
        'grupo_ocupacional',
        'postulante_id',
        // agrega otros campos si existen (p.ej. 'proceso_id', 'ip', etc.)
    ];

    // Casts recomendados
    protected $casts = [
        'cedula' => 'string',
        'grupo_ocupacional' => 'string',
        'postulante_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relaciones
    public function postulante()
    {
        return $this->belongsTo(Postulante::class, 'postulante_id');
    }
}
