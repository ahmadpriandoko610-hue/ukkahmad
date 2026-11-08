<?php

namespace Tests\Feature;

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
     * 1. Role PIC dapat mengisi dan menyimpan nomor WhatsApp.
     */
    public function test_pic_role_can_save_and_update_whatsapp_number(): void
    {
        // Tambah User baru sebagai PIC dengan nomor WhatsApp
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

        $pic = User::where('email', 'budi.pic@rapi.test')->first();

        // Update nomor WhatsApp PIC
        $updateResponse = $this->actingAs($this->admin)->put(route('users.update', $pic), [
            'name' => 'Budi PIC Diperbarui',
            'email' => 'budi.pic@rapi.test',
            'role' => 'pic',
            'phone' => '089876543210',
        ]);

        $updateResponse->assertRedirect(route('users.index'));
        $this->assertEquals('089876543210', $pic->fresh()->phone);
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

        $adminUser = User::where('email', 'admin.tambahan@rapi.test')->first();
        $this->assertNull($adminUser->phone);

        // Percobaan update dengan nomor WhatsApp
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

        $guru = User::where('email', 'guru.mtk@rapi.test')->first();
        $this->assertNull($guru->phone);

        // Percobaan update dengan nomor WhatsApp
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

        $siswa = User::where('email', 'siswa.rpl@rapi.test')->first();
        $this->assertNull($siswa->phone);

        // Percobaan update dengan nomor WhatsApp
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
        // A. Ubah PIC ke Guru
        $pic1 = User::factory()->create([
            'role' => 'pic',
            'phone' => '081234567890',
        ]);
        $this->assertEquals('081234567890', $pic1->phone);

        $this->actingAs($this->admin)->put(route('users.update', $pic1), [
            'name' => $pic1->name,
            'email' => $pic1->email,
            'role' => 'guru',
            'phone' => '081234567890', // input dikirim atau tidak dikirim harus dibersihkan
        ]);

        $this->assertEquals('guru', $pic1->fresh()->role);
        $this->assertNull($pic1->fresh()->phone);

        // B. Ubah PIC ke Admin
        $pic2 = User::factory()->create([
            'role' => 'pic',
            'phone' => '081233333333',
        ]);
        $this->actingAs($this->admin)->put(route('users.update', $pic2), [
            'name' => $pic2->name,
            'email' => $pic2->email,
            'role' => 'admin',
        ]);
        $this->assertEquals('admin', $pic2->fresh()->role);
        $this->assertNull($pic2->fresh()->phone);

        // C. Ubah PIC ke Siswa
        $pic3 = User::factory()->create([
            'role' => 'pic',
            'phone' => '081244444444',
        ]);
        $this->actingAs($this->admin)->put(route('users.update', $pic3), [
            'name' => $pic3->name,
            'email' => $pic3->email,
            'role' => 'siswa',
        ]);
        $this->assertEquals('siswa', $pic3->fresh()->role);
        $this->assertNull($pic3->fresh()->phone);
    }

    /**
     * 6. Halaman Tambah User dan Edit User menampilkan kondisi input yang benar.
     */
    public function test_create_and_edit_user_page_displays_correct_input_condition(): void
    {
        // A. Halaman Tambah User: default role adalah admin, sehingga nomor WhatsApp dinonaktifkan
        $createResponse = $this->actingAs($this->admin)->get(route('users.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('placeholder="Khusus untuk PIC"', false);
        $createResponse->assertSee('disabled', false);

        // B. Halaman Edit User untuk role PIC: input aktif dan menampilkan nomor WhatsApp
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

        // C. Halaman Edit User untuk role non-PIC (Guru): input dinonaktifkan, nilai kosong
        $guruUser = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru Biologi',
            'email' => 'guru.biologi@rapi.test',
        ]);
        $editGuruResponse = $this->actingAs($this->admin)->get(route('users.edit', $guruUser));
        $editGuruResponse->assertOk();
        $editGuruResponse->assertSee('placeholder="Khusus untuk PIC"', false);
        $editGuruResponse->assertSee('disabled', false);

        // D. Halaman Edit User untuk role Admin: input dinonaktifkan
        $editAdminResponse = $this->actingAs($this->admin)->get(route('users.edit', $this->admin));
        $editAdminResponse->assertOk();
        $editAdminResponse->assertSee('placeholder="Khusus untuk PIC"', false);
        $editAdminResponse->assertSee('disabled', false);
    }

    /**
     * 7. Request langsung ke backend tidak dapat melewati aturan tersebut.
     */
    public function test_direct_http_request_cannot_bypass_whatsapp_rule_for_non_pic(): void
    {
        // Request langsung ke store dengan role guru dan nomor telepon disisipkan
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Direct Request User',
            'email' => 'direct.request@rapi.test',
            'password' => 'secret123',
            'role' => 'guru',
            'phone' => '081234567890',
        ]);

        $created = User::where('email', 'direct.request@rapi.test')->first();
        $this->assertNotNull($created);
        $this->assertEquals('guru', $created->role);
        // Pastikan phone tetap null meskipun dikirim langsung via HTTP
        $this->assertNull($created->phone);
        $this->assertDatabaseMissing('users', [
            'email' => 'direct.request@rapi.test',
            'phone' => '081234567890',
        ]);

        // Request langsung ke update dengan model non-PIC
        $this->actingAs($this->admin)->put(route('users.update', $created), [
            'name' => 'Direct Request User Updated',
            'email' => 'direct.request@rapi.test',
            'role' => 'guru',
            'phone' => '081999999999',
        ]);

        $this->assertNull($created->fresh()->phone);
    }
}

