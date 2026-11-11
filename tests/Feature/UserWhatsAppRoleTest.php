<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserWhatsAppRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Utama',
            'email' => 'admin.utama@rapi.test',
        ]);
    }

    /**
     * 1. Role PIC dapat mengisi dan menyimpan nomor WhatsApp untuk pengaduan kerusakan via wa.me.
     */
    public function test_pic_role_can_save_and_update_whatsapp_number(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Budi PIC Sarpras',
            'email' => 'budi.pic@rapi.test',
            'password' => 'password123',
            'role' => 'pic',
            'phone' => '081234567890',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'budi.pic@rapi.test',
            'role' => 'pic',
            'phone' => '081234567890',
        ]);

        $pic = User::where('email', 'budi.pic@rapi.test')->firstOrFail();

        $updateResponse = $this->actingAs($this->admin)->put(route('users.update', $pic), [
            'name' => 'Budi PIC Diperbarui',
            'email' => 'budi.pic@rapi.test',
            'role' => 'pic',
            'phone' => '089876543210',
        ]);

        $updateResponse->assertRedirect(route('users.index'));
        $this->assertSame('089876543210', $pic->fresh()->phone);
    }

    /**
     * 2. Role Admin tidak dapat menyimpan nomor WhatsApp.
     */
    public function test_admin_role_cannot_save_whatsapp_number(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Admin Tambahan',
            'email' => 'admin.tambahan@rapi.test',
            'password' => 'password123',
            'role' => 'admin',
            'phone' => '081234567890',
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'admin.tambahan@rapi.test',
            'role' => 'admin',
            'phone' => null,
        ]);

        $adminUser = User::where('email', 'admin.tambahan@rapi.test')->firstOrFail();
        $this->assertNull($adminUser->phone);

        $this->actingAs($this->admin)->put(route('users.update', $adminUser), [
            'name' => 'Admin Tambahan Edit',
            'email' => 'admin.tambahan@rapi.test',
            'role' => 'admin',
            'phone' => '081299999999',
        ]);

        $this->assertNull($adminUser->fresh()->phone);
    }

    /**
     * 3. Role Guru / Organisasi tidak dapat menyimpan nomor WhatsApp.
     */
    public function test_guru_role_cannot_save_whatsapp_number(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Guru Matematika',
            'email' => 'guru.mtk@rapi.test',
            'password' => 'password123',
            'role' => 'guru',
            'phone' => '081234567890',
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'guru.mtk@rapi.test',
            'role' => 'guru',
            'phone' => null,
        ]);

        $guru = User::where('email', 'guru.mtk@rapi.test')->firstOrFail();
        $this->assertNull($guru->phone);

        $this->actingAs($this->admin)->put(route('users.update', $guru), [
            'name' => 'Guru Matematika Edit',
            'email' => 'guru.mtk@rapi.test',
            'role' => 'guru',
            'phone' => '081288888888',
        ]);

        $this->assertNull($guru->fresh()->phone);
    }

    /**
     * 4. Role Siswa tidak dapat menyimpan nomor WhatsApp.
     */
    public function test_siswa_role_cannot_save_whatsapp_number(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Siswa RPL 1',
            'email' => 'siswa.rpl@rapi.test',
            'password' => 'password123',
            'role' => 'siswa',
            'phone' => '081234567890',
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'siswa.rpl@rapi.test',
            'role' => 'siswa',
            'phone' => null,
        ]);

        $siswa = User::where('email', 'siswa.rpl@rapi.test')->firstOrFail();
        $this->assertNull($siswa->phone);

        $this->actingAs($this->admin)->put(route('users.update', $siswa), [
            'name' => 'Siswa RPL 1 Edit',
            'email' => 'siswa.rpl@rapi.test',
            'role' => 'siswa',
            'phone' => '081277777777',
        ]);

        $this->assertNull($siswa->fresh()->phone);
    }

    /**
     * 5. Mengubah role PIC menjadi non-PIC mengosongkan nomor WhatsApp.
     */
    public function test_changing_pic_role_to_non_pic_clears_whatsapp_number(): void
    {
        $picToGuru = User::factory()->create([
            'role' => 'pic',
            'phone' => '081234567890',
        ]);
        $this->assertSame('081234567890', $picToGuru->phone);

        $this->actingAs($this->admin)->put(route('users.update', $picToGuru), [
            'name' => $picToGuru->name,
            'email' => $picToGuru->email,
            'role' => 'guru',
            'phone' => '081234567890',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('guru', $picToGuru->fresh()->role);
        $this->assertNull($picToGuru->fresh()->phone);

        $picToAdmin = User::factory()->create([
            'role' => 'pic',
            'phone' => '081233333333',
        ]);
        $this->actingAs($this->admin)->put(route('users.update', $picToAdmin), [
            'name' => $picToAdmin->name,
            'email' => $picToAdmin->email,
            'role' => 'admin',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('admin', $picToAdmin->fresh()->role);
        $this->assertNull($picToAdmin->fresh()->phone);

        $picToSiswa = User::factory()->create([
            'role' => 'pic',
            'phone' => '081244444444',
        ]);
        $this->actingAs($this->admin)->put(route('users.update', $picToSiswa), [
            'name' => $picToSiswa->name,
            'email' => $picToSiswa->email,
            'role' => 'siswa',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('siswa', $picToSiswa->fresh()->role);
        $this->assertNull($picToSiswa->fresh()->phone);
    }

    /**
     * 6. Halaman Tambah User dan Edit User menampilkan kondisi input khusus PIC tanpa fitur opt-in Guru.
     */
    public function test_create_and_edit_user_page_displays_correct_input_condition(): void
    {
        $createResponse = $this->actingAs($this->admin)->get(route('users.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('placeholder="Khusus untuk PIC"', false);
        $createResponse->assertSee('disabled', false);
        $createResponse->assertDontSeeText('persetujuan penerimaan notifikasi pemindahan ruangan');

        $picUser = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Khusus',
            'email' => 'pic.khusus@rapi.test',
            'phone' => '081234567890',
        ]);
        $editPicResponse = $this->actingAs($this->admin)->get(route('users.edit', $picUser));
        $editPicResponse->assertOk();
        $editPicResponse->assertSee('value="081234567890"', false);
        $editPicResponse->assertSee('placeholder="Contoh: 081234567890"', false);

        $guruUser = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru Biologi',
            'email' => 'guru.biologi@rapi.test',
        ]);
        $editGuruResponse = $this->actingAs($this->admin)->get(route('users.edit', $guruUser));
        $editGuruResponse->assertOk();
        $editGuruResponse->assertSee('placeholder="Khusus untuk PIC"', false);
        $editGuruResponse->assertSee('disabled', false);
        $editGuruResponse->assertDontSeeText('Status Persetujuan Notifikasi WhatsApp Guru');

        $editAdminResponse = $this->actingAs($this->admin)->get(route('users.edit', $this->admin));
        $editAdminResponse->assertOk();
        $editAdminResponse->assertSee('placeholder="Khusus untuk PIC"', false);
        $editAdminResponse->assertSee('disabled', false);

        $usersIndex = $this->actingAs($this->admin)->get(route('users.index'));
        $usersIndex->assertOk();
        $usersIndex->assertDontSeeText('Persetujuan Notifikasi WA');
        $usersIndex->assertDontSeeText('WhatsApp (Tersamar)');
    }

    /**
     * 7. Request langsung ke backend tidak dapat menyimpan phone untuk role non-PIC.
     */
    public function test_direct_http_request_cannot_bypass_whatsapp_rule_for_non_pic(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Direct Request Guru',
            'email' => 'direct.guru@rapi.test',
            'password' => 'secret123',
            'role' => 'guru',
            'phone' => '081234567890',
        ]);

        $created = User::where('email', 'direct.guru@rapi.test')->firstOrFail();
        $this->assertSame('guru', $created->role);
        $this->assertNull($created->phone);

        $this->actingAs($this->admin)->put(route('users.update', $created), [
            'name' => 'Direct Request Guru Updated',
            'email' => 'direct.guru@rapi.test',
            'role' => 'guru',
            'phone' => '081999999999',
        ]);

        $this->assertNull($created->fresh()->phone);

        $directAdmin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081299998888',
        ]);
        $this->assertNull($directAdmin->phone);
    }

    /**
     * 8. Fitur peringatan WhatsApp pengaduan kerusakan ke PIC via wa.me tetap berfungsi normal.
     */
    public function test_pic_complaint_whatsapp_warning_redirects_to_wa_me_link(): void
    {
        $pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Bengkel',
            'phone' => '081234567890',
        ]);

        $guru = User::factory()->create(['role' => 'guru']);
        $room = Room::create([
            'room_name' => 'Bengkel RPL',
            'location' => 'Gedung D',
            'capacity' => 36,
            'pic_id' => $pic->id,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $guru->id,
            'description' => 'Stop kontak meja komputer rusak.',
            'status_repair' => 'Belum Diperbaiki',
            'reported_at' => now(),
        ]);

        $indexResponse = $this->actingAs($this->admin)->get(route('admin.complaints'));
        $indexResponse->assertOk();
        $indexResponse->assertSee(route('complaint.whatsapp', $complaint->id));
        $indexResponse->assertSeeText('Kirim WhatsApp ke PIC');

        $waResponse = $this->actingAs($this->admin)->post(route('complaint.whatsapp', $complaint));
        $waResponse->assertRedirect();

        $targetUrl = (string) $waResponse->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $targetUrl);
        $this->assertStringContainsString(rawurlencode('Bengkel RPL'), $targetUrl);
    }
}
