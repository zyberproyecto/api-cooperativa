<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Unidades
        Schema::create('unidades', function (Blueprint $t) {
            $t->engine    = 'InnoDB';
            $t->charset   = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';

            $t->bigIncrements('id');
            $t->string('codigo', 50)->unique();
            $t->string('descripcion', 191)->nullable();
            $t->tinyInteger('dormitorios')->nullable();
            $t->decimal('m2', 6, 2)->nullable();
            $t->enum('estado_unidad', ['disponible','asignada','entregada'])->default('disponible');
            $t->timestamps();
        });

        // Relación usuario-unidad
        Schema::create('usuario_unidad', function (Blueprint $t) {
            $t->engine    = 'InnoDB';
            $t->charset   = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';

            $t->bigIncrements('id');

            // MATCHEA EXACTO con usuarios.ci_usuario (VARCHAR(8))
            $t->string('ci_usuario', 8)->collation('utf8mb4_unicode_ci');

            // FK local a unidades
            $t->unsignedBigInteger('unidad_id');

            $t->date('fecha_asignacion');
            $t->enum('estado', ['activa','liberada'])->default('activa');
            $t->text('nota_admin')->nullable();
            $t->timestamps();

            // FKs
            $t->foreign('ci_usuario')
              ->references('ci_usuario')->on('usuarios')
              ->onDelete('cascade');

            $t->foreign('unidad_id')
              ->references('id')->on('unidades')
              ->onDelete('restrict');

            $t->index(['ci_usuario','estado']);
            $t->unique(['ci_usuario','unidad_id'], 'uniq_ci_unidad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_unidad');
        Schema::dropIfExists('unidades');
    }
};