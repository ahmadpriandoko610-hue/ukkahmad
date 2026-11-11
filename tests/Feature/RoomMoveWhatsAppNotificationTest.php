<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use App\Models\ScheduleNotification;
use App\Models\User;
use App\Services\RoomMoveWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoomMoveWhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guruOwner;
    private User $otherGuru;
    private User $picA;
    private User $picB;
    private User $siswa;
    private Room $roomA;
    private Room $roomB;
    private Room $roomC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin RAPI',
            'email' => 'admin.rapi@rapi.test',
        ]);

        $this->guruOwner = User::factory()->create([
            'role' => 'guru',
            'name' => 'Budi Hartono',
            'email' => 'budi.guru@rapi.test',
            'phone' => '081234567890',
        ]);

        $this->otherGuru = User::factory()->create([
            'role' => 'guru',
            'name' => 'Siti Lainnya',
            'email' => 'siti.guru@rapi.test',
            'phone' => '081299990000',
        ]);

        $this->picA = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Aula',
            'email' => 'pic.a@rapi.test',
            'phone' => '081111111111',
        ]);

        $this->picB = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Lab',
            'email' => 'pic.b@rapi.test',
            'phone' => '082222222222',
        ]);

        $this->siswa = User::factory()->create([
            'role' => 'siswa',
            'name' => 'Siswa Pengamat',
            'email' => 'siswa.wa@rapi.test',
        ]);

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

        $this->roomC = Room::create([
            'room_name' => 'LAB 2',
            'location' => 'Gedung C',
            'capacity' => 40,
            'pic_id' => $this->picB->id,
            'is_active' => true,
        ]);
    }

    public function test_room_move_generates_accurate_wa_me_link_and_message_via_move_and_edit_flows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            Http::preventStrayRequests();
            Http::fake();

            $event = Event::create([
                'event_name' => 'Workshop Robotik Nasional',
                'date' => '2026-11-15',
                'end_date' => '2026-11-15',
                'start_time' => '08:15:00',
                'end_time' => '10:30:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            // 1. Pindah ruangan melalui form Pindah Ruangan (events.move)
            $moveResponse = $this->actingAs($this->admin)->put(route('events.move', $event), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomB->id,
                'move_reason' => 'Perawatan sistem suara Aula Utama',
            ]);

            $moveResponse->assertRedirect(route('agenda.index'));
            $moveResponse->assertSessionHas('success', 'Ruangan agenda berhasil dipindahkan.');

            // Pastikan tombol muncul di Kelola Agenda & tidak membocorkan nomor HP mentah di HTML
            $indexResponse = $this->actingAs($this->admin)->get(route('agenda.index'));
            $indexResponse->assertOk();
            $indexResponse->assertSeeText('Kirim Pemberitahuan WhatsApp kepada Guru');
            $indexResponse->assertSeeText('Workshop Robotik Nasional');
            $indexResponse->assertSeeText('Aula Utama');
            $indexResponse->assertSeeText('LAB 1');
            $indexResponse->assertSeeText('Budi Hartono');
            $indexResponse->assertSee(route('events.move.whatsapp', $event->id));
            $indexResponse->assertDontSeeText('WhatsApp akan terbuka dengan pesan yang sudah terisi.');
            $indexResponse->assertDontSeeText('Periksa kembali lalu tekan Kirim secara manual.');
            $indexResponse->assertDontSee('081234567890');
            $indexResponse->assertDontSee('6281234567890');

            // Klik tombol membuka wa.me sesuai nomor Guru pemilik agenda & isi pesan aktual database
            $waResponse = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $event));
            $waResponse->assertRedirect();

            $targetUrl = (string) $waResponse->headers->get('Location');
            $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $targetUrl);

            $encodedQuery = substr($targetUrl, strlen('https://wa.me/6281234567890?text='));
            $decodedMessage = rawurldecode($encodedQuery);
            $expectedMessage =
                "Halo Bapak/Ibu Budi Hartono.\n\n" .
                "Kami informasikan bahwa ruangan agenda Anda telah mengalami perubahan.\n\n" .
                "Informasi Perubahan Ruangan:\n\n" .
                "Nama Agenda: Workshop Robotik Nasional\n" .
                "Ruangan Sebelumnya: Aula Utama\n" .
                "Ruangan Baru: LAB 1\n" .
                "Tanggal: 15-11-2026\n" .
                "Waktu: 08:15 - 10:30\n\n" .
                "Mohon memperhatikan perubahan ruangan tersebut.\n\n" .
                "Terima kasih.\n" .
                'Admin RAPI-Sekolah.';
            $this->assertSame($expectedMessage, $decodedMessage);
            $this->assertSame(rawurlencode($expectedMessage), $encodedQuery);

            // 2. Pindah ruangan melalui form Edit Agenda (agenda.update) juga menghasilkan pemberitahuan yang benar
            $editMoveResponse = $this->actingAs($this->admin)->put(route('agenda.update', $event), [
                'judul' => 'Workshop Robotik Nasional',
                'tanggal' => '2026-11-15',
                'tanggal_selesai' => '2026-11-15',
                'sesi_mulai' => 2,
                'sesi_selesai' => 4,
                'current_room_id' => $this->roomB->id,
                'ruangan_id' => $this->roomC->id,
                'move_reason' => 'Dipindah ke LAB 2 agar lebih luas',
                'keterangan' => 'Mohon membawa laptop masing-masing',
            ]);

            $editMoveResponse->assertRedirect(route('agenda.index'));
            $editMoveResponse->assertSessionHas('success', 'Agenda berhasil diperbarui dan notifikasi internal telah dibuat.');

            $waAfterEdit = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $event));
            $waAfterEdit->assertRedirect();
            $secondUrl = (string) $waAfterEdit->headers->get('Location');
            $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $secondUrl);

            $secondMessage = rawurldecode(substr($secondUrl, strlen('https://wa.me/6281234567890?text=')));
            $this->assertStringContainsString('Ruangan Sebelumnya: LAB 1', $secondMessage);
            $this->assertStringContainsString('Ruangan Baru: LAB 2', $secondMessage);
            $this->assertStringNotContainsString('Ruangan Sebelumnya: Aula Utama', $secondMessage);

            // Pastikan ScheduleNotification internal tetap dibuat dan TIDAK ada request HTTP otomatis ke API eksternal
            $this->assertSame(2, ScheduleNotification::where('event_id', $event->id)->count());
            Http::assertNothingSent();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_whatsapp_recipient_is_strictly_agenda_owner_and_never_pic_or_other_guru(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            // Agenda milik guruOwner dengan format nomor +62 813-4567-8901
            $this->guruOwner->update(['phone' => '+62 813-4567-8901']);

            $guruEvent = Event::create([
                'event_name' => 'Klinik Literasi',
                'date' => '2026-11-18',
                'end_date' => '2026-11-18',
                'start_time' => '09:00:00',
                'end_time' => '11:15:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $this->actingAs($this->admin)->put(route('events.move', $guruEvent), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomB->id,
                'move_reason' => 'Renovasi atap Aula',
            ])->assertRedirect(route('agenda.index'));

            $waResponse = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $guruEvent));
            $targetUrl = (string) $waResponse->headers->get('Location');

            // Harus mengarah strictly ke nomor guruOwner (6281345678901), bukan PIC Aula, PIC Lab, atau Guru lainnya
            $this->assertStringStartsWith('https://wa.me/6281345678901?text=', $targetUrl);
            $this->assertStringNotContainsString('6281111111111', $targetUrl);
            $this->assertStringNotContainsString('6282222222222', $targetUrl);
            $this->assertStringNotContainsString('6281299990000', $targetUrl);

            // Jika pemilik agenda kebetulan adalah user PIC itu sendiri, maka hanya pemilik agenda yang dituju (bukan PIC ruangan tujuan)
            $picOwnedEvent = Event::create([
                'event_name' => 'Inventarisasi Sarpras',
                'date' => '2026-11-19',
                'end_date' => '2026-11-19',
                'start_time' => '12:00:00',
                'end_time' => '13:30:00',
                'requester_id' => $this->picA->id,
                'room_id' => $this->roomB->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $this->actingAs($this->admin)->put(route('events.move', $picOwnedEvent), [
                'current_room_id' => $this->roomB->id,
                'room_id' => $this->roomC->id,
                'move_reason' => 'Penyesuaian jadwal praktikum',
            ])->assertRedirect(route('agenda.index'));

            $picWaResponse = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $picOwnedEvent));
            $picTargetUrl = (string) $picWaResponse->headers->get('Location');
            $this->assertStringStartsWith('https://wa.me/6281111111111?text=', $picTargetUrl);
            $this->assertStringNotContainsString('6282222222222', $picTargetUrl);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_whatsapp_notice_is_not_shown_or_built_when_room_does_not_change(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            $event = Event::create([
                'event_name' => 'Rapat Kurikulum',
                'date' => '2026-11-16',
                'end_date' => '2026-11-16',
                'start_time' => '07:30:00',
                'end_time' => '09:45:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            // Simulasikan pernah pindah ruangan terlebih dahulu
            $this->actingAs($this->admin)->put(route('events.move', $event), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomB->id,
                'move_reason' => 'Pemeliharaan AC',
            ])->assertRedirect(route('agenda.index'));

            $this->actingAs($this->admin)->get(route('agenda.index'))
                ->assertOk()
                ->assertSeeText('Kirim Pemberitahuan WhatsApp kepada Guru');

            // Sekarang Admin mengedit judul & sesi agenda TANPA mengganti ruangan
            $noRoomChange = $this->actingAs($this->admin)->put(route('agenda.update', $event), [
                'judul' => 'Rapat Kurikulum Semester Genap',
                'tanggal' => '2026-11-16',
                'tanggal_selesai' => '2026-11-16',
                'sesi_mulai' => 3,
                'sesi_selesai' => 5,
                'current_room_id' => $this->roomB->id,
                'ruangan_id' => $this->roomB->id,
                'keterangan' => 'Perubahan jam saja tanpa pindah ruang',
            ]);

            $noRoomChange->assertRedirect(route('agenda.index'));
            $noRoomChange->assertSessionHas('success', 'Agenda berhasil diperbarui.');

            // Tidak boleh ada tombol/panel WA pemindahan ruangan pada Kelola Agenda, Edit, Detail, maupun Pindah Ruangan
            foreach ([
                route('agenda.index'),
                route('events.show', $event),
                route('agenda.edit', $event),
                route('events.move.form', $event),
            ] as $url) {
                $this->actingAs($this->admin)->get($url)
                    ->assertOk()
                    ->assertDontSeeText('Kirim Pemberitahuan WhatsApp kepada Guru')
                    ->assertDontSeeText('Pemberitahuan WhatsApp Pemindahan Ruangan');
            }

            // Akses langsung ke endpoint WA untuk agenda ini juga harus ditolak
            $directPost = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $event));
            $directPost->assertRedirect(route('agenda.index'));
            $directPost->assertSessionHas('error');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_whatsapp_notice_and_link_are_not_created_when_save_fails(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            $event = Event::create([
                'event_name' => 'Seminar Pendidikan',
                'date' => '2026-11-20',
                'end_date' => '2026-11-20',
                'start_time' => '08:15:00',
                'end_time' => '10:30:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            // Agenda pemblokir di roomB agar terjadi bentrok
            Event::create([
                'event_name' => 'Ujian Praktik LAB 1',
                'date' => '2026-11-20',
                'end_date' => '2026-11-20',
                'start_time' => '09:00:00',
                'end_time' => '11:15:00',
                'requester_id' => $this->otherGuru->id,
                'room_id' => $this->roomB->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $inactiveRoom = Room::create([
                'room_name' => 'Ruang Gudang',
                'location' => 'Belakang',
                'capacity' => 20,
                'pic_id' => $this->picA->id,
                'is_active' => false,
            ]);

            // 1. Gagal karena bentrok jadwal di roomB
            $this->actingAs($this->admin)
                ->from(route('events.move.form', $event))
                ->put(route('events.move', $event), [
                    'current_room_id' => $this->roomA->id,
                    'room_id' => $this->roomB->id,
                    'move_reason' => 'Bentrok seharusnya gagal',
                ])
                ->assertRedirect(route('events.move.form', $event))
                ->assertSessionHasErrors('room_id');

            // 2. Gagal karena ruangan tidak aktif
            $this->actingAs($this->admin)
                ->from(route('events.move.form', $event))
                ->put(route('events.move', $event), [
                    'current_room_id' => $this->roomA->id,
                    'room_id' => $inactiveRoom->id,
                    'move_reason' => 'Ruangan nonaktif seharusnya gagal',
                ])
                ->assertRedirect(route('events.move.form', $event))
                ->assertSessionHasErrors('room_id');

            // 3. Gagal karena validasi move_reason kosong
            $this->actingAs($this->admin)
                ->from(route('events.move.form', $event))
                ->put(route('events.move', $event), [
                    'current_room_id' => $this->roomA->id,
                    'room_id' => $this->roomC->id,
                    'move_reason' => '',
                ])
                ->assertRedirect(route('events.move.form', $event))
                ->assertSessionHasErrors('move_reason');

            // 4. Gagal karena memilih ruangan yang sama pada form pindah ruangan
            $this->actingAs($this->admin)
                ->from(route('events.move.form', $event))
                ->put(route('events.move', $event), [
                    'current_room_id' => $this->roomA->id,
                    'room_id' => $this->roomA->id,
                    'move_reason' => 'Ruangan tidak berubah',
                ])
                ->assertRedirect(route('events.move.form', $event))
                ->assertSessionHasErrors('room_id');

            // 5. Gagal pada agenda yang belum disetujui (status Menunggu)
            $pendingEvent = Event::create([
                'event_name' => 'Agenda Menunggu Persetujuan',
                'date' => '2026-11-22',
                'end_date' => '2026-11-22',
                'start_time' => '08:15:00',
                'end_time' => '09:45:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->roomA->id,
                'status' => 'Menunggu',
            ]);

            $this->actingAs($this->admin)
                ->from(route('agenda.edit', $pendingEvent))
                ->put(route('agenda.update', $pendingEvent), [
                    'judul' => 'Agenda Menunggu Persetujuan',
                    'tanggal' => '2026-11-22',
                    'tanggal_selesai' => '2026-11-22',
                    'sesi_mulai' => 2,
                    'sesi_selesai' => 3,
                    'current_room_id' => $this->roomA->id,
                    'ruangan_id' => $this->roomC->id,
                ])
                ->assertRedirect(route('agenda.edit', $pendingEvent))
                ->assertSessionHasErrors('ruangan_id');

            $this->actingAs($this->admin)->get(route('agenda.index'))
                ->assertOk()
                ->assertDontSeeText('Kirim Pemberitahuan WhatsApp kepada Guru')
                ->assertDontSeeText('Pemberitahuan WhatsApp Pemindahan Ruangan');

            $this->actingAs($this->admin)->post(route('events.move.whatsapp', $event))
                ->assertRedirect(route('agenda.index'))
                ->assertSessionHas('error');

            $this->assertSame(0, ScheduleNotification::count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_empty_and_invalid_guru_phone_numbers_hide_button_and_display_clear_notice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            $guruNoPhone = User::factory()->create([
                'role' => 'guru',
                'name' => 'Guru Tanpa WA',
                'email' => 'guru.tanpawa@rapi.test',
                'phone' => null,
            ]);

            $event = Event::create([
                'event_name' => 'Sosialisasi PPDB',
                'date' => '2026-11-19',
                'end_date' => '2026-11-19',
                'start_time' => '08:15:00',
                'end_time' => '09:45:00',
                'requester_id' => $guruNoPhone->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            // A. Nomor kosong
            $this->actingAs($this->admin)->put(route('events.move', $event), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomB->id,
                'move_reason' => 'Dipindah ke LAB 1',
            ])->assertRedirect(route('agenda.index'));

            $indexNoPhone = $this->actingAs($this->admin)->get(route('agenda.index'));
            $indexNoPhone->assertOk();
            $indexNoPhone->assertSeeText('Pemberitahuan WhatsApp Pemindahan Ruangan');
            $indexNoPhone->assertSeeText('WhatsApp tidak dapat dibuka karena nomor WhatsApp Guru pemilik agenda belum tersedia atau tidak valid.');
            $indexNoPhone->assertDontSeeText('Kirim Pemberitahuan WhatsApp kepada Guru');
            $indexNoPhone->assertDontSee(route('events.move.whatsapp', $event->id));

            $postNoPhone = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $event));
            $postNoPhone->assertRedirect(route('agenda.index'));
            $postNoPhone->assertSessionHas('error', 'WhatsApp tidak dapat dibuka karena nomor WhatsApp Guru pemilik agenda belum tersedia atau tidak valid.');

            // B. Nomor tidak valid di database (mis. teks atau digit terlalu pendek)
            DB::table('users')->where('id', $guruNoPhone->id)->update(['phone' => 'tidak-valid-123']);

            $indexInvalidPhone = $this->actingAs($this->admin)->get(route('agenda.index'));
            $indexInvalidPhone->assertOk();
            $indexInvalidPhone->assertSeeText('WhatsApp tidak dapat dibuka karena nomor WhatsApp Guru pemilik agenda belum tersedia atau tidak valid.');
            $indexInvalidPhone->assertDontSeeText('Kirim Pemberitahuan WhatsApp kepada Guru');

            $postInvalidPhone = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $event));
            $postInvalidPhone->assertRedirect(route('agenda.index'));
            $postInvalidPhone->assertSessionHas('error');

            // C. Setelah Admin melengkapi nomor WA Guru melalui Kelola User dan memuat ulang halaman, tombol otomatis aktif
            $this->actingAs($this->admin)->put(route('users.update', $guruNoPhone), [
                'name' => $guruNoPhone->name,
                'email' => $guruNoPhone->email,
                'role' => 'guru',
                'phone' => '085611223344',
            ])->assertRedirect(route('users.index'));

            $indexRecovered = $this->actingAs($this->admin)->get(route('agenda.index'));
            $indexRecovered->assertOk();
            $indexRecovered->assertSeeText('Kirim Pemberitahuan WhatsApp kepada Guru');

            $waRecovered = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $event));
            $waRecovered->assertRedirect();
            $this->assertStringStartsWith('https://wa.me/6285611223344?text=', (string) $waRecovered->headers->get('Location'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_multiday_agenda_displays_start_and_end_date_range_in_ui_and_whatsapp_message(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            $multiDayEvent = Event::create([
                'event_name' => 'Pelatihan Sertifikasi Guru',
                'date' => '2026-11-20',
                'end_date' => '2026-11-22',
                'start_time' => '07:30:00',
                'end_time' => '15:00:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $this->actingAs($this->admin)->put(route('events.move', $multiDayEvent), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomC->id,
                'move_reason' => 'Kebutuhan komputer untuk praktik 3 hari',
            ])->assertRedirect(route('agenda.index'));

            $indexResponse = $this->actingAs($this->admin)->get(route('agenda.index'));
            $indexResponse->assertOk();
            $indexResponse->assertSeeText('20-11-2026 s/d 22-11-2026');
            $indexResponse->assertSeeText('07:30 - 15:00');

            $waResponse = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $multiDayEvent));
            $waResponse->assertRedirect();

            $targetUrl = (string) $waResponse->headers->get('Location');
            $decoded = rawurldecode(substr($targetUrl, strlen('https://wa.me/6281234567890?text=')));
            $this->assertStringContainsString('Tanggal: 20-11-2026 s/d 22-11-2026', $decoded);
            $this->assertStringContainsString('Waktu: 07:30 - 15:00', $decoded);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_message_persists_accurately_across_reloads_and_ignores_tampered_client_inputs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            $event = Event::create([
                'event_name' => 'Rapat Evaluasi Proyek',
                'date' => '2026-11-25',
                'end_date' => '2026-11-25',
                'start_time' => '09:45:00',
                'end_time' => '12:00:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $this->actingAs($this->admin)->put(route('events.move', $event), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomB->id,
                'move_reason' => 'Ketersediaan proyektor',
            ])->assertRedirect(route('agenda.index'));

            // Simulasi reload halaman berulang kali & navigasi ke detail/edit/move agenda tersebut
            for ($i = 0; $i < 3; $i++) {
                $reload = $this->actingAs($this->admin)->get(route('agenda.index'));
                $reload->assertOk()
                    ->assertSeeText('Kirim Pemberitahuan WhatsApp kepada Guru')
                    ->assertSeeText('Rapat Evaluasi Proyek')
                    ->assertSeeText('Aula Utama')
                    ->assertSeeText('LAB 1');
            }

            foreach ([
                route('events.show', $event),
                route('agenda.edit', $event),
                route('events.move.form', $event),
            ] as $detailOrFormUrl) {
                $this->actingAs($this->admin)->get($detailOrFormUrl)
                    ->assertOk()
                    ->assertSeeText('Kirim Pemberitahuan WhatsApp kepada Guru');
            }

            // Manipulasi input client pada POST WhatsApp tidak boleh mengubah penerima maupun isi pesan
            $tamperedPost = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $event), [
                'phone' => '089999999999',
                'message' => 'Pesan palsu dari attacker',
                'old_room' => 'Ruang Palsu',
                'new_room' => 'Gudang Palsu',
            ]);

            $tamperedPost->assertRedirect();
            $targetUrl = (string) $tamperedPost->headers->get('Location');
            $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $targetUrl);

            $decoded = rawurldecode(substr($targetUrl, strlen('https://wa.me/6281234567890?text=')));
            $this->assertStringContainsString('Halo Bapak/Ibu Budi Hartono.', $decoded);
            $this->assertStringContainsString('Ruangan Sebelumnya: Aula Utama', $decoded);
            $this->assertStringContainsString('Ruangan Baru: LAB 1', $decoded);
            $this->assertStringNotContainsString('Pesan palsu dari attacker', $decoded);
            $this->assertStringNotContainsString('Ruang Palsu', $decoded);

            // Tombol Tutup membersihkan panel secara bersih
            $this->actingAs($this->admin)->post(route('events.move.whatsapp.dismiss'))
                ->assertRedirect(route('agenda.index'));

            $this->actingAs($this->admin)->get(route('agenda.index'))
                ->assertOk()
                ->assertDontSeeText('Kirim Pemberitahuan WhatsApp kepada Guru');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_non_admin_and_guest_cannot_access_room_move_whatsapp_endpoints_or_view_guru_phone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            $event = Event::create([
                'event_name' => 'Pertemuan Ekstrakurikuler',
                'date' => '2026-11-26',
                'end_date' => '2026-11-26',
                'start_time' => '13:30:00',
                'end_time' => '15:00:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            // Guest diarahkan ke login
            $this->post(route('events.move.whatsapp', $event))
                ->assertRedirect(route('login'));

            $this->post(route('events.move.whatsapp.dismiss'))
                ->assertRedirect(route('login'));

            // Non-Admin (guru, pic, siswa) wajib mendapat 403 Forbidden
            foreach ([$this->guruOwner, $this->otherGuru, $this->picA, $this->siswa] as $nonAdmin) {
                $this->actingAs($nonAdmin)
                    ->post(route('events.move.whatsapp', $event))
                    ->assertForbidden();

                $this->actingAs($nonAdmin)
                    ->post(route('events.move.whatsapp.dismiss'))
                    ->assertForbidden();
            }

            // Kalender publik & API publik tidak membocorkan nomor WhatsApp Guru
            $publicCalendar = $this->get(route('calendar.public'));
            $publicCalendar->assertOk();
            $publicCalendar->assertDontSee('081234567890');
            $publicCalendar->assertDontSee('6281234567890');

            $apiRooms = $this->getJson('/api/ruangan');
            $apiRooms->assertOk();
            $apiRooms->assertDontSee('081234567890');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_shared_phone_normalization_and_pic_complaint_whatsapp_work_without_regression(): void
    {
        $this->assertSame('6281234567890', User::normalizeWhatsAppPhone('081234567890'));
        $this->assertSame('6281234567890', User::normalizeWhatsAppPhone('0812-3456-7890'));
        $this->assertSame('6281234567890', User::normalizeWhatsAppPhone('+62 812-3456-7890'));
        $this->assertSame('6281234567890', User::normalizeWhatsAppPhone('6281234567890'));
        $this->assertNull(User::normalizeWhatsAppPhone(null));
        $this->assertNull(User::normalizeWhatsAppPhone(''));
        $this->assertNull(User::normalizeWhatsAppPhone('abc123'));
        $this->assertNull(User::normalizeWhatsAppPhone('0812'));
        $this->assertNull(User::normalizeWhatsAppPhone('+6208123456789'));

        $complaint = Complaint::create([
            'room_id' => $this->roomA->id,
            'reporter_id' => $this->guruOwner->id,
            'description' => 'Lampu proyektor Aula redup.',
            'status_repair' => 'Belum Diperbaiki',
            'reported_at' => now(),
        ]);

        $waComplaintResponse = $this->actingAs($this->admin)->post(route('complaint.whatsapp', $complaint));
        $waComplaintResponse->assertRedirect();

        $targetUrl = (string) $waComplaintResponse->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/6281111111111?text=', $targetUrl);
        $this->assertStringContainsString(rawurlencode('Aula Utama'), $targetUrl);
    }

    public function test_multi_agenda_session_is_scoped_by_event_id_and_never_mixes_or_wipes_unrelated_notices(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            $eventA = Event::create([
                'event_name' => 'Rapat Koordinasi Kurikulum',
                'date' => '2026-11-18',
                'end_date' => '2026-11-18',
                'start_time' => '08:15:00',
                'end_time' => '09:45:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $eventB = Event::create([
                'event_name' => 'Latihan Presentasi Karya',
                'date' => '2026-11-19',
                'end_date' => '2026-11-19',
                'start_time' => '10:30:00',
                'end_time' => '12:00:00',
                'requester_id' => $this->otherGuru->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $blockerEventB = Event::create([
                'event_name' => 'Tryout LAB 2',
                'date' => '2026-11-19',
                'end_date' => '2026-11-19',
                'start_time' => '10:30:00',
                'end_time' => '12:00:00',
                'requester_id' => $this->otherGuru->id,
                'room_id' => $this->roomC->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            // 1. Admin memindahkan Agenda A (roomA -> roomB)
            $this->actingAs($this->admin)->put(route('events.move', $eventA), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomB->id,
                'move_reason' => 'Perlu proyektor LAB 1',
            ])->assertRedirect(route('agenda.index'));

            $this->actingAs($this->admin)->get(route('agenda.index'))
                ->assertOk()
                ->assertSeeText('Rapat Koordinasi Kurikulum')
                ->assertSeeText('Budi Hartono')
                ->assertSee(route('events.move.whatsapp', $eventA->id));

            // Verifikasi session mengikat admin_id tanpa menyimpan nomor HP ataupun teks pesan mentah
            $sessionPayload = session(RoomMoveWhatsAppService::SESSION_KEY);
            $this->assertIsArray($sessionPayload);
            $this->assertSame($this->admin->id, $sessionPayload['admin_id']);
            $serializedSession = json_encode($sessionPayload, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('081234567890', $serializedSession);
            $this->assertStringNotContainsString('6281234567890', $serializedSession);
            $this->assertStringNotContainsString('Halo Bapak/Ibu', $serializedSession);

            // 2. Admin mengedit Agenda B tanpa mengganti ruangan -> notifikasi Agenda A TIDAK boleh terhapus
            $this->actingAs($this->admin)->put(route('agenda.update', $eventB), [
                'judul' => 'Latihan Presentasi Karya Revisi',
                'tanggal' => '2026-11-19',
                'tanggal_selesai' => '2026-11-19',
                'sesi_mulai' => 5,
                'sesi_selesai' => 6,
                'current_room_id' => $this->roomA->id,
                'ruangan_id' => $this->roomA->id,
                'keterangan' => 'Edit nama tanpa ganti ruangan',
            ])->assertRedirect(route('agenda.index'));

            $this->actingAs($this->admin)->get(route('agenda.index'))
                ->assertOk()
                ->assertSeeText('Rapat Koordinasi Kurikulum')
                ->assertSeeText('Kirim Pemberitahuan WhatsApp kepada Guru')
                ->assertSee(route('events.move.whatsapp', $eventA->id));

            // 3. Pemindahan/validasi Agenda B gagal (bentrok atau reason kosong) -> notifikasi Agenda A tetap bertahan
            $this->actingAs($this->admin)
                ->from(route('events.move.form', $eventB))
                ->put(route('events.move', $eventB), [
                    'current_room_id' => $this->roomA->id,
                    'room_id' => $this->roomC->id,
                    'move_reason' => 'Bentrok LAB 2',
                ])
                ->assertRedirect(route('events.move.form', $eventB))
                ->assertSessionHasErrors('room_id');

            $this->actingAs($this->admin)
                ->from(route('events.move.form', $eventB))
                ->put(route('events.move', $eventB), [
                    'current_room_id' => $this->roomA->id,
                    'room_id' => $this->roomB->id,
                    'move_reason' => '',
                ])
                ->assertRedirect(route('events.move.form', $eventB))
                ->assertSessionHasErrors('move_reason');

            $this->actingAs($this->admin)->get(route('agenda.index'))
                ->assertOk()
                ->assertSeeText('Rapat Koordinasi Kurikulum')
                ->assertSee(route('events.move.whatsapp', $eventA->id));

            // 4. Hapus blocker dan pindahkan Agenda B ke roomC (roomA -> roomC)
            $blockerEventB->delete();

            $this->actingAs($this->admin)->put(route('events.move', $eventB), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomC->id,
                'move_reason' => 'Pindah ke LAB 2',
            ])->assertRedirect(route('agenda.index'));

            // Halaman index menampilkan Agenda B terbaru; halaman show/edit A dan B tetap terisolasi dengan akurat
            $this->actingAs($this->admin)->get(route('agenda.index'))
                ->assertOk()
                ->assertSeeText('Latihan Presentasi Karya Revisi')
                ->assertSeeText('Siti Lainnya')
                ->assertSee(route('events.move.whatsapp', $eventB->id));

            $this->actingAs($this->admin)->get(route('events.show', $eventA))
                ->assertOk()
                ->assertSeeText('Rapat Koordinasi Kurikulum')
                ->assertSeeText('Budi Hartono')
                ->assertSee(route('events.move.whatsapp', $eventA->id))
                ->assertDontSee(route('events.move.whatsapp', $eventB->id));

            $this->actingAs($this->admin)->get(route('events.show', $eventB))
                ->assertOk()
                ->assertSeeText('Latihan Presentasi Karya Revisi')
                ->assertSeeText('Siti Lainnya')
                ->assertSee(route('events.move.whatsapp', $eventB->id))
                ->assertDontSee(route('events.move.whatsapp', $eventA->id));

            // Setiap tombol WA mengarah strictly ke Guru pemilik agenda dan isi pesan yang benar
            $waA = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $eventA));
            $urlA = (string) $waA->headers->get('Location');
            $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $urlA);
            $msgA = rawurldecode(substr($urlA, strlen('https://wa.me/6281234567890?text=')));
            $this->assertStringContainsString('Halo Bapak/Ibu Budi Hartono.', $msgA);
            $this->assertStringContainsString('Nama Agenda: Rapat Koordinasi Kurikulum', $msgA);
            $this->assertStringContainsString('Ruangan Sebelumnya: Aula Utama', $msgA);
            $this->assertStringContainsString('Ruangan Baru: LAB 1', $msgA);

            $waB = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $eventB));
            $urlB = (string) $waB->headers->get('Location');
            $this->assertStringStartsWith('https://wa.me/6281299990000?text=', $urlB);
            $msgB = rawurldecode(substr($urlB, strlen('https://wa.me/6281299990000?text=')));
            $this->assertStringContainsString('Halo Bapak/Ibu Siti Lainnya.', $msgB);
            $this->assertStringContainsString('Nama Agenda: Latihan Presentasi Karya Revisi', $msgB);
            $this->assertStringContainsString('Ruangan Sebelumnya: Aula Utama', $msgB);
            $this->assertStringContainsString('Ruangan Baru: LAB 2', $msgB);

            // 5. Mengedit Agenda A tanpa ganti ruangan hanya menghapus konteks A, sementara B tetap utuh
            $this->actingAs($this->admin)->put(route('agenda.update', $eventA), [
                'judul' => 'Rapat Koordinasi Kurikulum Final',
                'tanggal' => '2026-11-18',
                'tanggal_selesai' => '2026-11-18',
                'sesi_mulai' => 2,
                'sesi_selesai' => 3,
                'current_room_id' => $this->roomB->id,
                'ruangan_id' => $this->roomB->id,
            ])->assertRedirect(route('agenda.index'));

            $this->actingAs($this->admin)->post(route('events.move.whatsapp', $eventA))
                ->assertRedirect(route('agenda.index'))
                ->assertSessionHas('error');

            $waBAfterClearA = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $eventB));
            $this->assertStringStartsWith(
                'https://wa.me/6281299990000?text=',
                (string) $waBAfterClearA->headers->get('Location')
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_logout_and_relogin_clears_previous_room_move_whatsapp_notification(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            $adminLoginUser = User::factory()->create([
                'role' => 'admin',
                'name' => 'Admin Sesi',
                'email' => 'admin.sesi@rapi.test',
                'password' => bcrypt('password123'),
            ]);

            $event = Event::create([
                'event_name' => 'Workshop Desain UI',
                'date' => '2026-11-21',
                'end_date' => '2026-11-21',
                'start_time' => '08:15:00',
                'end_time' => '10:30:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $adminLoginUser->id,
            ]);

            // Login nyata sebagai Admin via POST /login
            $this->post(route('login.proses'), [
                'email' => $adminLoginUser->email,
                'password' => 'password123',
            ])->assertRedirect(route('dashboard.admin'));

            $this->put(route('events.move', $event), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomB->id,
                'move_reason' => 'Maintenance Aula',
            ])->assertRedirect(route('agenda.index'));

            $this->get(route('agenda.index'))
                ->assertOk()
                ->assertSeeText('Kirim Pemberitahuan WhatsApp kepada Guru');

            // Admin logout (POST /logout -> session invalidate)
            $this->post(route('logout'))
                ->assertRedirect('/login');
            $this->assertGuest();

            // Admin login kembali via POST /login
            $this->post(route('login.proses'), [
                'email' => $adminLoginUser->email,
                'password' => 'password123',
            ])->assertRedirect(route('dashboard.admin'));

            $this->get(route('agenda.index'))
                ->assertOk()
                ->assertDontSeeText('Kirim Pemberitahuan WhatsApp kepada Guru')
                ->assertDontSeeText('Pemberitahuan WhatsApp Pemindahan Ruangan');

            $this->post(route('events.move.whatsapp', $event))
                ->assertRedirect(route('agenda.index'))
                ->assertSessionHas('error');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_account_switch_without_logout_cannot_reuse_previous_admin_whatsapp_transition(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            $adminOne = User::factory()->create([
                'role' => 'admin',
                'name' => 'Admin Pertama',
                'email' => 'admin.satu@rapi.test',
                'password' => bcrypt('secretOne123'),
            ]);

            $adminTwo = User::factory()->create([
                'role' => 'admin',
                'name' => 'Admin Kedua',
                'email' => 'admin.dua@rapi.test',
                'password' => bcrypt('secretTwo123'),
            ]);

            $event = Event::create([
                'event_name' => 'Lokakarya Kewirausahaan',
                'date' => '2026-11-23',
                'end_date' => '2026-11-23',
                'start_time' => '09:00:00',
                'end_time' => '11:15:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $adminOne->id,
            ]);

            // Admin 1 login & memindahkan ruangan
            $this->post(route('login.proses'), [
                'email' => $adminOne->email,
                'password' => 'secretOne123',
            ])->assertRedirect(route('dashboard.admin'));

            $this->put(route('events.move', $event), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomB->id,
                'move_reason' => 'Dipindah Admin Pertama',
            ])->assertRedirect(route('agenda.index'));

            $this->assertSame($adminOne->id, session(RoomMoveWhatsAppService::SESSION_KEY . '.admin_id'));

            // Tanpa logout, Admin 2 login di session/browser yang sama (prosesLogin memakai session()->regenerate())
            $this->post(route('login.proses'), [
                'email' => $adminTwo->email,
                'password' => 'secretTwo123',
            ])->assertRedirect(route('dashboard.admin'));

            $this->assertAuthenticatedAs($adminTwo);

            // Admin 2 tidak boleh bisa memakai transisi milik Admin 1 lewat POST endpoint maupun melihat bannernya
            $this->post(route('events.move.whatsapp', $event))
                ->assertRedirect(route('agenda.index'))
                ->assertSessionHas('error');

            $this->assertNull(session(RoomMoveWhatsAppService::SESSION_KEY));

            $this->get(route('agenda.index'))
                ->assertOk()
                ->assertDontSeeText('Kirim Pemberitahuan WhatsApp kepada Guru');

            // Bahkan jika Admin 1 login ulang lagi setelah pergantian akun, konteks lama sudah bersih dengan aman
            $this->post(route('login.proses'), [
                'email' => $adminOne->email,
                'password' => 'secretOne123',
            ])->assertRedirect(route('dashboard.admin'));

            $this->get(route('agenda.index'))
                ->assertOk()
                ->assertDontSeeText('Kirim Pemberitahuan WhatsApp kepada Guru');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_fresh_db_phone_lookup_and_delete_scoped_clearing_behave_safely(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00'));

        try {
            $eventA = Event::create([
                'event_name' => 'Rapat Komite Sekolah',
                'date' => '2026-11-24',
                'end_date' => '2026-11-24',
                'start_time' => '08:15:00',
                'end_time' => '09:45:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $eventB = Event::create([
                'event_name' => 'Bimbingan OSIS',
                'date' => '2026-11-24',
                'end_date' => '2026-11-24',
                'start_time' => '10:30:00',
                'end_time' => '12:00:00',
                'requester_id' => $this->otherGuru->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $this->actingAs($this->admin)->put(route('events.move', $eventA), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomB->id,
                'move_reason' => 'Keperluan rapat',
            ])->assertRedirect(route('agenda.index'));

            $this->actingAs($this->admin)->put(route('events.move', $eventB), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomC->id,
                'move_reason' => 'Keperluan OSIS',
            ])->assertRedirect(route('agenda.index'));

            // Nomor WA Guru diubah di database setelah pemindahan -> sistem selalu membaca nomor terbaru dari database
            $this->guruOwner->update(['phone' => '6287778889990']);

            $waA = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $eventA), [
                'phone' => '089999999999',
                'admin_id' => 999999,
                'requester_id' => $this->otherGuru->id,
            ]);
            $this->assertStringStartsWith(
                'https://wa.me/6287778889990?text=',
                (string) $waA->headers->get('Location')
            );

            // Jika transisi Agenda B menjadi tidak valid di DB (misalnya dipindah balik secara eksternal), hanya konteks B yang dibersihkan
            $eventB->refresh();
            $eventB->update(['room_id' => $this->roomA->id]);

            $this->actingAs($this->admin)->post(route('events.move.whatsapp', $eventB))
                ->assertRedirect(route('agenda.index'))
                ->assertSessionHas('error');

            $transitionsAfterInvalidB = session(RoomMoveWhatsAppService::SESSION_KEY . '.transitions', []);
            $this->assertArrayNotHasKey($eventB->id, $transitionsAfterInvalidB);
            $this->assertArrayHasKey($eventA->id, $transitionsAfterInvalidB);

            // Pindahkan ulang Agenda B agar masuk session kembali, lalu hapus Agenda B -> hanya konteks B terhapus dari session
            $this->actingAs($this->admin)->put(route('events.move', $eventB), [
                'current_room_id' => $this->roomA->id,
                'room_id' => $this->roomC->id,
                'move_reason' => 'Keperluan OSIS',
            ])->assertRedirect(route('agenda.index'));

            $this->actingAs($this->admin)->delete(route('agenda.destroy', $eventB))
                ->assertRedirect(route('agenda.index'));

            $transitionsAfterDestroyB = session(RoomMoveWhatsAppService::SESSION_KEY . '.transitions', []);
            $this->assertArrayNotHasKey($eventB->id, $transitionsAfterDestroyB);
            $this->assertArrayHasKey($eventA->id, $transitionsAfterDestroyB);

            $this->actingAs($this->admin)->post(route('events.move.whatsapp', $eventB->id))
                ->assertNotFound();

            $waAAfterDestroyB = $this->actingAs($this->admin)->post(route('events.move.whatsapp', $eventA));
            $this->assertStringStartsWith(
                'https://wa.me/6287778889990?text=',
                (string) $waAAfterDestroyB->headers->get('Location')
            );
        } finally {
            Carbon::setTestNow();
        }
    }
}
