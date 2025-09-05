<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proceso extends Model
{
    protected $fillable = ['ano', 'desde', 'hasta', 'estado'];

    public function postulantes()
    {
        return $this->hasMany(Postulante::class);
    }
}
