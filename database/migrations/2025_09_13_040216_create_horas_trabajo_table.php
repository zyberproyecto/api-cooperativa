<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('horas_trabajo', function (Blueprint $t) {
            $t->engine    = 'InnoDB';
            $t->charset   = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->bigIncrements('id');
            $t->string('ci_usuario', 8)->collation('utf8mb4_unicode_ci');
            $t->date('semana_inicio');          // lunes de la semana
            $t->date('semana_fin')->nullable(); // domingo (opcional)
            $t->decimal('horas_reportadas', 4, 1); // ej: 20.5
            $t->text('motivo')->nullable();        // si < 21h
            $t->enum('estado', ['reportado','aprobado','rechazado'])->default('reportado');
            $t->timestamps();
            $t->foreign('ci_usuario')
              ->references('ci_usuario')->on('usuarios')
              ->onDelete('cascade');
            $t->unique(['ci_usuario','semana_inicio'], 'horas_trabajo_unq_ci_semana');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horas_trabajo');
    }
};