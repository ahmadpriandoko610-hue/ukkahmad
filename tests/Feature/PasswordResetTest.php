<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class PasswordResetTest extends TestCase
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

    public function test_all_supported_roles_receive_a_code_and_unknown_email_gets_a_neutral_response(): void
    {
        $this->fakeRawMailer();
        $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
        $this->get(route('password.request'))->assertOk();

        $requestedEmails = [];
        foreach (['guru', 'pic', 'admin', 'siswa'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $requestedEmails[] = $user->email;

            $this->from(route('password.request'))
                ->post(route('password.email'), ['email' => $user->email])
                ->assertRedirect(route('password.code.form', ['email' => $user->email]))
                ->assertSessionHas('status', 'Jika email terdaftar, kode verifikasi telah dikirim. Periksa kotak masuk atau folder spam.');

            $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        }

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'tidak-terdaftar@example.test'])
            ->assertRedirect(route('password.code.form', ['email' => 'tidak-terdaftar@example.test']))
            ->assertSessionHas('status', 'Jika email terdaftar, kode verifikasi telah dikirim. Periksa kotak masuk atau folder spam.')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'tidak-terdaftar@example.test']);
        $this->assertCount(4, $this->sentRawMessages);
        $this->assertSame($requestedEmails, array_column($this->sentRawMessages, 'to'));
        $this->assertSame('Kode Verifikasi Reset Password RAPI-Sekolah', $this->sentRawMessages[0]['subject']);
        $this->assertMatchesRegularExpression('/[0-9]{6}/', $this->sentRawMessages[0]['body']);
    }

    public function test_email_format_is_validated_before_a_code_is_created_or_mailed(): void
    {
        $this->fakeRawMailer();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'format-email-salah'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertCount(0, $this->sentRawMessages);
    }

    public function test_correct_code_is_verified_wrong_code_is_rejected_and_code_is_hashed(): void
    {
        $this->fakeRawMailer();
        $user = User::factory()->create(['role' => 'guru']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.code.form', ['email' => $user->email]));

        $row = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertNotNull($row);
        $this->assertNotSame($this->codeFromMail($this->sentRawMessages[0]['body']), $row->token);
        $this->assertTrue(Hash::check($this->codeFromMail($this->sentRawMessages[0]['body']), $row->token));

        $validCode = $this->codeFromMail($this->sentRawMessages[0]['body']);
        $wrongCode = $validCode === '000000' ? '000001' : '000000';
        $this->from(route('password.code.form', ['email' => $user->email]))
            ->post(route('password.code.verify'), ['email' => $user->email, 'code' => $wrongCode])
            ->assertRedirect(route('password.code.form', ['email' => $user->email]))
            ->assertSessionHasErrors('code');

        $this->from(route('password.code.form', ['email' => $user->email]))
            ->post(route('password.code.verify'), ['email' => $user->email, 'code' => $validCode])
            ->assertRedirect(route('password.reset.form'))
            ->assertSessionHas('password_reset_verified_email', $user->email);

        $this->get(route('password.reset.form'))->assertOk()->assertSee('Atur Password Baru');
    }

    public function test_code_expires_after_ten_minutes(): void
    {
        $this->fakeRawMailer();
        $this->travelTo(Carbon::parse('2026-08-01 08:00:00'));
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);
        $code = $this->codeFromMail($this->sentRawMessages[0]['body']);

        $this->travelTo(now()->addMinutes(10));
        $this->from(route('password.code.form', ['email' => $user->email]))
            ->post(route('password.code.verify'), ['email' => $user->email, 'code' => $code])
            ->assertRedirect(route('password.code.form', ['email' => $user->email]))
            ->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->travelBack();
    }

    public function test_code_resend_is_limited_to_once_per_minute_per_email(): void
    {
        $this->fakeRawMailer();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();
        $firstTokenHash = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.code.form', ['email' => $user->email]))
            ->assertSessionHas('status', 'Jika email terdaftar, kode verifikasi sudah dikirim. Tunggu 1 menit sebelum meminta kode baru.');
        $this->assertCount(1, $this->sentRawMessages);
        $this->assertSame($firstTokenHash, DB::table('password_reset_tokens')->where('email', $user->email)->value('token'));

        $this->travel(61)->seconds();
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();
        $this->assertCount(2, $this->sentRawMessages);
        $this->assertNotSame($firstTokenHash, DB::table('password_reset_tokens')->where('email', $user->email)->value('token'));
        $this->travelBack();
    }

    public function test_password_confirmation_is_required_and_success_preserves_role_and_consumes_code(): void
    {
        $this->fakeRawMailer();
        $user = User::factory()->create(['role' => 'pic', 'password' => 'password-lama']);
        $this->post(route('password.email'), ['email' => $user->email]);
        $code = $this->codeFromMail($this->sentRawMessages[0]['body']);
        $this->post(route('password.code.verify'), ['email' => $user->email, 'code' => $code])
            ->assertRedirect(route('password.reset.form'));

        $this->post(route('password.update'), [
            'password' => 'password-baru',
            'password_confirmation' => 'tidak-sama',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password-lama', $user->fresh()->password));

        $this->post(route('password.update'), [
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ])->assertRedirect(route('login'))
          ->assertSessionHas('status', 'Password berhasil diubah. Silakan masuk dengan password baru.');

        $user->refresh();
        $this->assertSame('pic', $user->role);
        $this->assertTrue(Hash::check('password-baru', $user->password));
        $this->assertFalse(Hash::check('password-lama', $user->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->post(route('password.update'), [
            'password' => 'password-lain',
            'password_confirmation' => 'password-lain',
        ])->assertRedirect(route('password.request'))
          ->assertSessionHasErrors('email');
    }

    public function test_reset_requires_a_fresh_valid_verification_session(): void
    {
        $this->fakeRawMailer();
        $this->post(route('password.update'), [
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ])->assertRedirect(route('password.request'))
          ->assertSessionHasErrors('email');

        $user = User::factory()->create(['password' => 'password-awal']);
        $this->post(route('password.email'), ['email' => $user->email]);
        $code = $this->codeFromMail($this->sentRawMessages[0]['body']);
        $this->post(route('password.code.verify'), ['email' => $user->email, 'code' => $code])
            ->assertRedirect(route('password.reset.form'));

        $this->travel(10)->minutes();
        $this->get(route('password.reset.form'))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password-awal', $user->fresh()->password));
        $this->travelBack();
    }

    public function test_unconfigured_smtp_reports_configuration_error_without_sending_mail(): void
    {
        Mail::shouldReceive('raw')->never();
        config(['mail.mailers.smtp.password' => null]);
        $user = User::factory()->create();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors([
                'email' => 'Email belum dapat dikirim. Administrator perlu mengatur SMTP pada file .env.',
            ]);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    private function fakeRawMailer(): void
    {
        Mail::shouldReceive('raw')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $body, $callback): void {
                $recipient = null;
                $subject = null;
                $message = Mockery::mock();
                $message->shouldReceive('to')->once()->andReturnUsing(function ($address) use (&$recipient, &$message) {
                    $recipient = $address;
                    return $message;
                });
                $message->shouldReceive('subject')->once()->andReturnUsing(function ($value) use (&$subject, &$message) {
                    $subject = $value;
                    return $message;
                });

                $callback($message);
                $this->sentRawMessages[] = [
                    'to' => $recipient,
                    'subject' => $subject,
                    'body' => $body,
                ];
            });
    }

    private function codeFromMail(string $body): string
    {
        $this->assertMatchesRegularExpression('/adalah: ([0-9]{6})/', $body);
        preg_match('/adalah: ([0-9]{6})/', $body, $matches);

        return $matches[1];
    }
}
