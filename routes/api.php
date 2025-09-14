<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HorasController;
use App\Http\Controllers\ComprobanteController;

// Preflight CORS
Route::options('{any}', fn() => response()->noContent())->where('any', '.*');

// Health
Route::get('/health', fn() => ['ok' => true]);

// Endpoints de SOCIO (token Sanctum emitido por api-usuarios)
Route::middleware('auth:sanctum')->group(function () {
    // Horas
    Route::post('/horas', [HorasController::class, 'store']);
    Route::get('/horas/mias', [HorasController::class, 'index']); // reutiliza tu index como "mias"

    // Comprobantes
    Route::post('/comprobantes', [ComprobanteController::class, 'store']);
    Route::get('/comprobantes/estado', [ComprobanteController::class, 'index']); // reutiliza tu index
});