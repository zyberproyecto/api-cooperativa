<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\HorasController;
use App\Http\Controllers\AdminComprobantesController;

/*
|--------------------------------------------------------------------------
| API Routes - Cooperativa
|-------------------------------------------------------------------------- 
| Público:    /ping, /health, /solicitudes (landing)
| Socio:      /horas, /comprobantes   (auth:sanctum)
| Admin:      /admin/*                (auth:sanctum + ability:admin)
|--------------------------------------------------------------------------
*/

// --- Preflight CORS (opcional pero útil si servís front desde 5500) ---
Route::options('{any}', fn () => response()->noContent())->where('any', '.*');

// --- Público (sin auth) ---
Route::get('/ping', fn () => response()->json([
    'ok'   => true,
    'app'  => 'api-cooperativa',
    'time' => now()->toISOString(),
]));

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// Landing: crea/actualiza solicitud (NO requiere token)
Route::post('/solicitudes', [SolicitudController::class, 'store'])->name('api.solicitudes.store');
// (Opcional) listar para debug: /api/solicitudes?estado=...&ci=...
Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('api.solicitudes.index');

// --- Socio autenticado (token Sanctum) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn (Request $request) => ['ok' => true, 'user' => $request->user()]);

    // Horas del socio (propias)
    Route::get('/horas',  [HorasController::class, 'index']);
    Route::post('/horas', [HorasController::class, 'store']);

    // Comprobantes del socio (propios)
    Route::get('/comprobantes', [ComprobanteController::class, 'index']);
    Route::post('/comprobantes', [ComprobanteController::class, 'store']);

    // Alias v1 (compat)
    Route::prefix('v1')->group(function () {
        Route::get('/horas/mias',        [HorasController::class, 'index']);
        Route::get('/comprobantes/mios', [ComprobanteController::class, 'index']);
    });
});

// --- Admin (token + Sanctum abilities:admin) ---
Route::middleware(['auth:sanctum', 'abilities:admin'])->prefix('admin')->group(function () {
    // HORAS (gestión admin)
    Route::get('/horas',               [HorasController::class, 'adminIndex']);
    Route::put('/horas/{id}/validar',  [HorasController::class, 'validar']);
    Route::put('/horas/{id}/rechazar', [HorasController::class, 'rechazar']);

    // COMPROBANTES (gestión admin)
    Route::get('/comprobantes',               [AdminComprobantesController::class, 'index']);
    Route::put('/comprobantes/{id}/validar',  [AdminComprobantesController::class, 'validar']);
    Route::put('/comprobantes/{id}/rechazar', [AdminComprobantesController::class, 'rechazar']);
});