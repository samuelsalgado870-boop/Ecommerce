<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Producto> */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        return ['sku' => fake()->unique()->uuid(), 'nombre' => fake()->word(), 'precio' => '12.35', 'activo' => true];
    }
}
