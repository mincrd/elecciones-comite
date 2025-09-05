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
        Schema::create('registro_votos', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable()->unique();
            $table->string('no_empleado')->nullable()->unique();
            $table->enum('grupo_ocupacional', ['I', 'II', 'III', 'IV', 'V']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registro_votos');
    }
};
