<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Postulante extends Model
{
    protected $fillable = [
        'proceso_id', 'nombre_completo', 'cargo', 'email', 'telefono',
        'grupo_ocupacional', 'valores', 'foto_path'
    ];

    protected $casts = [
        'valores' => 'array',
    ];

    protected $appends = ['foto_url'];

    public function proceso()
    {
        return $this->belongsTo(Proceso::class);
    }

    public function votos()
    {
        return $this->hasMany(Voto::class);
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto_path ? Storage::disk('public')->url($this->foto_path) : null;
    }
}
