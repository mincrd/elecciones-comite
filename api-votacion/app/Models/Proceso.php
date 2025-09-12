<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proceso extends Model
{
    use HasFactory;

    // --- NUEVOS ESTADOS ---

    public const ESTADO_NUEVO     = 'Nuevo';
    public const ESTADO_ABIERTO   = 'Abierto';
    public const ESTADO_CERRADO   = 'Cerrado';
    public const ESTADO_CONCLUIDO = 'Concluido';
    public const ESTADO_CANCELADO = 'Cancelado';

    public static function estados(): array
    {
        return [
            self::ESTADO_ABIERTO,
            self::ESTADO_CERRADO,
            self::ESTADO_CANCELADO,
            self::ESTADO_CONCLUIDO,
        ];
    }



    protected $fillable = [
        'ano',
        'desde',
        'hasta',
        'estado',
    ];

    /**
     * Relación para obtener todos sus logs de auditoría.
     */
    public function logs()
    {
        return $this->morphMany(LogAuditoria::class, 'auditable');
    }
    public function postulantes()
    {
        return $this->hasMany(Postulante::class);
    }
}
