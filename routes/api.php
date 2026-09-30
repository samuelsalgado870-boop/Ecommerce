<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Login: público
Route::post('/login', [AuthController::class, 'login']);

// Rutas que requieren autenticación
Route::middleware('auth:sanctum')->group(function () {

    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // Consultar usuarios
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::get('/usuarios/{id}', [UsuarioController::class, 'show']);

    // Actualizar usuario
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update']);
});

// Crear usuarios: solo Superadministrador (3) y Administrador (4)
Route::middleware([
    'auth:sanctum',
    \App\Http\Middleware\CheckRole::class . ':3,4'
])->group(function () {

    Route::post('/usuarios', [UsuarioController::class, 'store']);

    // Eliminar usuarios
    Route::delete('/usuarios/{id}', [UsuarioController::class, 'destroy']);
});
// Productos públicos
Route::get('/productos', [ProductoController::class, 'index']);
Route::get('/productos/{id}', [ProductoController::class, 'show']);

// Productos protegidos
Route::middleware([
    'auth:sanctum',
    \App\Http\Middleware\CheckRole::class . ':3,4,5'
])->group(function () {
    Route::post('/productos', [ProductoController::class, 'store']);
    Route::put('/productos/{id}', [ProductoController::class, 'update']);
    Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);
});
