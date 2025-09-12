<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('log_auditoria', function (Blueprint $table) {
            $table->id();
            // Quien hizo la acción (asume que tienes una tabla de usuarios)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            // Qué acción se realizó
            $table->string('accion'); // Ej: CAMBIO_ESTADO_PROCESO, ANULACION_VOTO

            // Descripción detallada o justificación
            $table->text('descripcion');

            // Para relacionar el log con cualquier otro modelo (Proceso, Voto, etc.)
            $table->morphs('auditable'); // Crea auditable_id y auditable_type

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_auditoria');
    }
};
