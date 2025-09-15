<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HorasController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\EstadoInicialController;

// Preflight CORS
Route::options('{any}', fn () => response()->noContent())->where('any', '.*');

// Healthcheck
Route::get('/health', fn () => ['ok' => true]);

/**
 * Estado inicial: requiere token válido, PERO NO requiere aprobación.
 * Así el frontend puede mostrar “en revisión”, “falta aporte”, etc.
 */
Route::middleware('auth:sanctum')->get('/estado-inicial', [EstadoInicialController::class, 'estado'])
     ->name('estado-inicial');

/**
 * Rutas que sí requieren aprobación
 */
Route::middleware(['auth:sanctum', 'require.approved'])->group(function () {

    // Horas
    Route::post('/horas',      [HorasController::class, 'store']);
    Route::get('/horas/mias',  [HorasController::class, 'index']);

    // Comprobantes
    Route::post('/comprobantes',         [ComprobanteController::class, 'store']);
    Route::get('/comprobantes/estado',   [ComprobanteController::class, 'index']);

    // Unidad (si querés, podés moverla fuera si debe verse antes de aprobar)
    Route::get('/unidad/mia',  [EstadoInicialController::class, 'miUnidad'])->name('unidad.mia');
});