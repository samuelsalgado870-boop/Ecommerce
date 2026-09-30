<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('usuarios')->orderBy('id_usuario')->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    DB::table('usuarios')->where('id_usuario', $user->id_usuario)
                        ->update(['email' => mb_strtolower(trim($user->email))]);
                }
            }, 'id_usuario');
        });
    }

    public function down(): void
    {
        // Canonicalizing an identity cannot reconstruct its prior casing safely.
    }
};
