<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Identificador (compatibilidad, pero preferimos ci_usuario)
            $table->string('ci_usuario', 20)->nullable()->index();
            $table->string('ci', 20)->nullable()->index();

            // Datos personales
            $table->string('nombre', 200)->nullable();          // nombre de pila o parcial
            $table->string('nombre_completo', 200)->nullable(); // opcional
            $table->string('email', 190);                      // obligatorio
            $table->string('telefono', 50)->nullable();

            // Info adicional
            $table->boolean('menores_a_cargo')->default(false);
            $table->unsignedTinyInteger('dormitorios')->nullable(); // ej. 1, 2, 3

            $table->text('comentarios')->nullable();
            $table->text('mensaje')->nullable();
            $table->string('intereses', 100)->nullable();

            // Estado de la solicitud
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])
                  ->default('pendiente');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('solicitudes');
    }
};