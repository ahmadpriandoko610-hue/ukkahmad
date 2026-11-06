<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarActiveNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pic;
    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Utama',
        ]);

        $this->pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Sarpras',
        ]);

        $this->guru = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru Pengajar',
        ]);
    }

    public function test_admin_sidebar_highlights_data_ruangan_when_on_rooms_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('rooms.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'href="' . route('rooms.index') . '"',
            'class="menu-item active"',
            'Data Ruangan',
        ], false);
    }

    public function test_admin_sidebar_active_states_across_all_routes(): void
    {
        // 1. Dashboard
        $res = $this->actingAs($this->admin)->get(route('dashboard.admin'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('dashboard.admin') . '"', 'class="menu-item active"', 'Dashboard'], false);

        // 2. Data User
        $res = $this->actingAs($this->admin)->get(route('users.index'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('users.index') . '"', 'class="menu-item active"', 'Data User'], false);

        // 3. Data Agenda
        $res = $this->actingAs($this->admin)->get(route('events.index'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('events.index') . '"', 'class="menu-item active"', 'Data Agenda'], false);

        // 4. Laporan Agenda
        $res = $this->actingAs($this->admin)->get(route('admin.agenda.report'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('admin.agenda.report') . '"', 'class="menu-item active"', 'Laporan Agenda'], false);

        // 5. Pengaduan Kerusakan
        $res = $this->actingAs($this->admin)->get(route('admin.complaints'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('admin.complaints') . '"', 'class="menu-item active"', 'Pengaduan Kerusakan'], false);

        // 6. Kalender
        $res = $this->actingAs($this->admin)->get(route('admin.calendar'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('admin.calendar') . '"', 'class="menu-item active"', 'Kalender'], false);

        // 7. Jadwal Ruangan
        $res = $this->actingAs($this->admin)->get(route('jadwal.index'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('jadwal.index') . '"', 'class="menu-item active"', 'Jadwal Ruangan'], false);
    }

    public function test_pic_sidebar_active_states_across_all_routes(): void
    {
        // 1. Dashboard
        $res = $this->actingAs($this->pic)->get(route('dashboard.pic'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('dashboard.pic') . '"', 'class="menu-item active"', 'Dashboard'], false);

        // 2. Verifikasi Agenda
        $res = $this->actingAs($this->pic)->get(route('pic.events'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('pic.events') . '"', 'class="menu-item active"', 'Verifikasi Agenda'], false);

        // 3. Kalender Agenda
        $res = $this->actingAs($this->pic)->get(route('pic.calendar'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('pic.calendar') . '"', 'class="menu-item active"', 'Kalender Agenda'], false);

        // 4. Pengaduan Kerusakan
        $res = $this->actingAs($this->pic)->get(route('pic.complaints'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('pic.complaints') . '"', 'class="menu-item active"', 'Pengaduan Kerusakan'], false);

        // 5. Laporan Kerusakan
        $res = $this->actingAs($this->pic)->get(route('pic.complaints.report'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('pic.complaints.report') . '"', 'class="menu-item active"', 'Laporan Kerusakan'], false);

        // 6. Jadwal Ruangan
        $res = $this->actingAs($this->pic)->get(route('pic.schedule'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('pic.schedule') . '"', 'class="menu-item active"', 'Jadwal Ruangan'], false);
    }

    public function test_guru_sidebar_active_states_across_all_routes(): void
    {
        // 1. Dashboard
        $res = $this->actingAs($this->guru)->get(route('dashboard.guru'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('dashboard.guru') . '"', 'class="menu-item active"', 'Dashboard'], false);

        // 2. Agenda Saya
        $res = $this->actingAs($this->guru)->get(route('guru.agenda'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('guru.agenda') . '"', 'class="menu-item active"', 'Agenda Saya'], false);

        // 3. Ajukan Agenda
        $res = $this->actingAs($this->guru)->get(route('guru.events.create'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('guru.events.create') . '"', 'class="menu-item active"', 'Ajukan Agenda'], false);

        // 4. Kalender Agenda
        $res = $this->actingAs($this->guru)->get(route('guru.calendar'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('guru.calendar') . '"', 'class="menu-item active"', 'Kalender Agenda'], false);

        // 5. Jadwal
        $res = $this->actingAs($this->guru)->get(route('jadwal.index'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('jadwal.index') . '"', 'class="menu-item active"', 'Jadwal'], false);

        // 6. Pengaduan Kerusakan
        $res = $this->actingAs($this->guru)->get(route('complaints.index'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('complaints.index') . '"', 'class="menu-item active"', 'Pengaduan Kerusakan'], false);

        // 7. Buat Pengaduan
        $res = $this->actingAs($this->guru)->get(route('complaints.create'));
        $res->assertOk();
        $res->assertSeeInOrder(['href="' . route('complaints.create') . '"', 'class="menu-item active"', 'Buat Pengaduan'], false);
    }
}

