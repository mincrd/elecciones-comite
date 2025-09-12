<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Postulante extends Model
{
    protected $fillable = [
        'proceso_id','nombre_completo','cargo','email','telefono',
        'grupo_ocupacional','valores','foto_path',
    ];

    protected $casts = [
        'valores' => 'array',
    ];

    protected $appends = ['foto_url'];

    public function getFotoUrlAttribute(): ?string
    {
        if (!$this->foto_path) return null;
        // Genera URL usando la ruta del controlador
        return url('media/'.$this->foto_path);
    }

    public function proceso()
    {
        return $this->belongsTo(Proceso::class);
    }

    public function votos()
    {
        return $this->hasMany(Voto::class);
    }
}
