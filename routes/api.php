<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrdenController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/registro', [AuthController::class, 'register'])->middleware('throttle:auth-sensitive');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-sensitive');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-sensitive');
Route::get('/productos', [ProductoController::class, 'index']);
Route::get('/productos/{producto}', [ProductoController::class, 'show']);

Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/perfil', [UsuarioController::class, 'profile']);
    Route::get('/usuarios', [UsuarioController::class, 'index'])->middleware('permission:usuarios.read');
    Route::get('/usuarios/{usuario}', [UsuarioController::class, 'show']);
    Route::match(['put', 'patch'], '/usuarios/{usuario}', [UsuarioController::class, 'update']);
    Route::post('/usuarios', [AuthController::class, 'register'])->middleware('permission:usuarios.create');
    Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy']);
    Route::get('/roles', [RolController::class, 'index'])->middleware('permission:usuarios.assign_roles');
    Route::put('/usuarios/{usuario}/roles/{rol}', [RolController::class, 'store'])->middleware('permission:usuarios.assign_roles');
    Route::delete('/usuarios/{usuario}/roles/{rol}', [RolController::class, 'destroy'])->middleware('permission:usuarios.assign_roles');

    Route::post('/productos', [ProductoController::class, 'store'])->middleware('permission:productos.create');
    Route::match(['put', 'patch'], '/productos/{producto}', [ProductoController::class, 'update'])->middleware('permission:productos.update');
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])->middleware('permission:productos.delete');
    Route::get('/productos/{producto}/movimientos', [InventoryController::class, 'index'])->middleware('permission:inventario.read');
    Route::post('/productos/{producto}/movimientos', [InventoryController::class, 'store'])->middleware('permission:inventario.update');

    Route::get('/ordenes', [OrdenController::class, 'index']);
    Route::get('/ordenes/{orden}', [OrdenController::class, 'show']);
    Route::post('/ordenes', [OrdenController::class, 'store'])->middleware('permission:pedidos.create');
    Route::post('/ordenes/{orden}/cancelar', [OrdenController::class, 'cancel']);
});
