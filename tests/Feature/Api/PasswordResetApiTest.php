<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{to: string|null, subject: string|null, body: string}> */
    private array $sentRawMessages = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'fake-sender@example.test',
            'mail.mailers.smtp.password' => 'fake-test-password',
            'mail.from.address' => 'fake-sender@example.test',
        ]);
    }

    private function fakeRawMailer(): void
    {
        $this->sentRawMessages = [];

        Mail::shouldReceive('raw')
            ->andReturnUsing(function (string $text, callable $callback): void {
                $message = new class {
                    public ?string $to = null;
                    public ?string $subject = null;

                    public function to(string $address): self
                    {
                        $this->to = $address;
                        return $this;
                    }

                    public function subject(string $subject): self
                    {
                        $this->subject = $subject;
                        return $this;
                    }
                };

                $callback($message);

                $this->sentRawMessages[] = [
                    'to' => $message->to,
                    'subject' => $message->subject,
                    'body' => $text,
                ];
            });
    }

    private function extractCodeFromMail(string $text): string
    {
        preg_match('/[0-9]{6}/', $text, $matches);
        return $matches[0] ?? '';
    }

    public function test_forgot_password_sends_code_for_registered_user_and_neutral_for_unknown(): void
    {
        $this->fakeRawMailer();
        $user = User::factory()->create(['role' => 'guru', 'email' => 'guru@sekolah.sch.id']);

        // 1. Email terdaftar
        $response = $this->postJson('/api/forgot-password', ['email' => $user->email]);
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Jika email terdaftar, kode verifikasi akan dikirimkan. Periksa kotak masuk atau folder spam.',
            ])
            ->assertJsonMissing(['code']);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        $this->assertCount(1, $this->sentRawMessages);
        $this->assertSame($user->email, $this->sentRawMessages[0]['to']);

        // 2. Email tidak terdaftar -> pesan netral persis sama
        $unknownResponse = $this->postJson('/api/forgot-password', ['email' => 'unknown@sekolah.sch.id']);
        $unknownResponse->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Jika email terdaftar, kode verifikasi akan dikirimkan. Periksa kotak masuk atau folder spam.',
            ]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'unknown@sekolah.sch.id']);
    }

    public function test_forgot_password_validates_email_format(): void
    {
        $response = $this->postJson('/api/forgot-password', ['email' => 'bukan-email-valid']);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_returns_503_if_smtp_unconfigured(): void
    {
        config(['mail.default' => 'log']);

        $response = $this->postJson('/api/forgot-password', ['email' => 'test@sekolah.sch.id']);
        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Email belum dapat dikirim. Administrator perlu mengatur SMTP pada file .env.',
            ]);
    }

    public function test_resend_cooldown_is_enforced_for_60_seconds(): void
    {
        $this->fakeRawMailer();
        $user = User::factory()->create(['email' => 'resend@sekolah.sch.id']);

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        // Resend sebelum 60 detik -> 429
        $resend = $this->postJson('/api/forgot-password', ['email' => $user->email]);
        $resend->assertStatus(429)
            ->assertJsonStructure(['data' => ['resend_cooldown_seconds']]);

        // Setelah 61 detik -> 200 OK
        $this->travel(61)->seconds();
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        $this->travelBack();
    }

    public function test_verify_code_validates_correct_and_wrong_and_expired(): void
    {
        $this->fakeRawMailer();
        $user = User::factory()->create(['email' => 'verify@sekolah.sch.id']);
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        $code = $this->extractCodeFromMail($this->sentRawMessages[0]['body']);
        $this->assertNotEmpty($code);

        // Kode salah
        $wrong = $this->postJson('/api/verify-password-code', [
            'email' => $user->email,
            'code' => $code === '111111' ? '222222' : '111111',
        ]);
        $wrong->assertStatus(422)
            ->assertJson(['success' => false]);

        // Kode kedaluwarsa setelah 10 menit
        $this->travel(11)->minutes();
        $expired = $this->postJson('/api/verify-password-code', [
            'email' => $user->email,
            'code' => $code,
        ]);
        $expired->assertStatus(422);
        $this->travelBack();

        // Kode benar
        $correct = $this->postJson('/api/verify-password-code', [
            'email' => $user->email,
            'code' => $code,
        ]);
        $correct->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['email', 'reset_token']]);
    }

    public function test_reset_password_success_and_login_with_new_password(): void
    {
        $this->fakeRawMailer();
        $user = User::factory()->create([
            'role' => 'pic',
            'email' => 'pic.ruang@sekolah.sch.id',
            'password' => Hash::make('password-lama-123'),
        ]);

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        $code = $this->extractCodeFromMail($this->sentRawMessages[0]['body']);

        $verify = $this->postJson('/api/verify-password-code', [
            'email' => $user->email,
            'code' => $code,
        ]);
        $verify->assertOk();
        $resetToken = $verify->json('data.reset_token');

        // Validasi password < 6 karakter
        $tooShort = $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'reset_token' => $resetToken,
            'password' => '12345',
            'password_confirmation' => '12345',
        ]);
        $tooShort->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Validasi konfirmasi tidak sama
        $mismatch = $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'reset_token' => $resetToken,
            'password' => 'rahasia123',
            'password_confirmation' => 'beda123',
        ]);
        $mismatch->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Validasi pemalsuan email
        $spoof = $this->postJson('/api/reset-password', [
            'email' => 'target.lain@sekolah.sch.id',
            'reset_token' => $resetToken,
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);
        $spoof->assertStatus(422);

        // Reset berhasil
        $success = $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'reset_token' => $resetToken,
            'password' => 'password-baru-456',
            'password_confirmation' => 'password-baru-456',
        ]);
        $success->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Password berhasil diubah. Silakan masuk dengan password baru.',
            ]);

        // Role tidak berubah
        $fresh = $user->fresh();
        $this->assertSame('pic', $fresh->role);
        $this->assertTrue(Hash::check('password-baru-456', $fresh->password));

        // Token reset & kode sudah dikonsumsi (one-time use)
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $reuse = $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'reset_token' => $resetToken,
            'password' => 'password-ketiga-789',
            'password_confirmation' => 'password-ketiga-789',
        ]);
        $reuse->assertStatus(422);

        // Login dengan password baru di /api/login berhasil
        \Illuminate\Support\Facades\RateLimiter::clear(request()->ip() ?: '127.0.0.1');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password-baru-456',
        ]);
        $loginResponse->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['token', 'role']]);
    }

    public function test_reset_password_revokes_all_active_api_tokens_without_affecting_other_users_and_allows_new_login(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->fakeRawMailer();

        $user = User::factory()->create([
            'role' => 'guru',
            'email' => 'guru.reset@sekolah.sch.id',
            'password' => Hash::make('password-lama-123'),
        ]);
        $otherUser = User::factory()->create([
            'role' => 'guru',
            'email' => 'guru.lain@sekolah.sch.id',
            'password' => Hash::make('password-lain-123'),
        ]);

        // Terbitkan 2 token aktif untuk pengguna yang sama (simulasi 2 perangkat berbeda) dan 1 token untuk pengguna lain
        $login1 = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password-lama-123',
        ])->assertOk();
        $oldToken1 = $login1->json('data.token');

        $login2 = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password-lama-123',
        ])->assertOk();
        $oldToken2 = $login2->json('data.token');

        $otherLogin = $this->postJson('/api/login', [
            'email' => $otherUser->email,
            'password' => 'password-lain-123',
        ])->assertOk();
        $otherToken = $otherLogin->json('data.token');

        $this->withToken($oldToken1)->getJson('/api/profile')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->withToken($oldToken2)->getJson('/api/profile')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->withToken($otherToken)->getJson('/api/profile')->assertOk()->assertJsonPath('data.id', $otherUser->id);

        // Jalankan alur reset password API
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        $code = $this->extractCodeFromMail($this->sentRawMessages[0]['body']);
        $verify = $this->postJson('/api/verify-password-code', [
            'email' => $user->email,
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'reset_token' => $verify->json('data.reset_token'),
            'password' => 'password-baru-789',
            'password_confirmation' => 'password-baru-789',
        ])->assertOk()->assertJsonPath('success', true);

        // Seluruh token lama pengguna wajib langsung ditolak (401) dan key cache dibersihkan
        $this->assertFalse(Cache::has('api_token_' . hash('sha256', $oldToken1)));
        $this->assertFalse(Cache::has('api_token_' . hash('sha256', $oldToken2)));
        $this->withToken($oldToken1)->getJson('/api/profile')->assertUnauthorized();
        $this->withToken($oldToken2)->getJson('/api/profile')->assertUnauthorized();

        // Token pengguna lain tidak boleh terpengaruh
        $this->withToken($otherToken)->getJson('/api/profile')->assertOk()->assertJsonPath('data.id', $otherUser->id);

        // Login ulang dengan password baru menghasilkan token baru yang valid, sedangkan token lama tetap 401
        $newLogin = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password-baru-789',
        ])->assertOk();
        $newToken = $newLogin->json('data.token');

        $this->withToken($newToken)->getJson('/api/profile')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->withToken($oldToken1)->getJson('/api/profile')->assertUnauthorized();
        $this->withToken($oldToken2)->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_web_password_reset_also_revokes_active_api_tokens(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->fakeRawMailer();

        $user = User::factory()->create([
            'role' => 'pic',
            'email' => 'pic.webreset@sekolah.sch.id',
            'password' => Hash::make('password-lama-123'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password-lama-123',
        ])->assertOk();
        $oldApiToken = $login->json('data.token');

        $this->withToken($oldApiToken)->getJson('/api/profile')->assertOk();

        // Reset password melalui rute web
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();
        $code = $this->extractCodeFromMail($this->sentRawMessages[0]['body']);
        $this->post(route('password.code.verify'), ['email' => $user->email, 'code' => $code])
            ->assertRedirect(route('password.reset.form'));
        $this->post(route('password.update'), [
            'password' => 'password-web-baru',
            'password_confirmation' => 'password-web-baru',
        ])->assertRedirect(route('login'));

        // Token API lama harus tercabut dan login API baru tetap bekerja
        $this->withToken($oldApiToken)->getJson('/api/profile')->assertUnauthorized();

        $newLogin = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password-web-baru',
        ])->assertOk();

        $this->withToken($newLogin->json('data.token'))->getJson('/api/profile')->assertOk();
    }
}
