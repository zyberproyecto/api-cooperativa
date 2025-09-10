<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('exoneraciones', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Relación con usuarios
            $table->string('ci_usuario', 20)->index();
            $table->foreign('ci_usuario')
                  ->references('ci_usuario')
                  ->on('usuarios')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // Período asociado (ej: misma semana que horas_trabajo)
            $table->string('periodo', 20)->nullable();

            // Motivo de la exoneración
            $table->text('motivo')->nullable();

            // Estado de la solicitud
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado'])
                  ->default('pendiente');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('exoneraciones');
    }
};