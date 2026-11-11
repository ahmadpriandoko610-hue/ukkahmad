<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebLoginRoleRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_siswa_cannot_stay_authenticated_on_web_login(): void
    {
        $siswa = User::factory()->create([
            'role' => 'siswa',
            'email' => 'siswa.uji@sekolah.sch.id',
            'password' => Hash::make('password123'),
        ]);

        // Buka halaman login untuk mendapatkan session & CSRF awal
        $this->get(route('login'))->assertOk();
        $initialSessionId = session()->getId();
        $initialCsrfToken = session()->token();

        $response = $this->post(route('login.proses'), [
            'email' => $siswa->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Akun Siswa tidak memiliki akses dashboard web. Silakan gunakan aplikasi mobile RAPI-Sekolah atau lihat kalender publik tanpa login.');

        // Akun siswa TIDAK boleh tertinggal dalam status terautentikasi & session wajib dirotasi
        $this->assertGuest();
        $this->assertNotSame($initialSessionId, session()->getId());
        $this->assertNotSame($initialCsrfToken, session()->token());

        // Halaman login menampilkan pesan penolakan yang tepat dan rute terproteksi tetap menendang ke /login
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Akun Siswa tidak memiliki akses dashboard web.');
        $this->get(route('dashboard.guru'))->assertRedirect(route('login'));
        $this->get(route('dashboard.pic'))->assertRedirect(route('login'));
        $this->get(route('dashboard.admin'))->assertRedirect(route('login'));
    }

    public function test_siswa_with_wrong_password_receives_generic_invalid_credentials_message(): void
    {
        $siswa = User::factory()->create([
            'role' => 'siswa',
            'email' => 'siswa.salah@sekolah.sch.id',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->from(route('login'))->post(route('login.proses'), [
            'email' => $siswa->email,
            'password' => 'salah-banget',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Email atau Password salah!');
        $this->assertGuest();
    }

    public function test_admin_pic_and_guru_can_still_log_in_on_web_normally(): void
    {
        $cases = [
            'admin' => 'dashboard.admin',
            'pic' => 'dashboard.pic',
            'guru' => 'dashboard.guru',
        ];

        foreach ($cases as $role => $expectedRoute) {
            $user = User::factory()->create([
                'role' => $role,
                'email' => "{$role}.web@sekolah.sch.id",
                'password' => Hash::make('password123'),
            ]);

            $response = $this->post(route('login.proses'), [
                'email' => $user->email,
                'password' => 'password123',
            ]);

            $response->assertRedirect(route($expectedRoute));
            $this->assertAuthenticatedAs($user);

            $this->post(route('logout'))->assertRedirect('/login');
            $this->assertGuest();
        }
    }
}

