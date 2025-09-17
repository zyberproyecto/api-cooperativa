<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HorasController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\EstadoInicialController;

Route::options('{any}', fn () => response()->noContent())->where('any', '.*');

Route::get('/health', fn () => ['ok' => true]);

Route::middleware('auth:sanctum')->get('/estado-inicial', [EstadoInicialController::class, 'estado'])
     ->name('estado-inicial');

Route::middleware(['auth:sanctum', 'require.approved'])->group(function () {

    Route::post('/horas',      [HorasController::class, 'store']);
    Route::get('/horas/mias',  [HorasController::class, 'index']);

    Route::post('/comprobantes',         [ComprobanteController::class, 'store']);
    Route::get('/comprobantes/estado',   [ComprobanteController::class, 'index']);

    Route::get('/unidad/mia',  [EstadoInicialController::class, 'miUnidad'])->name('unidad.mia');
});