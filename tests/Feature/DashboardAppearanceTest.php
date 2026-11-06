<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_successfully(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Pak Administrator',
        ]);

        $expectedDate = \Carbon\Carbon::now()->locale('id')->translatedFormat('l, d F Y');

        $response = $this->actingAs($admin)->get('/dashboard/admin');

        $response->assertOk();
        $response->assertSee('Selamat Datang, Pak Administrator');
        $response->assertSee($expectedDate);
        $response->assertSee('Total Pengguna');
        $response->assertSee('Ruangan Terdaftar');
        $response->assertDontSee('Ruang Kosong');
        $response->assertSee('Agenda Aktif');
        $response->assertSee('Pengaduan Kerusakan');
        $response->assertDontSee('dash-stat-icon', false);
        $response->assertDontSee('dash-card-title-icon', false);
        $response->assertDontSee('dash-list-icon', false);
        $response->assertDontSee('dash-empty-icon', false);
    }

    public function test_guru_dashboard_renders_successfully(): void
    {
        $guru = User::factory()->create([
            'role' => 'guru',
            'name' => 'Ibu Guru Ani',
        ]);

        $expectedDate = \Carbon\Carbon::now()->locale('id')->translatedFormat('l, d F Y');

        $response = $this->actingAs($guru)->get('/dashboard/guru');

        $response->assertOk();
        $response->assertSee('Selamat Datang, Ibu Guru Ani');
        $response->assertSee($expectedDate);
        $response->assertDontSee('Portal Guru');
        $response->assertDontSee('Aksi Cepat Guru & Organisasi');
        $response->assertSee('Agenda Saya');
        $response->assertSee('Disetujui PIC');
        $response->assertSee('Alur & Tata Tertib Peminjaman Ruangan', false);
        $response->assertDontSee('dash-stat-icon', false);
        $response->assertDontSee('dash-card-title-icon', false);
        $response->assertDontSee('dash-empty-icon', false);
    }

    public function test_pic_dashboard_renders_successfully(): void
    {
        $pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'Bapak PIC Sarpras',
        ]);

        $expectedDate = \Carbon\Carbon::now()->locale('id')->translatedFormat('l, d F Y');

        $response = $this->actingAs($pic)->get('/dashboard/pic');

        $response->assertOk();
        $response->assertSee('Selamat Datang, Bapak PIC Sarpras');
        $response->assertSee($expectedDate);
        $response->assertDontSee('Panel PIC Ruang');
        $response->assertDontSee('Aksi Cepat PIC Ruangan');
        $response->assertDontSee('Agenda Menunggu Verifikasi');
        $response->assertDontSee('Pengaduan Kerusakan di Ruangan Anda');
        $response->assertSee('Agenda Menunggu');
        $response->assertSee('Agenda Disetujui');
        $response->assertSee('Ruangan Tanggung Jawab Saya');
        $response->assertDontSee('dash-stat-icon', false);
        $response->assertDontSee('dash-card-title-icon', false);
        $response->assertDontSee('dash-empty-icon', false);
    }
}
