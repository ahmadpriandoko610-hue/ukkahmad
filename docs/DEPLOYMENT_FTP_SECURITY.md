# Keamanan Transfer Deployment FTP/FTPS RAPI-Sekolah

Dokumen ini mencatat hasil pemeriksaan teknis dukungan FTPS pada server hosting cPanel produksi serta risiko dan rekomendasi migrasi dari `.github/workflows/deploy.yml`.

## Hasil Pemeriksaan Dukungan FTPS Hosting (11 November 2026)

Pengujian koneksi langsung terhadap endpoint hosting produksi (`ahmad-rapi.web.id` / `ftp.ahmad-rapi.web.id`, IP `157.66.55.62`) menunjukkan:

1. **Implicit FTPS (port 990)**: Koneksi ditolak (`curl: (7) Failed to connect to ahmad-rapi.web.id:990`).
2. **Explicit FTPS (port 21 via `AUTH TLS` / `AUTH SSL` pada ProFTPD)**:
   - Server menerima perintah `AUTH SSL` (`234 AUTH SSL successful`).
   - Namun **verifikasi sertifikat TLS ketat gagal** karena dua kendala konfigurasi server hosting:
     - **Hostname Mismatch**: Sertifikat TLS ProFTPD diterbitkan hanya untuk `CN=srv100.hypercloudhost.com` (`SAN: DNS:srv100.hypercloudhost.com`) dan **tidak** mencakup `ahmad-rapi.web.id` ataupun `ftp.ahmad-rapi.web.id` (`curl (60)`).
     - **Rantai Sertifikat Tidak Lengkap**: ProFTPD hanya mengirim leaf certificate (`depth=0`) tanpa intermediate CA chain (`Let's Encrypt YR1`), sehingga runner GitHub Actions (`ubuntu-latest` dengan curl/OpenSSL) gagal memverifikasi sertifikat meskipun mengarah ke `srv100.hypercloudhost.com` (`Verify return code: 21 unable to verify the first certificate`).
3. **Verifikasi TLS Tidak Boleh Dinonaktifkan**: Penggunaan flag `-k` / `--insecure` dilarang karena menghilangkan autentikasi server dan tetap rentan terhadap serangan Man-in-the-Middle (MITM).

## Risiko Penggunaan Plaintext `ftp://` Saat Ini

- Kredensial akun FTP (`FTP_USERNAME` dan `FTP_PASSWORD`) dikirim dalam bentuk plaintext pada kanal kontrol FTP sehingga berisiko disadap (eavesdropping) maupun dimodifikasi pada jalur jaringan transit.
- Data yang diunggah melalui FTP (`.htaccess`, probe staging, `auto_extract.php`, dan berkas `deploy_payload.zip`) tidak dilindungi enkripsi transport TLS — meskipun isi `deploy_payload.zip` sendiri telah dienkripsi menggunakan `DEPLOY_TOKEN` dan eksekusi Stage 3 (`auto_extract.php`) berjalan melalui HTTPS terverifikasi.

## Rekomendasi Migrasi Keamanan Transport

Untuk menghilangkan risiko plaintext FTP tanpa mengorbankan integritas sertifikat TLS:

1. **Opsi A — Aktifkan FTPS Terverifikasi Penuh (`--ssl-reqd`)**:
   - Minta penyedia hosting cPanel memasang sertifikat TLS lengkap (leaf + full intermediate CA chain) pada layanan ProFTPD yang mencakup domain/subdomain deployment (`ftp.ahmad-rapi.web.id` dan `ahmad-rapi.web.id`).
   - Setelah sertifikat valid diverifikasi oleh `curl --ssl-reqd` tanpa flag `--insecure`, tambahkan `--ssl-reqd` pada seluruh perintah `curl` FTP Stage 1 dan Stage 2.
2. **Opsi B — Migrasi ke SFTP/SSH (jika diaktifkan oleh pihak hosting)**:
   - Jika paket cPanel menyediakan akses SSH/SFTP, gunakan SFTP dengan kunci privat SSH dedicated yang disimpan di GitHub Secrets serta verifikasi fingerprint host (`known_hosts`) ketat sebelum transfer.
3. **Mitigasi Sementara**:
   - Lakukan rotasi berkala terhadap kredensial FTP (`FTP_USERNAME`, `FTP_PASSWORD`) dan `DEPLOY_TOKEN` menggunakan akun FTP terbatas hanya pada direktori target deployment.

