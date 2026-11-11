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
     * 1. Role Guru / Organisasi dapat mengisi, menyimpan, dan memperbarui nomor WhatsApp ternormalisasi.
     */
    public function test_guru_role_can_save_and_update_whatsapp_number(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Guru Informatika',
            'email' => 'guru.if@rapi.test',
            'password' => 'password123',
            'role' => 'guru',
            'phone' => '081234567890',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'guru.if@rapi.test',
            'role' => 'guru',
            'phone' => '6281234567890',
        ]);

        $guru = User::where('email', 'guru.if@rapi.test')->firstOrFail();

        $updateResponse = $this->actingAs($this->admin)->put(route('users.update', $guru), [
            'name' => 'Guru Informatika Update',
            'email' => 'guru.if@rapi.test',
            'role' => 'guru',
            'phone' => '+62 898-7654-3210',
        ]);

        $updateResponse->assertRedirect(route('users.index'));
        $this->assertSame('6289876543210', $guru->fresh()->phone);
    }

    /**
     * 2. Role PIC tetap dapat mengisi, menyimpan, dan memperbarui nomor WhatsApp ternormalisasi.
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
            'phone' => '6281234567890',
        ]);

        $pic = User::where('email', 'budi.pic@rapi.test')->firstOrFail();

        $updateResponse = $this->actingAs($this->admin)->put(route('users.update', $pic), [
            'name' => 'Budi PIC Diperbarui',
            'email' => 'budi.pic@rapi.test',
            'role' => 'pic',
            'phone' => '089876543210',
        ]);

        $updateResponse->assertRedirect(route('users.index'));
        $this->assertSame('6289876543210', $pic->fresh()->phone);
    }

    /**
     * 3a. Role Admin tidak dapat menyimpan nomor WhatsApp.
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
            'phone' => '+6281299999999',
        ]);

        $this->assertNull($adminUser->fresh()->phone);
    }

    /**
     * 3b. Role Siswa tidak dapat menyimpan nomor WhatsApp.
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
            'phone' => '6281277777777',
        ]);

        $this->assertNull($siswa->fresh()->phone);
    }

    /**
     * 4 & 5. Normalisasi nomor lokal 08..., +62..., dan 62... secara konsisten.
     */
    public function test_phone_numbers_are_normalized_consistently_across_formats(): void
    {
        $cases = [
            'guru.lokal@rapi.test' => ['role' => 'guru', 'input' => '081234567890', 'expected' => '6281234567890'],
            'guru.plus62@rapi.test' => ['role' => 'guru', 'input' => '+6281234567891', 'expected' => '6281234567891'],
            'guru.format@rapi.test' => ['role' => 'guru', 'input' => '+62 813-4567-8902', 'expected' => '6281345678902'],
            'pic.62@rapi.test' => ['role' => 'pic', 'input' => '6285712345678', 'expected' => '6285712345678'],
        ];

        foreach ($cases as $email => $case) {
            $response = $this->actingAs($this->admin)->post(route('users.store'), [
                'name' => 'User Uji Normalisasi',
                'email' => $email,
                'password' => 'password123',
                'role' => $case['role'],
                'phone' => $case['input'],
            ]);

            $response->assertRedirect(route('users.index'));
            $response->assertSessionHasNoErrors();

            $this->assertDatabaseHas('users', [
                'email' => $email,
                'role' => $case['role'],
                'phone' => $case['expected'],
            ]);
        }
    }

    /**
     * 6. Nomor WhatsApp tidak valid ditolak dengan error validasi.
     */
    public function test_invalid_whatsapp_numbers_are_rejected_for_guru_and_pic(): void
    {
        $invalidNumbers = [
            'abc12345678',
            '08123',
            '08123456',
            '0812345678901234',
            '+62081234567890',
            '62081234567890',
            '+14155552671',
            '0215551234',
            '12345678901',
        ];

        foreach ($invalidNumbers as $index => $invalidPhone) {
            $email = "invalid.guru.{$index}@rapi.test";
            $response = $this->actingAs($this->admin)
                ->from(route('users.create'))
                ->post(route('users.store'), [
                    'name' => 'Guru Nomor Invalid',
                    'email' => $email,
                    'password' => 'password123',
                    'role' => $index % 2 === 0 ? 'guru' : 'pic',
                    'phone' => $invalidPhone,
                ]);

            $response->assertRedirect(route('users.create'));
            $response->assertSessionHasErrors('phone');
            $this->assertDatabaseMissing('users', ['email' => $email]);
        }

        // Update dengan nomor invalid juga ditolak dan tidak merusak nilai lama
        $guru = User::factory()->create([
            'role' => 'guru',
            'phone' => '081255556666',
        ]);

        $updateInvalid = $this->actingAs($this->admin)
            ->from(route('users.edit', $guru))
            ->put(route('users.update', $guru), [
                'name' => $guru->name,
                'email' => $guru->email,
                'role' => 'guru',
                'phone' => 'bukan-nomor-wa',
            ]);

        $updateInvalid->assertRedirect(route('users.edit', $guru));
        $updateInvalid->assertSessionHasErrors('phone');
        $this->assertSame('6281255556666', $guru->fresh()->phone);
    }

    /**
     * Mengubah role Guru atau PIC menjadi Admin/Siswa mengosongkan nomor WhatsApp;
     * sementara ubah antara Guru dan PIC tetap diizinkan menyimpan nomor valid.
     */
    public function test_changing_guru_or_pic_to_admin_or_siswa_clears_whatsapp_number(): void
    {
        $picToGuru = User::factory()->create([
            'role' => 'pic',
            'phone' => '081234567890',
        ]);
        $this->assertSame('6281234567890', $picToGuru->phone);

        $this->actingAs($this->admin)->put(route('users.update', $picToGuru), [
            'name' => $picToGuru->name,
            'email' => $picToGuru->email,
            'role' => 'guru',
            'phone' => '081234567890',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('guru', $picToGuru->fresh()->role);
        $this->assertSame('6281234567890', $picToGuru->fresh()->phone);

        $guruToAdmin = User::factory()->create([
            'role' => 'guru',
            'phone' => '081233333333',
        ]);
        $this->actingAs($this->admin)->put(route('users.update', $guruToAdmin), [
            'name' => $guruToAdmin->name,
            'email' => $guruToAdmin->email,
            'role' => 'admin',
            'phone' => '081233333333',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('admin', $guruToAdmin->fresh()->role);
        $this->assertNull($guruToAdmin->fresh()->phone);

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
     * Halaman Tambah User dan Edit User menampilkan kondisi input yang sesuai kebijakan role guru & pic.
     */
    public function test_create_and_edit_user_page_displays_correct_input_condition(): void
    {
        // A. Halaman Tambah User: default role adalah admin, sehingga nomor WhatsApp dinonaktifkan
        $createResponse = $this->actingAs($this->admin)->get(route('users.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('placeholder="Khusus untuk Guru / Organisasi dan PIC"', false);
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
        $editPicResponse->assertSee('value="6281234567890"', false);
        $editPicResponse->assertSee('placeholder="Contoh: 081234567890 atau +6281234567890"', false);

        // C. Halaman Edit User untuk role Guru: input aktif dan menampilkan nomor WhatsApp
        $guruUser = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru Biologi',
            'email' => 'guru.biologi@rapi.test',
            'phone' => '+6281377778888',
        ]);
        $editGuruResponse = $this->actingAs($this->admin)->get(route('users.edit', $guruUser));
        $editGuruResponse->assertOk();
        $editGuruResponse->assertSee('value="6281377778888"', false);
        $editGuruResponse->assertSee('placeholder="Contoh: 081234567890 atau +6281234567890"', false);

        // D. Halaman Edit User untuk role Admin & Siswa: input dinonaktifkan
        $editAdminResponse = $this->actingAs($this->admin)->get(route('users.edit', $this->admin));
        $editAdminResponse->assertOk();
        $editAdminResponse->assertSee('placeholder="Khusus untuk Guru / Organisasi dan PIC"', false);
        $editAdminResponse->assertSee('disabled', false);

        $siswaUser = User::factory()->create([
            'role' => 'siswa',
            'name' => 'Siswa Uji',
            'email' => 'siswa.uji@rapi.test',
        ]);
        $editSiswaResponse = $this->actingAs($this->admin)->get(route('users.edit', $siswaUser));
        $editSiswaResponse->assertOk();
        $editSiswaResponse->assertSee('placeholder="Khusus untuk Guru / Organisasi dan PIC"', false);
        $editSiswaResponse->assertSee('disabled', false);
    }

    /**
     * Request langsung ke backend tidak dapat menyimpan phone pada role Admin maupun Siswa.
     */
    public function test_direct_http_request_cannot_bypass_whatsapp_rule_for_admin_or_siswa(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Direct Request Siswa',
            'email' => 'direct.siswa@rapi.test',
            'password' => 'secret123',
            'role' => 'siswa',
            'phone' => '081234567890',
        ]);

        $createdSiswa = User::where('email', 'direct.siswa@rapi.test')->firstOrFail();
        $this->assertSame('siswa', $createdSiswa->role);
        $this->assertNull($createdSiswa->phone);

        // Simpan langsung via model pun tetap membersihkan phone untuk admin/siswa
        $directModel = User::factory()->create([
            'role' => 'admin',
            'phone' => '+6281299998888',
        ]);
        $this->assertNull($directModel->phone);
    }
}
