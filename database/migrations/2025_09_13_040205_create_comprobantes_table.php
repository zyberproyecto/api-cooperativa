<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $t) {
            $t->engine    = 'InnoDB';
            $t->charset   = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';

            $t->bigIncrements('id');
            $t->string('ci_usuario', 8)->collation('utf8mb4_unicode_ci');
            $t->enum('tipo', ['aporte_inicial','aporte_mensual','compensatorio'])->index();
            $t->char('periodo', 7)->nullable(); 
            $t->decimal('monto', 10, 2);
            $t->string('archivo', 255); 
            $t->enum('estado', ['pendiente','aprobado','rechazado'])->default('pendiente')->index();
            $t->text('nota_admin')->nullable();
            $t->unsignedBigInteger('aprobado_por')->nullable();
            $t->timestamp('aprobado_at')->nullable();
            $t->timestamps();

            $t->foreign('ci_usuario')
              ->references('ci_usuario')->on('usuarios')
              ->onDelete('cascade');
            $t->unique(['ci_usuario','tipo','periodo'], 'comprobantes_unq_ci_tipo_per');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};