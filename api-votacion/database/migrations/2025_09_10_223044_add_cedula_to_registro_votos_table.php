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
        Schema::table('registro_votos', function (Blueprint $table) {
            // Hacemos que los campos anteriores puedan ser nulos para migrarlos
            $table->string('email')->nullable()->change();
            $table->string('no_empleado')->nullable()->change();

            // Agregamos la cédula, que será la nueva clave única.
            // La hacemos nullable() temporalmente para poder añadirla a datos existentes
            // pero la haremos unique()
            $table->string('cedula')->nullable()->after('id');
        });

        // Una vez que la columna está agregada, podemos hacerla única
        Schema::table('registro_votos', function (Blueprint $table) {
            $table->unique('cedula');
        });
    }

    public function down(): void
    {
        Schema::table('registro_votos', function (Blueprint $table) {
            $table->dropUnique(['cedula']);
            $table->dropColumn('cedula');

            // Revertir los cambios en email y no_empleado si es necesario
            $table->string('email')->nullable(false)->change();
            $table->string('no_empleado')->nullable(false)->change();
        });
    }
};
