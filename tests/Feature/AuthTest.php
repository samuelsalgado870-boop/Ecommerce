<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\BackendTestCase;

class AuthTest extends BackendTestCase
{
    public function test_active_user_logs_in_with_expiring_hashed_token_and_reset_attempts(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['intentos_fallidos' => 2]);

        $response = $this->postJson('/api/login', ['email' => strtoupper($user->email), 'password' => 'Test-password-123']);

        $response->assertOk()->assertJsonStructure(['token', 'usuario' => ['id_usuario', 'nombre', 'email']]);
        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertSame(['api'], $token->abilities);
        $this->assertNotSame($response->json('token'), $token->token);
        $this->assertSame(now()->addMinutes(120)->toDateTimeString(), $token->expires_at->toDateTimeString());
        $this->assertDatabaseHas('usuarios', ['id_usuario' => $user->getKey(), 'intentos_fallidos' => 0]);
        $this->assertDatabaseHas('auditoria', ['accion' => 'LOGIN', 'id_usuario' => $user->getKey()]);
        $this->assertStringNotContainsString('password_hash', $response->getContent());
    }

    #[TestWith(['inactive'])]
    #[TestWith(['blocked'])]
    public function test_unavailable_account_returns_401_without_token(string $state): void
    {
        $this->freezeTime();
        $user = User::factory()->{$state}()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Test-password-123'])
            ->assertUnauthorized()->assertJsonPath('message', 'Credenciales incorrectas.');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_wrong_password_locks_account_and_revokes_tokens_at_threshold(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['intentos_fallidos' => 4]);
        $user->createToken('old', ['api']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame(5, $user->refresh()->intentos_fallidos);
        $this->assertTrue($user->bloqueado_hasta->isFuture());
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current', ['api']);
        $other = $user->createToken('other', ['api']);

        $this->withToken($current->plainTextToken)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $current->accessToken->getKey()]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->getKey()]);
    }

    public function test_expired_token_returns_401(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $token = $user->createToken('expired', ['api'], now()->subMinute());

        $this->withToken($token->plainTextToken)->getJson('/api/perfil')->assertUnauthorized();
    }

    public function test_disabled_account_cannot_use_previously_issued_token(): void
    {
        $user = User::factory()->inactive()->create();
        $token = $user->createToken('old', ['api']);

        $this->withToken($token->plainTextToken)->getJson('/api/perfil')->assertForbidden();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_registration_hashes_password_and_assigns_only_customer(): void
    {
        $this->postJson('/api/registro', [
            'nombre' => 'Cliente', 'email' => 'CLIENTE@example.test',
            'password' => 'New-password-123', 'password_confirmation' => 'New-password-123',
        ])->assertCreated();
        $user = User::where('email', 'cliente@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('New-password-123', $user->password_hash));
        $this->assertSame(['cliente'], $user->roles()->pluck('codigo')->all());
    }

    public function test_password_recovery_sends_native_notification_without_exposing_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk()
            ->assertExactJson(['message' => 'Si la cuenta es elegible, recibirás instrucciones.']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_uses_custom_hash_column_and_revokes_old_credentials(): void
    {
        $user = User::factory()->create();
        $user->createToken('old', ['api']);
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'Updated-password-123', 'password_confirmation' => 'Updated-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('Updated-password-123', $user->refresh()->password_hash));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $audit = DB::table('auditoria')->where('accion', 'PASSWORD_RESET')->first();
        $this->assertStringNotContainsString('Updated-password-123', $audit->valores_nuevos);
    }
}