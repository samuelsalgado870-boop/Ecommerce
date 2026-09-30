<?php

namespace App\Services;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(private AuditService $audit) {}

    public function register(array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $role = Rol::where('codigo', 'cliente')->where('activo', true)->firstOrFail();
            $user = new User;
            $user->fill(['nombre' => $data['nombre'], 'email' => mb_strtolower(trim($data['email']))]);
            $user->password_hash = $data['password'];
            $user->activo = true;
            $user->save();
            $user->roles()->attach($role->getKey(), ['asignado_por' => $actor?->getKey(), 'asignado_en' => now()]);
            $this->audit->record($actor ?? $user, 'CREATE', 'usuarios', $user->getKey(), [], ['activo' => true]);

            return $user;
        }, 3);
    }

    public function update(User $actor, User $target, array $data): User
    {
        return DB::transaction(function () use ($actor, $target, $data): User {
            $target = User::whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('update', $target);
            if (isset($data['password'])) {
                if (! Hash::check($data['current_password'], $actor->getAuthPassword())) {
                    throw ValidationException::withMessages(['current_password' => 'Contraseña actual incorrecta.']);
                }
                $target->password_hash = $data['password'];
                $this->revokeCredentials($target);
            }
            $target->fill(array_intersect_key($data, array_flip(['nombre', 'email'])));
            $target->save();
            $this->audit->record($actor, 'UPDATE', 'usuarios', $target->getKey());

            return $target;
        }, 3);
    }

    public function revokeCredentials(User $user): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
        $user->remember_token = \Illuminate\Support\Str::random(60);
    }
}
