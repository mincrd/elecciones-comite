<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/EmpleadoHabil.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;


class EmpleadoHabil extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre_completo',
        'cedula',
        'grupo_ocupacional',
        'cargo', // <-- AÑADIR ESTA LÍNEA
        'lugar_de_funciones',
    ];
}
