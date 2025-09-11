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
        // El método up() es para aplicar los cambios
        Schema::table('empleados_habiles', function (Blueprint $table) {
            // Agregamos las dos columnas después de 'grupo_ocupacional' para mantener el orden
            $table->string('cargo')->nullable()->after('grupo_ocupacional');
            $table->string('lugar_de_funciones')->nullable()->after('cargo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // El método down() es para revertir los cambios si es necesario
        Schema::table('empleados_habiles', function (Blueprint $table) {
            $table->dropColumn(['cargo', 'lugar_de_funciones']);
        });
    }
};
