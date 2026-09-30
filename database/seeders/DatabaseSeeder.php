<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
            if (! app()->environment('local') || ! config('commerce.dev_admin_email') || ! config('commerce.dev_admin_password')) {
                return;
            }
            if (strlen(config('commerce.dev_admin_password')) < 12 || strlen(config('commerce.dev_admin_password')) > 72) {
                throw new \RuntimeException('DEV_ADMIN_PASSWORD debe tener entre 12 y 72 caracteres.');
            }
            $user = User::firstOrNew(['email' => mb_strtolower(trim(config('commerce.dev_admin_email')))]);
            if ($user->exists) {
                return;
            }
            $user->nombre = 'Administrador de desarrollo';
            $user->password_hash = config('commerce.dev_admin_password');
            $user->activo = true;
            $user->save();
            $user->roles()->attach(Rol::where('codigo', 'superadministrador')->firstOrFail()->getKey(), ['asignado_en' => now()]);
        });
    }
}
