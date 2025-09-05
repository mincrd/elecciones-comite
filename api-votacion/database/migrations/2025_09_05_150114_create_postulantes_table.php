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
        Schema::create('postulantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('procesos')->onDelete('cascade');
            $table->string('nombre_completo');
            $table->string('cargo');
            $table->string('email')->unique();
            $table->string('telefono')->nullable();
            $table->enum('grupo_ocupacional', ['I', 'II', 'III', 'IV', 'V']);
            $table->json('valores'); // Ideal para guardar la selección múltiple
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postulantes');
    }
};
