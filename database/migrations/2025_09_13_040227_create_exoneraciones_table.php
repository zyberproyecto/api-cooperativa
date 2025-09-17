<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exoneraciones', function (Blueprint $t) {
            $t->engine    = 'InnoDB';
            $t->charset   = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->bigIncrements('id');
            $t->string('ci_usuario', 8)->collation('utf8mb4_unicode_ci');
            $t->date('semana_inicio');    // semana a la que aplica
            $t->text('motivo');
            $t->enum('estado', ['pendiente','aprobada','rechazada'])->default('pendiente')->index();
            $t->text('resolucion_admin')->nullable();
            $t->string('archivo', 255)->nullable(); // anexo opcional
            $t->timestamps();
            $t->foreign('ci_usuario')
              ->references('ci_usuario')->on('usuarios')
              ->onDelete('cascade');
            $t->unique(['ci_usuario','semana_inicio'], 'exon_unq_ci_semana');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exoneraciones');
    }
};