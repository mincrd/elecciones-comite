<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->string('foto_path', 2048)->nullable()->after('valores');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropColumn('foto_path');
        });
    }
};
