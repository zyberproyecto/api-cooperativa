<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Relación con usuario por CI
            $table->string('ci_usuario', 20)->index();
            $table->foreign('ci_usuario')
                  ->references('ci_usuario')
                  ->on('usuarios')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // Tipo de comprobante: aporte inicial o mensual
            $table->enum('tipo', ['aporte_inicial', 'mensual']);

            // Archivo público (Storage::url)
            $table->string('archivo', 500)->nullable();

            // Meta
            $table->decimal('monto', 12, 2)->nullable();
            $table->date('fecha_pago')->nullable();

            // Estado: pendiente | aprobado | rechazado
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado'])
                  ->default('pendiente');

            // Nota/motivo de rechazo
            $table->text('motivo_rechazo')->nullable();
            $table->text('nota_admin')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('comprobantes');
    }
};