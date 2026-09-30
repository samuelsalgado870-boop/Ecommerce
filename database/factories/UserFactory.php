<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => 'Test-password-123',
            'activo' => true,
            'intentos_fallidos' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['bloqueado_hasta' => now()->addMinutes(15)]);
    }
}
