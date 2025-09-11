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
        Schema::create('empleados_habiles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_completo');
            $table->string('cedula')->unique(); // La cédula debe ser única
            $table->enum('grupo_ocupacional', ['I', 'II', 'III', 'IV', 'V']);
            // Agrega aquí cualquier otro campo del Excel que necesites
            // $table->string('cargo');
            // $table->string('departamento');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados_habiles');
    }
};
