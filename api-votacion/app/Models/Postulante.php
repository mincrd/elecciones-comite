<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Postulante extends Model
{
    protected $fillable = [
        'proceso_id', 'nombre_completo', 'cargo', 'email', 'telefono',
        'grupo_ocupacional', 'valores'
    ];

    protected $casts = [
        'valores' => 'array', // Convierte el JSON a un array automáticamente
    ];

    public function proceso()
    {
        return $this->belongsTo(Proceso::class);
    }

    public function votos()
    {
        return $this->hasMany(Voto::class);
    }
}
