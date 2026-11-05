<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
}
