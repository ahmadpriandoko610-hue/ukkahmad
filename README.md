# RAPI-Sekolah

<p>
  <strong>RAPI-Sekolah</strong> adalah aplikasi web dan REST API berbasis <strong>Laravel 13</strong> untuk pengelolaan jadwal ruangan, peminjaman dan verifikasi agenda kegiatan sekolah, kalender agenda publik, serta pelaporan kerusakan fasilitas sekolah secara terintegrasi.
</p>

<p>
  <img src="https://img.shields.io/badge/PHP-%5E8.3-777BB4?logo=php&logoColor=white" alt="PHP 8.3">
  <img src="https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/Tailwind_CSS-4.0-06B6D4?logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4">
  <img src="https://img.shields.io/badge/Vite-8.0-646CFF?logo=vite&logoColor=white" alt="Vite 8">
  <img src="https://img.shields.io/badge/License-MIT-green" alt="MIT License">
</p>

---

## Tentang Website

**RAPI-Sekolah** dirancang untuk memudahkan koordinasi pemanfaatan ruangan dan pemeliharaan sarana sekolah antara **Admin**, **PIC (Penanggung Jawab Ruangan)**, **Guru / Organisasi**, **Siswa**, dan **Pengunjung Publik**:

- **Kalender Agenda Sekolah (Publik & Internal):** Menampilkan agenda sekolah yang telah disetujui secara transparan tanpa perlu login, lengkap dengan penanda warna (agenda Admin/Sekolah, agenda Guru/Organisasi, hari libur nasional, dan hari penting nasional melalui `App\Services\NationalCalendarService`).
- **Pengajuan & Verifikasi Agenda Ruangan:** Guru / Organisasi maupun Admin dapat mengajukan peminjaman satu atau beberapa ruangan sekaligus (`group_id`), termasuk agenda multi-hari (`end_date`), dengan pemeriksaan bentrok jadwal ruangan otomatis. PIC ruangan atau Admin dapat menyetujui, menolak beserta alasan, atau memindahkan ruangan agenda sambil mengirim notifikasi internal (`ScheduleNotification`).
- **Pengaduan Kerusakan Fasilitas:** Guru dan Admin dapat melaporkan kerusakan fasilitas ruangan (maupun kerusakan terkait agenda disetujui) beserta lampiran foto bukti. PIC menangani perbaikan dengan pembaruan status (`Belum Diperbaiki`, `Menunggu Spare Part`, `Sudah Diperbaiki`) serta catatan perbaikan, dan Admin dapat mengirim peringatan cepat ke WhatsApp PIC via tautan `https://wa.me/...` untuk pengaduan yang belum diperbaiki.
- **Import Pengguna Massal & Laporan PDF:** Admin dapat mengimpor data pengguna secara massal melalui file Excel (`.xlsx`) lengkap dengan template resmi dan tahap pratinjau validasi, serta mengunduh laporan agenda dan laporan kerusakan fasilitas dalam format PDF (`App\Support\TablePdf`).
- **REST API Mobile:** Menyediakan layanan autentikasi Bearer token untuk aplikasi mobile Flutter (login Guru, PIC, Siswa) serta akses jadwal, agenda, pengaduan, dan notifikasi.

---

## Hak Akses & Fitur per Role

| Role | Akses Platform | Kemampuan Utama |
| :--- | :--- | :--- |
| **Publik / Tamu** | Web (Tanpa Login) | Melihat Kalender Agenda Sekolah (`/` & `/calendar`) beserta hari libur dan hari penting nasional. |
| **Admin** | Web | Dashboard statistik; kelola data user & import massal Excel; kelola data ruangan & penugasan PIC; kelola semua agenda (buat, edit, setujui, tolak, batal, pindah ruangan); laporan agenda harian/mingguan/bulanan + ekspor PDF; pantau pengaduan kerusakan & kirim peringatan WhatsApp (`wa.me`) ke PIC; lihat kalender & jadwal ruangan. |
| **PIC Ruangan** | Web & REST API | Dashboard khusus ruangan tanggung jawabnya; verifikasi pengajuan agenda (`Setujui` / `Tolak` dengan alasan); lihat kalender & jadwal ruangan; tangani pengaduan kerusakan & perbarui status perbaikan; laporan kerusakan harian/mingguan/bulanan + ekspor PDF. |
| **Guru / Organisasi** | Web & REST API | Dashboard guru; lihat daftar **Agenda Saya**; ajukan agenda (satu/multi-ruangan & multi-hari); lihat kalender agenda & jadwal ruangan; buat dan pantau pengaduan kerusakan fasilitas beserta bukti foto. |
| **Siswa** | Aplikasi Mobile (REST API) & Kalender Publik | Melihat agenda sekolah yang disetujui melalui aplikasi mobile atau kalender publik tanpa login (login ke dashboard web ditolak demi pemisahan hak akses). |

---

## Teknologi yang Digunakan

- **Backend:** PHP `^8.3`, Laravel Framework `^13.8`
- **Frontend:** Blade Templates, Vite `^8.0`, Tailwind CSS `^4.0`, FullCalendar (`@fullcalendar/core`, `@fullcalendar/daygrid`, `@fullcalendar/interaction` `^6.1.21`)
- **Ekspor & Import Dokumen:** `maatwebsite/excel` (`^4.0`) / PhpSpreadsheet untuk template & import pengguna Excel (`.xlsx`), serta `App\Support\TablePdf` untuk generasi laporan PDF
- **Database:** MySQL (produksi / Laragon) dan SQLite (pengembangan lokal & pengujian otomatis)
- **Pengujian:** PHPUnit `^12.5` (`phpunit.xml`)
- **CI/CD:** GitHub Actions (`.github/workflows/deploy.yml`) ke shared hosting cPanel

---

## Ringkasan Endpoint REST API (`/api/...`)

### Endpoint Publik
- `GET /api/test` — Cek status koneksi REST API RAPI-Sekolah
- `GET /api/ruangan`, `GET /api/dataRuang`, `GET /api/ruangan/{id}` — Daftar dan detail ruangan aktif
- `GET /api/kalender` — Kalender agenda sekolah dan hari libur/penting nasional
- `POST /api/login` — Login mobile (`Guru`, `PIC`, `Siswa`; akun `Admin` khusus website)
- `POST /api/forgot-password`, `POST /api/verify-password-code`, `POST /api/reset-password` — Reset password menggunakan kode verifikasi email 6 digit

### Endpoint Terautentikasi (`Authorization: Bearer <token>`)
- `GET /api/user`, `GET /api/profile`, `POST /api/logout` — Profil sesi aktif & logout
- `GET /api/agenda`, `GET /api/agenda/{id}` — Daftar & detail agenda (`admin`, `pic`, `guru`, `siswa`)
- `POST /api/agenda`, `POST /api/peminjaman`, `PUT /api/agenda/{id}`, `DELETE /api/agenda/{id}` — Pengajuan, pembaruan, dan pembatalan agenda (`guru`)
- `POST /api/agenda/{id}/approve`, `POST /api/agenda/{id}/reject` — Verifikasi agenda oleh `pic`
- `PUT /api/agenda/{id}/pindah-ruangan` — Pemindahan ruangan agenda oleh `admin`
- `GET /api/pengaduan`, `GET /api/pengaduan/{id}`, `POST /api/pengaduan` — Pengaduan kerusakan fasilitas (`guru`, `pic`)
- `GET /api/pic/pengaduan/report`, `GET /api/pengaduan/report`, `PUT /api/pengaduan/{id}/status` — Laporan & pembaruan status perbaikan oleh `pic`
- `GET /api/notifikasi`, `PUT /api/notifikasi/{id}/read` — Daftar & tanda baca notifikasi internal

> Seluruh token API aktif milik pengguna otomatis dicabut apabila pengguna melakukan reset password baik melalui Web maupun REST API.

---

## Instalasi & Menjalankan Secara Lokal

### Prasyarat
- PHP `>= 8.3` dengan ekstensi standar Laravel (`mbstring`, `xml`, `ctype`, `iconv`, `intl`, `pdo_mysql`/`pdo_sqlite`, `bcmath`, `fileinfo`, `zip`, `gd`)
- Composer
- Node.js `>= 20` & NPM
- MySQL atau SQLite

### Langkah Setup

1. **Clone repository & masuk ke direktori proyek:**
   ```bash
   git clone https://github.com/ahmadpriandoko610-hue/ukkahmad.git
   cd ukkahmad
   ```

2. **Install dependensi PHP & JavaScript:**
   ```bash
   composer install
   npm install
   ```

3. **Salin konfigurasi environment & buat application key:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Konfigurasikan database pada `.env`:**
   - Untuk **SQLite**, pastikan `DB_CONNECTION=sqlite` dan berkas `database/database.sqlite` tersedia.
   - Untuk **MySQL** (misalnya via Laragon), sesuaikan `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`.

5. **Jalankan migrasi database, seeder awal (opsional), dan symlink storage:**
   ```bash
   php artisan migrate
   php artisan db:seed
   php artisan storage:link
   ```

6. **Build aset frontend & jalankan server lokal:**
   ```bash
   npm run build
   php artisan serve
   ```
   Atau gunakan mode pengembangan sekaligus (server, queue listener, log, dan Vite dev server):
   ```bash
   composer run dev
   ```

---

## Reset Password melalui Gmail (SMTP)

Fitur lupa password memakai Password Broker Laravel serta kode verifikasi email 6 digit untuk semua role. Isi kredensial pengirim di `.env` (jangan pernah menyimpan App Password ke Git atau source code):

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=alamat-gmail-pengirim@gmail.com
MAIL_PASSWORD=app-password-gmail
MAIL_FROM_ADDRESS="${MAIL_USERNAME}"
MAIL_FROM_NAME="${APP_NAME}"
AUTH_PASSWORD_RESET_EXPIRE=60
```

Ganti `MAIL_USERNAME` dengan akun pengirim dan `MAIL_PASSWORD` dengan **App Password** Gmail (memerlukan Verifikasi 2 Langkah Google; jangan gunakan password utama Gmail). Apabila nilai SMTP atau alamat pengirim belum diisi, formulir akan memberi tahu bahwa email belum dikonfigurasi dan tidak akan mengklaim tautan/kode terkirim.

Setelah mengisi `.env`, jalankan:

```bash
php artisan config:clear
```

Pastikan `APP_URL` sesuai dengan alamat dasar aplikasi yang dibuka di browser. Kirim permintaan reset menggunakan email akun yang terdaftar, lalu periksa Inbox maupun folder Spam. SMTP Gmail menggunakan TLS/STARTTLS pada port `587`.

---

## Pengujian (Testing)

Proyek dilengkapi suite PHPUnit (unit dan feature test) yang berjalan terisolasi menggunakan database SQLite `:memory:` sesuai konfigurasi `phpunit.xml`:

```bash
php artisan test
```

Atau menggunakan script Composer:

```bash
composer test
```

Cakupan pengujian meliputi validasi hak akses role web & API, keamanan & revokasi token API, deteksi bentrok jadwal (harian, multi-hari, dan multi-ruangan), masa tampil agenda guru, sorting ruangan, import pengguna Excel, alur pengaduan & peringatan `wa.me`, reset password, serta keamanan script deployment.

---

## Deployment Otomatis (GitHub Actions ke cPanel)

Setiap `push` ke branch `main` memicu workflow **Deploy to cPanel** (`.github/workflows/deploy.yml`) yang menjalankan:

1. Build dependensi produksi (`composer install --no-dev`) dan kompilasi aset frontend (`npm run build`).
2. Pengemasan paket aplikasi (`ukk_rapi` + `public_html`) tanpa menyertakan `.env*`, `storage` lokal, `tests`, `node_modules`, atau berkas `.sql`.
3. Pengunggahan staging shield (HTTP 403), paket ZIP terenkripsi (`DEPLOY_TOKEN`), validasi integritas & anti path-traversal/zip-bomb pada server cPanel, backup pra-deploy dengan mode maintenance sementara, serta mekanisme rollback otomatis apabila ekstraksi gagal.

Catatan teknis mengenai konfigurasi FTP/FTPS dan keamanan transfer tersedia di [docs/DEPLOYMENT_FTP_SECURITY.md](docs/DEPLOYMENT_FTP_SECURITY.md).

---

## Lisensi

Aplikasi ini dibangun di atas framework Laravel yang berlisensi [MIT License](https://opensource.org/licenses/MIT).
