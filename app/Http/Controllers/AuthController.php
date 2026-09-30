<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessConflict;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, AuditService $audit): JsonResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email', 'max:150'], 'password' => ['required', 'string', 'max:1024']]);
        $result = DB::transaction(function () use ($data, $audit): ?array {
            $user = User::where('email', $data['email'])->lockForUpdate()->first();
            if (! $user || ! $user->disponible()) {
                return null;
            }
            if (! Hash::check($data['password'], $user->getAuthPassword())) {
                $user->intentos_fallidos++;
                if ($user->intentos_fallidos >= config('commerce.login_attempts')) {
                    $user->bloqueado_hasta = now()->addMinutes(config('commerce.lock_minutes'));
                    $user->tokens()->delete();
                }
                $user->save();

                return null;
            }
            $user->intentos_fallidos = 0;
            $user->bloqueado_hasta = null;
            $user->ultimo_acceso = now();
            if (Hash::needsRehash($user->getAuthPassword())) {
                $user->password_hash = $data['password'];
            }
            $user->save();
            $token = $user->createToken('api', ['api'], now()->addMinutes(config('commerce.token_minutes')));
            $audit->record($user, 'LOGIN', 'usuarios', $user->getKey());

            return ['token' => $token->plainTextToken, 'usuario' => new UserResource($user)];
        }, 3);

        return $result ? response()->json($result) : response()->json(['message' => 'Credenciales incorrectas.'], 401);
    }

    public function register(Request $request, UserService $users): JsonResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:usuarios,email'],
            'password' => ['required', 'string', 'max:72', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
            'id_rol' => ['prohibited'], 'roles' => ['prohibited'], 'activo' => ['prohibited'],
        ]);

        return response()->json(['usuario' => new UserResource($users->register($data, $request->user()))], 201);
    }

    public function logout(Request $request, AuditService $audit): JsonResponse
    {
        DB::transaction(function () use ($request, $audit): void {
            $token = $request->user()->currentAccessToken();
            if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $token->delete();
            }
            $audit->record($request->user(), 'LOGOUT', 'usuarios', $request->user()->getKey());
        });

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        if (config('mail.mailers.'.config('mail.default').'.transport') === 'log') {
            throw new BusinessConflict('Configura un transporte de correo que no escriba enlaces de recuperación en logs.');
        }
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email', 'max:150']]);
        Password::sendResetLink($data + ['activo' => true]);

        return response()->json(['message' => 'Si la cuenta es elegible, recibirás instrucciones.']);
    }

    public function resetPassword(Request $request, UserService $users, AuditService $audit): JsonResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'], 'token' => ['required', 'string'],
            'password' => ['required', 'string', 'max:72', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);
        $status = DB::transaction(function () use ($data, $users, $audit): string {
            User::where('email', $data['email'])->lockForUpdate()->first();

            return Password::reset($data + ['activo' => true], function (User $user, string $password) use ($users, $audit): void {
                $user->password_hash = $password;
                $user->intentos_fallidos = 0;
                $user->bloqueado_hasta = null;
                $users->revokeCredentials($user);
                $user->save();
                $audit->record($user, 'PASSWORD_RESET', 'usuarios', $user->getKey());
                event(new PasswordReset($user));
            });
        }, 3);
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => 'Solicitud de recuperación inválida.']);
        }

        return response()->json(['message' => 'Contraseña actualizada.']);
    }
}
