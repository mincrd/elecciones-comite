<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LogAuditoria extends Model
{
    use HasFactory;

    protected $table = 'log_auditoria';

    protected $fillable = [
        'user_id',
        'accion',
        'descripcion',
        'auditable_id',
        'auditable_type',
    ];

    /**
     * Obtiene el modelo padre (Proceso, Voto, etc.) que fue auditado.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Obtiene el usuario que realizó la acción.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
