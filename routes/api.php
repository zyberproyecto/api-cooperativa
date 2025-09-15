<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HorasController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\EstadoInicialController; // ⬅️ NUEVO

// ----------------------
// Preflight CORS (OPTIONS)
// ----------------------
Route::options('{any}', fn () => response()->noContent())
    ->where('any', '.*');

// ----------------------
// Healthcheck
// ----------------------
Route::get('/health', fn () => ['ok' => true]);

// ----------------------
// Endpoints de SOCIO (requieren token Sanctum emitido por api-usuarios)
// ----------------------
Route::middleware('auth:sanctum')->group(function () {

    // ----- Horas de trabajo -----
    Route::post('/horas', [HorasController::class, 'store']);
    Route::get('/horas/mias', [HorasController::class, 'index']); // tu index como "mías"

    // ----- Comprobantes -----
    Route::post('/comprobantes', [ComprobanteController::class, 'store']);
    Route::get('/comprobantes/estado', [ComprobanteController::class, 'index']); // tu index para estado

    // ----- Gate de primer ingreso (NUEVO) -----
    Route::get('/estado-inicial', [EstadoInicialController::class, 'estado'])
        ->name('estado-inicial');

    // ----- Datos de mi unidad asignada (NUEVO) -----
    Route::get('/unidad/mia', [EstadoInicialController::class, 'miUnidad'])
        ->name('unidad.mia');
});