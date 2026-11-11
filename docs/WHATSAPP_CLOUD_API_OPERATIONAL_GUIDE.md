# Panduan Operasional Integrasi Meta WhatsApp Cloud API — RAPI-Sekolah

Dokumen ini menjelaskan spesifikasi resmi template, audit infrastruktur cPanel, langkah aktivasi operasional untuk Admin/IT Sekolah, serta rancangan pengembangan lanjutan (Tahap 4) tanpa menyimpan kredensial atau melakukan pengiriman pesan sungguhan selama fase kode & pengujian.

---

## 1. Spesifikasi Template Resmi Meta

- **Nama Template (`WHATSAPP_TEMPLATE_NAME`)**: `notifikasi_pemindahan_ruangan`
- **Bahasa (`WHATSAPP_TEMPLATE_LANGUAGE`)**: `id` (Indonesian)
- **Kategori**: `UTILITY`
- **Status di Kode vs Meta**: Kode aplikasi telah mendukung struktur 6 parameter Meta Cloud API secara penuh, namun **template WAJIB didaftarkan dan disetujui (`APPROVED`) terlebih dahulu di Meta WhatsApp Manager** sebelum fitur diaktifkan untuk pesan langsung (`WHATSAPP_DRY_RUN=false`).

### Daftar 6 Parameter Body Terurut

Setiap pengiriman disanitasi otomatis oleh [`MetaCloudApiDriver`](../app/Services/WhatsApp/MetaCloudApiDriver.php) agar bebas newline (`\r`, `\n`), tab (`\t`), karakter kontrol, maupun spasi berlebih (>4 spasi beruntun), dan wajib memiliki fallback non-kosong:

| Posisi | Variabel | Sumber Data Aplikasi | Contoh Nilai Sanitasi |
| :--- | :--- | :--- | :--- |
| `{{1}}` | Nama agenda | `events.event_name` | `Workshop LKS Nasional 2026` |
| `{{2}}` | Tanggal agenda | `events.date` – `end_date` (Asia/Jakarta) | `20 November 2026` atau `20 November 2026 s/d 21 November 2026` |
| `{{3}}` | Jam agenda (WIB) | `events.start_time` – `end_time` | `08:15 - 10:30 WIB` |
| `{{4}}` | Ruangan lama | `rooms.room_name` sebelum pindah | `Aula Utama` |
| `{{5}}` | Ruangan baru | `rooms.room_name` tujuan | `Lab RPL 1` |
| `{{6}}` | Alasan pemindahan | `move_reason` (atau fallback saat edit Web) | `Perbaikan listrik ruang utama` *(fallback: `Penyesuaian jadwal dan penggunaan ruangan oleh Admin.`)* |

### Contoh Body Template untuk Pengajuan di Meta WhatsApp Manager

```text
Yth. Bapak/Ibu Guru/Organisasi,

Terdapat pemindahan ruangan pada agenda RAPI-Sekolah Anda dengan rincian berikut:
Agenda: {{1}}
Tanggal: {{2}}
Waktu (WIB): {{3}}
Ruangan Sebelumnya: {{4}}
Ruangan Baru: {{5}}
Alasan: {{6}}

Silakan periksa detail terbaru melalui dashboard RAPI-Sekolah. Terima kasih.
```

> **Catatan Meta Verification:** Pastikan sample variable yang diisi saat submit di WhatsApp Manager menggunakan teks contoh realistis tanpa placeholder kurung kurawal agar review kategori `UTILITY` tidak ditolak/dialihkan ke `MARKETING`.

---

## 2. Contoh Payload Resmi Meta Cloud API (Simulasi Aman Tanpa Rahasia)

Payload berikut identik dengan hasil [`WhatsAppService::previewTemplatePayload()`](../app/Services/WhatsAppService.php) saat mode simulasi maupun live (dengan nomor dan token tersamar):

```json
{
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "6281******890",
  "type": "template",
  "template": {
    "name": "notifikasi_pemindahan_ruangan",
    "language": {
      "code": "id"
    },
    "components": [
      {
        "type": "body",
        "parameters": [
          { "type": "text", "text": "Workshop LKS Nasional 2026" },
          { "type": "text", "text": "20 November 2026" },
          { "type": "text", "text": "08:15 - 10:30 WIB" },
          { "type": "text", "text": "Aula Utama" },
          { "type": "text", "text": "Lab RPL 1" },
          { "type": "text", "text": "Perbaikan listrik dan AC ruang utama" }
        ]
      }
    ]
  }
}
```

Endpoint tujuan:
`POST https://graph.facebook.com/v21.0/<WHATSAPP_PHONE_NUMBER_ID>/messages`
Header otorisasi:
`Authorization: Bearer <WHATSAPP_ACCESS_TOKEN>`

---

## 3. Audit Infrastruktur cPanel & Strategi Pengiriman

Hasil pemeriksaan konfigurasi deployment ([`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml), [`routes/console.php`](../routes/console.php), dan `.env`):

1. Hosting menggunakan **shared hosting cPanel** dengan deployment FTP stateless + `auto_extract.php`.
2. **Tidak tersedia daemon Supervisor** maupun `php artisan queue:work` yang berjalan permanen di background.
3. Oleh karena itu, arsitektur pengiriman & perlindungan duplikasi diterapkan sebagai berikut:
   - **Penguncian & Identitas Transisi Atomik:** Transaksi pemindahan ruangan Web (`EventController::move()`, `EventController::update()`) maupun API (`Api\EventController::moveRoom()`) mengunci baris `events` (`lockForUpdate()`), memeriksa stale `current_room_id` dan bentrok jadwal di dalam transaksi, serta mencatat `room_move_version` dan `last_room_move_transition_id` (UUID persisten per pemindahan sah).
   - **Pre-HTTP Claim (`pending`):** Sebelum memanggil Meta Cloud API (`DB::afterCommit()`), `WhatsAppService` mengklaim satu baris `whatsapp_notification_logs` berstatus `pending` berbasis `UNIQUE(idempotency_key)` di luar transaksi panjang agar tidak menahan lock database selama HTTP berjalan. Jika klaim atau status akhir yang memblokir (`pending`, `accepted`, `delivered`, `read`, `dry_run`, `ambiguous_timeout`) sudah ada, panggilan ulang ditolak (`skipped_duplicate`) tanpa HTTP kedua.
   - **Timeout & Kegagalan HTTP Terklasifikasi:**
     - `accepted`: HTTP 2xx disertai `messages.0.id`.
     - `failed` (`INVALID_META_RESPONSE`): HTTP 2xx tanpa `messages.0.id`.
     - `ambiguous_timeout`: Timeout / putus koneksi dengan hasil penerimaan Meta tidak pasti (**tidak pernah di-retry otomatis**, klaim tetap dipertahankan).
     - `connection_failed`: Kegagalan koneksi yang dapat dibuktikan terjadi sebelum request terkirim (misal gagal DNS / connection refused).
     - `HTTP 400 / 401`: Kegagalan permanen data/konfigurasi (`next_retry_at = null`).
     - `HTTP 429`: Rate limit (`next_retry_at` mengikuti header `Retry-After` atau +5 menit untuk kebijakan retry terkontrol).
     - `HTTP 5xx`: Kegagalan server Meta yang ambigu (`next_retry_at = null`; **tidak ada auto-retry membabi buta**).
   - **Ketahanan Gagal Update Log Pasca-HTTP:** Jika HTTP 200 + `wamid` sukses namun pembaruan baris `pending` ke database gagal, baris `pending` tetap dipertahankan (tidak dihapus / di-reset), kegagalan dicatat via `Log::critical()` tersanitasi tanpa token/nomor penuh, dan pemindahan ruangan tetap terkomit.
   - **Rekonsiliasi Manual Terkontrol:** Operator dapat memeriksa daftar log tertunda atau merekonsiliasi hasil verifikasi Meta WhatsApp Manager via perintah Artisan:
     - Daftar antrean rekonsiliasi: `php artisan whatsapp:reconcile`
     - Penyelesaian log: `php artisan whatsapp:reconcile --log_id=<ID> --status=accepted --message_id=<WAMID> --note="Terverifikasi di Meta"`

### Rancangan Tahap Lanjutan (Opsional Jika Diperlukan)

1. **Kebijakan Retry Eksplisit & Terbatas (`HTTP 429` / `connection_failed`)**:
   - Hanya baris dengan status `failed` berkode `RATE_LIMITED` (`HTTP 429`) atau `connection_failed` yang memenuhi syarat metode `WhatsAppService::retryFailedNotification()` (menggunakan identitas transisi & baris klaim yang sama, tanpa pernah menyentuh `ambiguous_timeout`, `pending`, `HTTP 400/401`, maupun `HTTP 5xx`).
2. **Webhook Status Pengiriman Meta (`delivered` / `read`) + Verifikasi Signature**:
   - Status awal dari Graph API `/messages` hanyalah `accepted` (diterima antrean Meta), belum berarti pesan sampai ke perangkat Guru.
   - Pada Tahap Lanjutan, endpoint webhook callback Meta wajib:
     - Mendukung verifikasi challenge `GET` (`hub.mode=subscribe`, `hub.verify_token` cocok dengan `services.whatsapp.webhook_verify_token`),
     - Memvalidasi setiap `POST` menggunakan header `X-Hub-Signature-256` dengan membandingkan `'sha256=' . hash_hmac('sha256', $request->getContent(), config('services.whatsapp.app_secret'))` via `hash_equals()`,
     - Memperbarui baris `whatsapp_notification_logs` berdasarkan `meta_message_id` (`delivered_at`, `read_at`, `status = delivered` / `read`) secara idempoten.

---

## 4. Langkah Penyiapan Sekolah di Meta & cPanel (Panduan Praktis)

### Langkah 1 — Meta Business Portfolio & WhatsApp Business Account (WABA)
1. Masuk ke [Meta Business Suite](https://business.facebook.com/) menggunakan akun resmi sekolah.
2. Buat atau verifikasi **Business Portfolio** sekolah dan hubungkan aset **WhatsApp Account (WABA)**.
3. Pada Meta for Developers, buat App bertipe **Business** dan aktifkan produk **WhatsApp**.

### Langkah 2 — Menyiapkan Nomor Bisnis & Phone Number ID
1. Daftarkan nomor telepon resmi sekolah (yang belum terhubung ke WhatsApp personal/Business App reguler pada ponsel) ke WABA.
2. Selesaikan verifikasi OTP SMS/telepon.
3. Catat **Phone Number ID** dan **WhatsApp Business Account ID (WABA ID)** dari halaman *WhatsApp > API Setup*.

### Langkah 3 — Membuat System User Token Permanen
1. Jangan gunakan temporary 24-hour user token di produksi.
2. Di **Meta Business Settings > Users > System Users**, buat System User baru (misal `rapi-sekolah-notifier`, role Admin).
3. Berikan akses aset **WhatsApp Account** dan App WhatsApp dengan izin `whatsapp_business_messaging` serta `whatsapp_business_management`.
4. Generate **Access Token permanen** dengan kedua izin di atas dan simpan di password manager sekolah (jangan pernah commit ke Git!).

### Langkah 4 — Mendaftarkan & Menunggu Approval Template
1. Buka **Meta WhatsApp Manager > Account Tools > Message Templates**.
2. Buat template baru:
   - Name: `notifikasi_pemindahan_ruangan`
   - Category: `Utility`
   - Language: `Indonesian (id)`
3. Isi body template tepat dengan 6 variabel `{{1}}`..`{{6}}` sesuai Bagian 1 dokumen ini dan lengkapi sample example.
4. Kirim untuk review dan tunggu status menjadi **Approved (Active)**.

### Langkah 5 — Menjalankan Migrasi Database & Mengisi `.env` Produksi di cPanel
1. Jalankan migrasi tabel baru di server produksi (atau cek sebelum aktivasi):
   ```bash
   php artisan migrate
   ```
   Migrasi menambahkan kolom `whatsapp_opt_in*` di tabel `users` dan membuat tabel `whatsapp_notification_logs`.
2. Edit file `.env` produksi di cPanel (`~/ukk_rapi/.env`), tambahkan variabel berikut secara bertahap (**pertama kali WAJIB tetap dengan `WHATSAPP_DRY_RUN=true`**):

   ```dotenv
   WHATSAPP_ENABLED=true
   WHATSAPP_DRIVER=meta_cloud_api
   WHATSAPP_DRY_RUN=true
   WHATSAPP_REQUIRE_CONSENT=true
   WHATSAPP_TEST_ALLOWLIST_ONLY=true
   WHATSAPP_TEST_ALLOWLIST=08xxxxxxxxxx
   WHATSAPP_GRAPH_BASE_URL=https://graph.facebook.com
   WHATSAPP_GRAPH_VERSION=v21.0
   WHATSAPP_PHONE_NUMBER_ID=ISI_PHONE_NUMBER_ID_DARI_META
   WHATSAPP_ACCESS_TOKEN=ISI_SYSTEM_USER_TOKEN_DARI_META
   WHATSAPP_TEMPLATE_NAME=notifikasi_pemindahan_ruangan
   WHATSAPP_TEMPLATE_LANGUAGE=id
   WHATSAPP_CONNECT_TIMEOUT=5
   WHATSAPP_TIMEOUT=10
   ```
3. Bersihkan cache konfigurasi jika config caching digunakan (`php artisan config:clear`).

### Langkah 6 — Pengujian Tahap 1: Dry-Run (Simulasi di Produksi/Staging Tanpa Pesan Masuk)
1. Login sebagai Guru uji coba -> buka menu **Notifikasi WhatsApp** -> simpan nomor HP yang terdaftar di `WHATSAPP_TEST_ALLOWLIST` -> centang persetujuan eksplisit -> simpan.
2. Login sebagai Admin -> pindahkan satu agenda uji milik Guru tersebut ke ruangan lain.
3. Verifikasi flash warning Admin menyatakan mode simulasi (`dry-run`), pesan tidak terkirim ke HP Guru, namun log berstatus `Simulasi (Dry-Run)` tercatat di halaman **Detail Agenda**.

### Langkah 7 — Uji Coba Pengiriman Terbatas Pertama (Allowlist Live)
1. Pastikan template `notifikasi_pemindahan_ruangan` di Meta sudah `Approved` dan `WHATSAPP_TEST_ALLOWLIST` berisi **hanya nomor tester internal (1–2 nomor)**.
2. Ubah di `.env` cPanel:
   ```dotenv
   WHATSAPP_DRY_RUN=false
   WHATSAPP_TEST_ALLOWLIST_ONLY=true
   ```
3. Admin memindahkan agenda uji milik Guru tester -> pastikan Admin melihat status `accepted` (diterima antrean Meta) dan pesan template 6 parameter masuk ke WhatsApp Guru tester dengan format tanggal Indonesia dan waktu WIB yang tepat.
4. Setelah pengujian terbatas dinyatakan stabil, barulah sekolah dapat memutuskan menonaktifkan batasan allowlist (`WHATSAPP_TEST_ALLOWLIST_ONLY=false`) jika seluruh Guru yang siap menerima notifikasi telah memberikan consent opt-in.

### Langkah 8 — Emergency Kill Switch (Matikan Instan Bila Terjadi Gangguan)
Kapan saja jika terjadi kuota terlampaui, masalah token, atau anomali pengiriman:
1. Ubah di `.env` cPanel:
   ```dotenv
   WHATSAPP_ENABLED=false
   ```
   *(Atau kembali ke mode simulasi aman dengan `WHATSAPP_DRY_RUN=true`.)*
2. Jika config cache pernah dijalankan, eksekusi `php artisan config:clear`.
3. Fitur utama aplikasi (**pemindahan ruangan, kalender, persetujuan PIC, notifikasi internal `ScheduleNotification`, dan tombol `wa.me` pengaduan PIC**) **tetap berjalan normal 100%** meskipun integrasi Meta Cloud API dimatikan.

