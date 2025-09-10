<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('horas_trabajo', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Relación con usuario por CI
            $table->string('ci_usuario', 20)->index();
            $table->foreign('ci_usuario')
                  ->references('ci_usuario')
                  ->on('usuarios')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // Semana ISO (ej. 2025-W36) o fecha específica
            $table->string('semana', 20)->nullable();
            $table->date('fecha')->nullable();

            // Registro de horas
            $table->unsignedTinyInteger('horas')->nullable();    // regla 21h
            $table->string('actividad', 255)->nullable();
            $table->text('descripcion')->nullable();

            // Estado del registro
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado'])
                  ->default('pendiente');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('horas_trabajo');
    }
};