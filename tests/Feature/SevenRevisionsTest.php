<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SevenRevisionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $picA;
    private User $picB;
    private User $guru;
    private User $siswa;
    private Room $roomA;
    private Room $roomB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Sekolah']);
        $this->picA = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Aula',
            'phone' => '081111111111',
        ]);
        $this->picB = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Lab',
            'phone' => '082222222222',
        ]);
        $this->guru = User::factory()->create(['role' => 'guru', 'name' => 'Guru Organisasi']);
        $this->siswa = User::factory()->create(['role' => 'siswa', 'name' => 'Siswa Pengamat']);

        $this->roomA = Room::create([
            'room_name' => 'Aula Utama',
            'location' => 'Gedung A',
            'capacity' => 150,
            'pic_id' => $this->picA->id,
            'is_active' => true,
        ]);

        $this->roomB = Room::create([
            'room_name' => 'LAB 1',
            'location' => 'Gedung B',
            'capacity' => 40,
            'pic_id' => $this->picB->id,
            'is_active' => true,
        ]);
    }

    public function test_newest_created_agenda_appears_first_for_admin_pic_and_guru(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            $oldEvent = $this->createEventAt([
                'event_name' => 'Agenda Lama Tanggal Jauh',
                'date' => '2026-11-25',
                'end_date' => '2026-11-25',
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ], now()->subHours(5));

            $middleEvent = $this->createEventAt([
                'event_name' => 'Agenda Menengah',
                'date' => '2026-11-20',
                'end_date' => '2026-11-20',
                'room_id' => $this->roomA->id,
                'status' => 'Menunggu',
            ], now()->subHours(2));

            $groupUuid = (string) Str::uuid();
            $newestGroupRoomA = $this->createEventAt([
                'event_name' => 'Agenda Paling Baru Multi Ruang',
                'date' => '2026-11-12',
                'end_date' => '2026-11-12',
                'room_id' => $this->roomA->id,
                'group_id' => $groupUuid,
                'status' => 'Menunggu',
            ], now());

            $this->createEventAt([
                'event_name' => 'Agenda Paling Baru Multi Ruang',
                'date' => '2026-11-12',
                'end_date' => '2026-11-12',
                'room_id' => $this->roomB->id,
                'group_id' => $groupUuid,
                'status' => 'Menunggu',
            ], now());

            foreach (['agenda.index' => $this->admin, 'pic.agenda' => $this->picA, 'guru.agenda' => $this->guru] as $routeName => $user) {
                $response = $this->actingAs($user)->get(route($routeName));
                $response->assertOk();

                $items = collect($response->viewData('agenda')->items());
                $this->assertCount(3, $items);
                $this->assertSame($newestGroupRoomA->event_name, $items[0]->event_name);
                $this->assertSame($middleEvent->id, $items[1]->id);
                $this->assertSame($oldEvent->id, $items[2]->id);

                // Status & tanggal pelaksanaan asli tidak boleh termutasi demi pengurutan
                $this->assertSame('Menunggu', $items[0]->status);
                $this->assertSame('Disetujui', $items[2]->status);
                $this->assertSame('25-11-2026', $items[2]->formatted_date_range);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_agenda_tables_and_detail_view_display_session_start_end_without_duplication(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            $groupId = (string) Str::uuid();

            $primary = Event::create([
                'event_name' => 'Rapat Koordinasi Multi Ruang',
                'date' => '2026-11-15',
                'end_date' => '2026-11-15',
                'start_time' => '08:15:00',
                'end_time' => '10:30:00',
                'requester_id' => $this->guru->id,
                'room_id' => $this->roomA->id,
                'group_id' => $groupId,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
                'description' => 'Membahas jadwal ujian semester.',
            ]);

            Event::create([
                'event_name' => 'Rapat Koordinasi Multi Ruang',
                'date' => '2026-11-15',
                'end_date' => '2026-11-15',
                'start_time' => '08:15:00',
                'end_time' => '10:30:00',
                'requester_id' => $this->guru->id,
                'room_id' => $this->roomB->id,
                'group_id' => $groupId,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
                'description' => 'Membahas jadwal ujian semester.',
            ]);

            foreach (['agenda.index' => $this->admin, 'pic.agenda' => $this->picA, 'guru.agenda' => $this->guru] as $routeName => $user) {
                $response = $this->actingAs($user)->get(route($routeName));
                $response->assertOk();
                $response->assertSeeTextInOrder(['Waktu Sesi', 'Jam Mulai', 'Jam Selesai']);
                $response->assertSeeText('Sesi 2 - Sesi 4');
                $response->assertSeeText('08:15');
                $response->assertSeeText('10:30');

                // Multi-room hanya muncul 1 baris agenda (tidak menggandakan waktu sesi di tabel)
                $html = $response->getContent();
                $this->assertSame(1, substr_count($html, 'Sesi 2 - Sesi 4'));
            }

            $detailResponse = $this->actingAs($this->admin)->get(route('events.show', $primary));
            $detailResponse->assertOk();
            $detailResponse->assertSeeText('Rapat Koordinasi Multi Ruang');
            $detailResponse->assertSeeText('Sesi 2 - Sesi 4');
            $detailResponse->assertSeeText('08:15');
            $detailResponse->assertSeeText('10:30');
            $detailResponse->assertSeeText('Aula Utama');
            $detailResponse->assertSeeText('LAB 1');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_date_range_filter_works_and_validates_bounds_across_roles(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            Event::create([
                'event_name' => 'Agenda Awal Bulan',
                'date' => '2026-11-10',
                'end_date' => '2026-11-10',
                'start_time' => '07:30:00',
                'end_time' => '09:00:00',
                'requester_id' => $this->guru->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
            ]);

            Event::create([
                'event_name' => 'Agenda Rentang Target',
                'date' => '2026-11-14',
                'end_date' => '2026-11-16',
                'start_time' => '08:15:00',
                'end_time' => '10:30:00',
                'requester_id' => $this->guru->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
            ]);

            Event::create([
                'event_name' => 'Agenda Akhir Bulan',
                'date' => '2026-11-28',
                'end_date' => '2026-11-28',
                'start_time' => '09:00:00',
                'end_time' => '11:15:00',
                'requester_id' => $this->guru->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
            ]);

            foreach (['agenda.index' => $this->admin, 'pic.agenda' => $this->picA, 'guru.agenda' => $this->guru] as $routeName => $user) {
                $filtered = $this->actingAs($user)->get(route($routeName, [
                    'tanggal_mulai' => '2026-11-15',
                    'tanggal_selesai' => '2026-11-20',
                ]));

                $filtered->assertOk();
                $filtered->assertSeeText('Agenda Rentang Target');
                $filtered->assertDontSeeText('Agenda Awal Bulan');
                $filtered->assertDontSeeText('Agenda Akhir Bulan');

                $invalid = $this->actingAs($user)->get(route($routeName, [
                    'tanggal_mulai' => '2026-11-25',
                    'tanggal_selesai' => '2026-11-20',
                ]));

                $invalid->assertRedirect(route($routeName));
                $invalid->assertSessionHasErrors(['tanggal_selesai']);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_date_filter_and_search_persist_across_pagination(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            for ($i = 1; $i <= 12; $i++) {
                $this->createEventAt([
                    'event_name' => sprintf('Workshop Digital #%02d', $i),
                    'date' => '2026-11-15',
                    'end_date' => '2026-11-15',
                    'room_id' => $this->roomA->id,
                    'status' => 'Disetujui',
                ], now()->subMinutes(20 - $i));
            }

            $this->createEventAt([
                'event_name' => 'Workshop Luar Rentang',
                'date' => '2026-11-25',
                'end_date' => '2026-11-25',
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
            ], now());

            $query = [
                'tanggal_mulai' => '2026-11-12',
                'tanggal_selesai' => '2026-11-18',
                'search' => 'Workshop',
            ];

            $page1 = $this->actingAs($this->admin)->get(route('agenda.index', $query));
            $page1->assertOk();
            $page1->assertViewHas('agenda', fn ($paginator) => $paginator->total() === 12 && $paginator->count() === 10);
            $page1->assertSee('Workshop Digital #12');
            $page1->assertDontSee('Workshop Luar Rentang');
            $page1->assertSee('tanggal_mulai=2026-11-12', false);
            $page1->assertSee('tanggal_selesai=2026-11-18', false);
            $page1->assertSee('search=Workshop', false);

            $page2 = $this->actingAs($this->admin)->get(route('agenda.index', $query + ['page' => 2]));
            $page2->assertOk();
            $page2->assertViewHas('agenda', fn ($paginator) => $paginator->currentPage() === 2 && $paginator->count() === 2);
            $page2->assertSee('Workshop Digital #02');
            $page2->assertSee('Workshop Digital #01');
            $page2->assertDontSee('Workshop Luar Rentang');

            // PIC & Guru pagination juga mempertahankan parameter filter
            $picPage2 = $this->actingAs($this->picA)->get(route('pic.agenda', $query + ['page' => 2]));
            $picPage2->assertOk();
            $picPage2->assertViewHas('agenda', fn ($paginator) => $paginator->currentPage() === 2 && $paginator->count() === 2);

            $guruPage2 = $this->actingAs($this->guru)->get(route('guru.agenda', $query + ['page' => 2]));
            $guruPage2->assertOk();
            $guruPage2->assertViewHas('agenda', fn ($paginator) => $paginator->currentPage() === 2 && $paginator->count() === 2);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_room_change_updates_event_creates_internal_notification_without_whatsapp_calls(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            Http::preventStrayRequests();
            Http::fake();

            $event = Event::create([
                'event_name' => 'Rapat OSIS',
                'date' => '2026-11-12',
                'end_date' => '2026-11-12',
                'start_time' => '08:15:00',
                'end_time' => '09:45:00',
                'requester_id' => $this->guru->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            // Halaman detail agenda & form pindah ruangan tidak menampilkan komponen WhatsApp otomatis
            $this->actingAs($this->admin)->get(route('events.show', $event))
                ->assertOk()
                ->assertDontSeeText('Riwayat Status Notifikasi WhatsApp Pemindahan Ruangan');

            $this->actingAs($this->admin)->get(route('events.move.form', $event))
                ->assertOk()
                ->assertDontSeeText('Status Notifikasi WhatsApp Pemilik Agenda');

            // Sidebar guru tidak lagi menampilkan menu Notifikasi WhatsApp
            $this->actingAs($this->guru)->get(route('dashboard.guru'))
                ->assertOk()
                ->assertDontSeeText('Notifikasi WhatsApp');

            // 1. Pindah ruangan via web berhasil dengan pesan sukses normal dan membuat ScheduleNotification
            $moveResponse = $this->actingAs($this->admin)->put(route('events.move', $event), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomB->id,
                'move_reason' => 'Aula dipakai upacara dadakan',
            ]);

            $moveResponse->assertRedirect(route('agenda.index'));
            $moveResponse->assertSessionHas('success', 'Ruangan agenda berhasil dipindahkan.');
            $moveResponse->assertSessionMissing('warning');

            $this->assertDatabaseHas('events', [
                'id' => $event->id,
                'room_id' => $this->roomB->id,
                'move_reason' => 'Aula dipakai upacara dadakan',
            ]);
            $this->assertDatabaseHas('schedule_notifications', [
                'event_id' => $event->id,
                'user_id' => $this->guru->id,
                'title' => 'Ruangan agenda dipindahkan',
            ]);

            Http::assertNothingSent();

            // 2. Edit agenda tanpa mengubah ruangan berhasil tanpa mengirim request eksternal apa pun
            $noRoomChangeResponse = $this->actingAs($this->admin)->put(route('agenda.update', $event), [
                'judul' => 'Rapat OSIS Revisi Judul',
                'tanggal' => '2026-11-12',
                'tanggal_selesai' => '2026-11-12',
                'sesi_mulai' => 2,
                'sesi_selesai' => 3,
                'current_room_id' => $this->roomB->id,
                'ruangan_id' => $this->roomB->id,
                'keterangan' => 'Update keterangan saja',
            ]);

            $noRoomChangeResponse->assertRedirect(route('agenda.index'));
            $noRoomChangeResponse->assertSessionHas('success', 'Agenda berhasil diperbarui.');
            $noRoomChangeResponse->assertSessionMissing('warning');
            Http::assertNothingSent();

            // 3. Pindah ruangan oleh Admin melalui API berhasil normal tanpa payload whatsapp_notification
            $apiMove = $this->actingAsApiUser($this->admin)
                ->putJson("/api/agenda/{$event->id}/pindah-ruangan", [
                    'current_room_id' => $this->roomB->id,
                    'ruangan_id' => $this->roomA->id,
                    'alasan' => 'Kembali ke Aula',
                ]);

            $apiMove->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('message', 'Ruangan agenda berhasil dipindahkan.')
                ->assertJsonMissingPath('whatsapp_notification');

            $this->assertDatabaseHas('events', [
                'id' => $event->id,
                'room_id' => $this->roomA->id,
                'move_reason' => 'Kembali ke Aula',
            ]);

            Http::assertNothingSent();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_room_move_edit_and_stale_state_checks_work_cleanly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            Http::preventStrayRequests();
            Http::fake();

            $event = Event::create([
                'event_name' => 'Simulasi UN',
                'date' => '2026-11-16',
                'end_date' => '2026-11-16',
                'start_time' => '07:30:00',
                'end_time' => '09:45:00',
                'requester_id' => $this->guru->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            // Pemindahan ruangan melalui form edit agenda (agenda.update) juga membuat ScheduleNotification
            $updateMoveResponse = $this->actingAs($this->admin)->put(route('agenda.update', $event), [
                'judul' => 'Simulasi UN',
                'tanggal' => '2026-11-16',
                'tanggal_selesai' => '2026-11-16',
                'sesi_mulai' => 1,
                'sesi_selesai' => 3,
                'current_room_id' => $this->roomA->id,
                'ruangan_id' => $this->roomB->id,
                'move_reason' => 'Pemeliharaan AC Aula',
                'keterangan' => 'Agenda simulasi ujian',
            ]);

            $updateMoveResponse->assertRedirect(route('agenda.index'));
            $updateMoveResponse->assertSessionHas('success', 'Agenda berhasil diperbarui dan notifikasi internal telah dibuat.');
            $updateMoveResponse->assertSessionMissing('warning');

            $this->assertDatabaseHas('events', [
                'id' => $event->id,
                'room_id' => $this->roomB->id,
                'move_reason' => 'Pemeliharaan AC Aula',
            ]);
            $this->assertDatabaseHas('schedule_notifications', [
                'event_id' => $event->id,
                'user_id' => $this->guru->id,
                'title' => 'Ruangan agenda dipindahkan',
            ]);

            // Stale current_room_id ditolak dengan aman pada Web maupun API
            $staleWebMove = $this->actingAs($this->admin)
                ->from(route('events.move.form', $event))
                ->put(route('events.move', $event), [
                    'current_room_id' => $this->roomA->id, // sudah kadaluarsa karena sekarang di roomB
                    'room_id' => $this->roomA->id,
                    'move_reason' => 'Permintaan usang',
                ]);

            $staleWebMove->assertRedirect(route('events.move.form', $event));
            $staleWebMove->assertSessionHasErrors('room_id');

            $staleApiMove = $this->actingAsApiUser($this->admin)
                ->putJson("/api/agenda/{$event->id}/pindah-ruangan", [
                    'current_room_id' => $this->roomA->id, // sudah kadaluarsa
                    'ruangan_id' => $this->roomA->id,
                    'alasan' => 'Permintaan usang API',
                ]);

            $staleApiMove->assertStatus(409)
                ->assertJsonPath('success', false);

            // Pindah ruangan API dengan current_room_id terbaru berhasil
            $freshApiMove = $this->actingAsApiUser($this->admin)
                ->putJson("/api/agenda/{$event->id}/pindah-ruangan", [
                    'current_room_id' => $this->roomB->id,
                    'ruangan_id' => $this->roomA->id,
                    'alasan' => 'Perbaikan Aula selesai',
                ]);

            $freshApiMove->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('message', 'Ruangan agenda berhasil dipindahkan.');

            $this->assertDatabaseHas('events', [
                'id' => $event->id,
                'room_id' => $this->roomA->id,
                'move_reason' => 'Perbaikan Aula selesai',
            ]);

            Http::assertNothingSent();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_repaired_complaint_can_be_reopened_and_updated_by_pic(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 10:00:00'));

        try {
            $complaint = Complaint::create([
                'room_id' => $this->roomA->id,
                'reporter_id' => $this->guru->id,
                'description' => 'AC Aula bocor dan menetes.',
                'status_repair' => 'Sudah Diperbaiki',
                'repair_note' => 'Catatan awal: saluran dibersihkan.',
                'repaired_at' => now(),
                'handled_by' => $this->picA->id,
                'reported_at' => now()->subHours(2),
            ]);

            $editForm = $this->actingAs($this->picA)->get(route('pic.complaints.edit', $complaint));
            $editForm->assertOk();
            $editForm->assertSee('Catatan awal: saluran dibersihkan.');
            $editForm->assertSee('Sudah Diperbaiki');

            $updateResponse = $this->actingAs($this->picA)->put(route('pic.complaints.update', $complaint), [
                'status_repair' => 'Sudah Diperbaiki',
                'repair_note' => 'Keterangan diperbarui: selang pembuangan AC sudah diganti baru.',
            ]);

            $updateResponse->assertRedirect(route('pic.complaints'));
            $updateResponse->assertSessionHas('success');

            // Tidak boleh membuat pengaduan baru
            $this->assertDatabaseCount('complaints', 1);
            $this->assertDatabaseHas('complaints', [
                'id' => $complaint->id,
                'status_repair' => 'Sudah Diperbaiki',
                'repair_note' => 'Keterangan diperbarui: selang pembuangan AC sudah diganti baru.',
                'handled_by' => $this->picA->id,
            ]);

            // Tampil pada daftar PIC, laporan PIC, dan halaman pelapor
            $this->actingAs($this->picA)->get(route('pic.complaints'))
                ->assertOk()
                ->assertSeeText('Keterangan diperbarui: selang pembuangan AC sudah diganti baru.');

            $this->actingAs($this->picA)->get(route('pic.complaints.report'))
                ->assertOk()
                ->assertSeeText('Keterangan diperbarui: selang pembuangan AC sudah diganti baru.');

            $this->actingAs($this->guru)->get(route('complaints.index'))
                ->assertOk()
                ->assertSeeText('Keterangan diperbarui: selang pembuangan AC sudah diganti baru.');

            // Pengaduan selesai >24 jam yang masih muncul di laporan PIC tetap bisa diedit dari laporan & ubah status
            $complaint->update([
                'repaired_at' => now()->subDays(3),
            ]);

            $reportEdit = $this->actingAs($this->picA)->get(route('pic.complaints.edit', [
                'complaint' => $complaint->id,
                'from' => 'report',
            ]));
            $reportEdit->assertOk();
            $reportEdit->assertSee('Keterangan diperbarui: selang pembuangan AC sudah diganti baru.');

            $updateFromReport = $this->actingAs($this->picA)->put(route('pic.complaints.update', $complaint), [
                'redirect_to' => 'report',
                'status_repair' => 'Menunggu Spare Part',
                'repair_note' => 'Perlu pengecekan ulang kompresor AC.',
            ]);

            $updateFromReport->assertRedirect(route('pic.complaints.report'));
            $this->assertDatabaseCount('complaints', 1);
            $this->assertDatabaseHas('complaints', [
                'id' => $complaint->id,
                'status_repair' => 'Menunggu Spare Part',
                'repair_note' => 'Perlu pengecekan ulang kompresor AC.',
                'repaired_at' => null,
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_non_pic_and_other_room_pic_cannot_edit_complaints(): void
    {
        $complaint = Complaint::create([
            'room_id' => $this->roomA->id,
            'reporter_id' => $this->guru->id,
            'description' => 'Lampu panggung redup.',
            'status_repair' => 'Sudah Diperbaiki',
            'repair_note' => 'Lampu diganti.',
            'repaired_at' => now(),
            'handled_by' => $this->picA->id,
            'reported_at' => now(),
        ]);

        foreach ([$this->admin, $this->guru, $this->siswa, $this->picB] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->get(route('pic.complaints.edit', $complaint))
                ->assertForbidden();

            $this->actingAs($unauthorizedUser)
                ->put(route('pic.complaints.update', $complaint), [
                    'status_repair' => 'Belum Diperbaiki',
                    'repair_note' => 'Coba ubah paksa',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status_repair' => 'Sudah Diperbaiki',
            'repair_note' => 'Lampu diganti.',
        ]);

        $this->actingAs($this->guru)->get(route('complaints.index'))
            ->assertOk()
            ->assertDontSeeText('Edit Perbaikan');
    }

    public function test_room_with_pic_cannot_be_deleted(): void
    {
        $response = $this->actingAs($this->admin)->delete(route('rooms.destroy', $this->roomA));

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionHas(
            'error',
            'Ruangan tidak dapat dihapus karena masih memiliki PIC yang bertanggung jawab. Silakan ubah atau lepaskan penanggung jawab ruangan terlebih dahulu.'
        );

        $this->assertDatabaseHas('rooms', [
            'id' => $this->roomA->id,
            'pic_id' => $this->picA->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $this->picA->id,
            'role' => 'pic',
        ]);
    }

    public function test_room_without_pic_follows_deletion_rules(): void
    {
        // 1. Admin melepaskan PIC terlebih dahulu -> ruangan tanpa keterkaitan data berhasil dihapus
        $unassignResponse = $this->actingAs($this->admin)->put(route('rooms.update', $this->roomB), [
            'room_name' => $this->roomB->room_name,
            'location' => $this->roomB->location,
            'capacity' => $this->roomB->capacity,
            'pic_id' => '',
            'description' => 'PIC dilepaskan sebelum penghapusan',
        ]);

        $unassignResponse->assertRedirect(route('rooms.index'));
        $this->assertDatabaseHas('rooms', [
            'id' => $this->roomB->id,
            'pic_id' => null,
        ]);

        $deleteClean = $this->actingAs($this->admin)->delete(route('rooms.destroy', $this->roomB));
        $deleteClean->assertRedirect(route('rooms.index'));
        $deleteClean->assertSessionHas('success', 'Ruangan berhasil dihapus.');
        $this->assertDatabaseMissing('rooms', ['id' => $this->roomB->id]);

        // 2. Ruangan tanpa PIC yang masih memiliki riwayat agenda tidak dapat dihapus sembarangan
        $roomWithHistory = Room::create([
            'room_name' => 'Ruang Sejarah',
            'location' => 'Gedung C',
            'capacity' => 30,
            'pic_id' => null,
            'is_active' => true,
        ]);

        Event::create([
            'event_name' => 'Agenda Arsip',
            'date' => '2026-11-20',
            'end_date' => '2026-11-20',
            'start_time' => '07:30:00',
            'end_time' => '09:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $roomWithHistory->id,
            'status' => 'Disetujui',
        ]);

        $deleteBlocked = $this->actingAs($this->admin)->delete(route('rooms.destroy', $roomWithHistory));
        $deleteBlocked->assertRedirect(route('rooms.index'));
        $deleteBlocked->assertSessionHas(
            'error',
            'Ruangan tidak dapat dihapus karena masih memiliki riwayat agenda atau pengaduan yang terhubung.'
        );
        $this->assertDatabaseHas('rooms', ['id' => $roomWithHistory->id]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createEventAt(array $attributes, Carbon $timestamp): Event
    {
        $event = new Event(array_merge([
            'event_name' => 'Agenda Uji',
            'date' => '2026-11-15',
            'end_date' => '2026-11-15',
            'start_time' => '07:30:00',
            'end_time' => '09:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->roomA->id,
            'status' => 'Menunggu',
        ], $attributes));

        $event->created_at = $timestamp;
        $event->updated_at = $timestamp;
        $event->save();

        return $event;
    }

    private function actingAsApiUser(User $user): static
    {
        $token = 'test_' . bin2hex(random_bytes(20));
        Cache::put('api_token_' . hash('sha256', $token), $user->id, now()->addHour());

        return $this->withToken($token);
    }
}
