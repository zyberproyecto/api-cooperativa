<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HorasController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\EstadoInicialController;

Route::options('{any}', fn () => response()->noContent())->where('any', '.*');

Route::get('/health', fn () => ['ok' => true]);

// Autenticado (pendiente o aprobado)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/estado-inicial', [EstadoInicialController::class, 'estado'])->name('estado-inicial');

    // Perfil en Cooperativa
    Route::get('/perfil',  [EstadoInicialController::class, 'perfil'])->name('perfil.show');
    Route::post('/perfil', [EstadoInicialController::class, 'guardarPerfil'])->name('perfil.save');

    // Comprobantes:
    // - Permitimos subir "aporte_inicial" aunque esté PENDIENTE.
    // - El controller validará que si el tipo NO es aporte_inicial -> exige aprobado.
    Route::post('/comprobantes',       [ComprobanteController::class, 'store'])->name('comprobantes.store');
    Route::get('/comprobantes/estado', [ComprobanteController::class, 'index'])->name('comprobantes.index');
});

// Solo APROBADOS
Route::middleware(['auth:sanctum', 'require.approved'])->group(function () {
    Route::post('/horas',     [HorasController::class, 'store'])->name('horas.store');
    Route::get('/horas/mias', [HorasController::class, 'index'])->name('horas.index');

    Route::get('/unidad/mia', [EstadoInicialController::class, 'miUnidad'])->name('unidad.mia');
});