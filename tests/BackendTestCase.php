<?php

namespace Tests;

use App\Models\Producto;
use App\Models\Rol;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

abstract class BackendTestCase extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function userWithRole(string $code = 'cliente'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Rol::where('codigo', $code)->firstOrFail()->getKey(), ['asignado_en' => now()]);

        return $user;
    }

    protected function productWithStock(int $stock, User $actor, string $price = '12.35'): Producto
    {
        $product = Producto::factory()->create(['precio' => $price]);
        if ($stock > 0) {
            app(InventoryService::class)->change($product->getKey(), 'ENTRADA', $stock, $actor, (string) Str::uuid());
        }

        return $product->refresh();
    }
}