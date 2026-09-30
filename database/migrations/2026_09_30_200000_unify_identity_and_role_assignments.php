<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'sesiones_usuario'] as $table) {
            if (Schema::hasTable($table) && Schema::hasTable($table.'_legacy')) {
                throw new RuntimeException('Ya existe '.$table.'_legacy; revisar adopción del esquema.');
            }
        }
        if (Schema::hasColumn('usuarios', 'id_rol')) {
            $conflict = DB::table('usuarios as u')
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('usuario_roles as r')->whereColumn('r.id_usuario', 'u.id_usuario'))
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('usuario_roles as r')
                    ->whereColumn('r.id_usuario', 'u.id_usuario')->whereColumn('r.id_rol', 'u.id_rol'))->exists();
            if ($conflict) {
                throw new RuntimeException('Roles contradictorios: conciliar usuarios.id_rol con usuario_roles antes de migrar. No se otorgaron privilegios nuevos.');
            }
            DB::transaction(function (): void {
                DB::table('usuarios')->orderBy('id_usuario')->chunkById(500, function ($users): void {
                    foreach ($users as $user) {
                        DB::table('usuario_roles')->insertOrIgnore([
                            'id_usuario' => $user->id_usuario, 'id_rol' => $user->id_rol,
                            'asignado_por' => null, 'asignado_en' => $user->fecha_creacion, 'expira_en' => null,
                        ]);
                    }
                }, 'id_usuario');
            });
            Schema::table('usuarios', function (Blueprint $table): void {
                $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['id_rol'] : 'fk_usuarios_roles');
                $table->dropIndex('idx_usuarios_rol');
                $table->dropColumn('id_rol');
            });
        }
        if (! Schema::hasColumn('usuarios', 'remember_token')) {
            Schema::table('usuarios', fn (Blueprint $table) => $table->rememberToken());
        }
        foreach (['users', 'sesiones_usuario'] as $table) {
            if (Schema::hasTable($table)) {
                if (Schema::hasTable($table.'_legacy')) {
                    throw new RuntimeException('Ya existe '.$table.'_legacy; revisar adopción del esquema.');
                }
                Schema::rename($table, $table.'_legacy');
            }
        }
        // Privilege migration invalidates previous API tokens; session infrastructure remains.
        DB::table('personal_access_tokens')->delete();
        DB::table('sessions')->delete();
    }

    public function down(): void
    {
        // Multiple roles cannot be represented safely by the old non-null single-role column.
        // Fail explicitly rather than choosing a privileged role or destroying assignments.
        if (DB::table('usuarios')->exists()) {
            throw new RuntimeException('Migración de identidad con usuarios irreversible automáticamente. Restaurar backup o aplicar corrección hacia adelante.');
        }
        foreach (['users', 'sesiones_usuario'] as $table) {
            if (Schema::hasTable($table.'_legacy')) {
                Schema::rename($table.'_legacy', $table);
            }
        }
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropColumn('remember_token');
            $table->integer('id_rol');
            $table->index('id_rol', 'idx_usuarios_rol');
            $table->foreign('id_rol', 'fk_usuarios_roles')->references('id_rol')->on('roles')->restrictOnDelete();
        });
    }
};
