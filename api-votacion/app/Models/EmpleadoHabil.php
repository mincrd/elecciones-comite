<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;


class EmpleadoHabil extends Model
{
    protected $table = 'empleados_habiles';

    protected $fillable = [
        'nombre_completo',
        'cedula',
        'grupo_ocupacional',
        'cargo', // <-- AÑADIR ESTA LÍNEA
        'lugar_de_funciones',
    ];
}
