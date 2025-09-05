<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voto extends Model
{
    protected $fillable = ['registro_voto_id', 'postulante_id'];

    public function votante()
    {
        return $this->belongsTo(RegistroVoto::class, 'registro_voto_id');
    }

    public function postulante()
    {
        return $this->belongsTo(Postulante::class);
    }
}
