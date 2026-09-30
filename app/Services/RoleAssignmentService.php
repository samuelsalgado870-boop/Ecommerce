<?php

namespace App\Services;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoleAssignmentService
{
    public function __construct(private AuditService $audit) {}

    public function assign(User $actor, User $target, Rol $role, ?string $expires = null, bool $revoke = false): void
    {
        DB::transaction(function () use ($actor, $target, $role, $expires, $revoke): void {
            $users = User::whereIn('id_usuario', [$actor->getKey(), $target->getKey()])
                ->orderBy('id_usuario')->lockForUpdate()->get()->keyBy('id_usuario');
            $actor = $users[$actor->getKey()];
            $target = $users[$target->getKey()];
            $role = Rol::whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($actor->tienePermiso('usuarios.assign_roles') && ! $actor->is($target), 403);
            abort_unless($revoke || $role->activo, 403);
            $targetIsSuper = $target->roles()->where('codigo', 'superadministrador')->exists();
            if ($role->codigo === 'superadministrador' || $targetIsSuper) {
                abort_unless($actor->tieneRol('superadministrador') && $actor->tienePermiso('usuarios.assign_superadmin'), 403);
            }
            $actorPermissions = $actor->codigosPermisos();
            abort_unless($target->codigosPermisos()->diff($actorPermissions)->isEmpty(), 403);
            if (! $revoke) {
                abort_unless($role->permisos()->pluck('codigo')->diff($actorPermissions)->isEmpty(),
                    403, 'No puedes delegar privilegios que no posees.');
            }
            $pivot = DB::table('usuario_roles')->where('id_usuario', $target->getKey())->where('id_rol', $role->getKey());
            $before = $pivot->first();
            if ($revoke) {
                $pivot->delete();
            } else {
                DB::table('usuario_roles')->updateOrInsert(
                    ['id_usuario' => $target->getKey(), 'id_rol' => $role->getKey()],
                    ['asignado_por' => $actor->getKey(), 'asignado_en' => now(), 'expira_en' => $expires],
                );
            }
            $this->audit->record($actor, $revoke ? 'REVOKE_ROLE' : 'ASSIGN_ROLE', 'usuario_roles', $target->getKey(),
                $before ? ['id_rol' => $before->id_rol] : [],
                ['id_rol' => $role->getKey(), 'expira_en' => $expires]);
        }, 3);
    }
}
