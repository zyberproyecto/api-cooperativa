<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        Schema::create('unidades', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 50)->unique();   // ej. “A-12” o similar
            $table->string('etapa', 50)->nullable();  // etapa de obra
            $table->unsignedTinyInteger('dormitorios')->nullable(); // 1..3
            $table->enum('estado', ['asignada', 'disponible', 'en_obra'])->default('disponible');
            $table->timestamps();
        });

        // Relación N:1 (cada usuario con 0..1 unidad); si querés permitir múltiple, mantené N:M
        Schema::create('usuario_unidad', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ci_usuario', 20)->index();
            $table->foreign('ci_usuario')->references('ci_usuario')->on('usuarios')->cascadeOnUpdate()->restrictOnDelete();

            $table->unsignedBigInteger('unidad_id')->index();
            $table->foreign('unidad_id')->references('id')->on('unidades')->cascadeOnUpdate()->restrictOnDelete();

            $table->timestamp('asignado_en')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('usuario_unidad');
        Schema::dropIfExists('unidades');
    }
};