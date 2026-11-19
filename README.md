# SPARTA

Sistem Peminjaman Barang & Alat untuk Admin, Petugas, dan Peminjam.
Baseline kebutuhan: [`docs/PRD-SPARTA.md`](docs/PRD-SPARTA.md).

## Status implementasi

Tersedia: schema MariaDB, SQL routines, seed demo, fondasi PHP MVC, login/logout,
CSRF, pemeriksaan role, penguncian sementara setelah percobaan login gagal berulang,
dashboard KPI per peran, serta CRUD pengguna, kategori, dan alat
untuk Admin. Master data mendukung pencarian, pagination, audit perubahan, dan
perlindungan stok serta histori transaksi. Akun dinonaktifkan tanpa menghapus
histori; Admin tidak dapat menonaktifkan atau mengganti role akunnya sendiri.
Peminjam dapat mencari katalog, mengajukan peminjaman, dan melihat riwayat sendiri.
Petugas dapat menyetujui atau menolak pengajuan. Stok dikurangi dalam transaksi
saat persetujuan, bukan saat pengajuan. Peminjam dapat mengajukan pengembalian;
Petugas memantau dan memverifikasi penerimaan sebelum stok dipulihkan tepat sekali;
hanya kondisi **Baik** yang menambah stok tersedia, alat rusak atau hilang tidak.
Admin dapat menelusuri dan mengoreksi transaksi: peminjaman hanya boleh dikoreksi
atau dihapus selama statusnya menunggu/ditolak, dan pengembalian hanya boleh
dikoreksi setelah diverifikasi; menghapus pengembalian yang belum diverifikasi
mengembalikan pinjaman ke status disetujui tanpa menyentuh stok. Log aktivitas
tersedia untuk Admin dengan filter aksi dan pencarian. Petugas dapat mencetak
laporan peminjaman per rentang tanggal dan status lewat halaman siap cetak.
Hari keterlambatan dihitung dengan tarif denda yang dapat diatur Admin di
`/admin/pengaturan` (bawaan Rp0); tarif baru hanya berlaku untuk verifikasi
berikutnya. Kondisi alat dicatat, tetapi penghapusan aset rusak/hilang belum termasuk
MVP. Dashboard tidak menampilkan angka fiktif.

## Requirement

- PHP 8.2 atau lebih baru dengan PDO MySQL.
- MariaDB dengan InnoDB dan dukungan `CHECK` constraint.
- Node.js dan npm untuk build CSS lokal; tidak diperlukan untuk runtime PHP.
- Document root harus menunjuk ke `public/`, bukan root repository.

## Menjalankan lokal

1. Buat database kosong dan user database khusus. Import schema dan routines dengan
   akun yang memiliki izin yang sesuai:

   ```sh
   mariadb -u root -p -e 'CREATE DATABASE sparta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
   mariadb -u root -p sparta < database/schema.sql
   mariadb -u root -p sparta < database/routines.sql
   ```

   Pembuatan user dan pemberian izin mengikuti konfigurasi MariaDB setempat.
   Jangan gunakan akun root sebagai akun runtime aplikasi.

2. Salin `.env.example` menjadi `.env`, lalu isi kredensial database:

   ```sh
   cp .env.example .env
   chmod 600 .env
   ```

   Aplikasi memuat `.env` otomatis (lihat `config/database.php`). Environment
   variable yang sudah diexport tetap menang atas `.env`, termasuk password kosong.
   Sebagai alternatif lokal, buat `config/database.local.php` yang mengembalikan
   array dengan key `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASSWORD`;
   file itu diabaikan Git. Kedua file `.env` dan `database.local.php` tidak boleh
   di-commit.

3. Buat data demo, build CSS, lalu jalankan development server:

   ```sh
   php database/seeders/seed.php
   npm ci
   npm run build
   php -S 127.0.0.1:8000 -t public public/router.php
   ```

4. Buka <http://127.0.0.1:8000/login>.

Development server PHP bukan server produksi. Pada hosting, arahkan document root
ke `public/` agar konfigurasi, SQL, dan source privat tidak dapat diunduh lewat URL.

## Akun demo lokal

| Role     | Username   | Password      |
| -------- | ---------- | ------------- |
| Admin    | `admin`    | `admin123`    |
| Petugas  | `petugas`  | `petugas123`  |
| Peminjam | `peminjam` | `peminjam123` |

Seeder tidak mengganti akun yang sudah ada. Detail database dan pemeriksaan
integritas tersedia pada [`database/README.md`](database/README.md).

## Assets dan desain

- Tailwind CSS dan daisyUI dikompilasi ke `public/assets/css/app.css`.
- Icons memakai SVG [Lucide](https://lucide.dev) lokal pada
  `public/assets/icons/lucide/`; license dan metadata disertakan.
- Tidak ada ketergantungan CDN saat runtime.
- `design/SPARTA.figcraft` merupakan sumber desain kanonik. Export JSON, modular
  read view, screenshots, dan validation logs diabaikan Git.

## Pengujian

Gunakan **database pengujian khusus**, bukan database produksi. Uji HTTP membuat
serta menghapus akun fixture bernama `it_*`; uji database menggunakan fixture
transaksional dan memeriksa seed demo.

```sh
php tests/database_config_test.php
php tests/security_test.php
php tests/database/schema_test.php
php tests/http_auth_test.php
php tests/http_master_data_test.php
php tests/http_users_test.php
php tests/http_loans_test.php
php tests/http_returns_test.php
php tests/http_backoffice_test.php
```

Uji HTTP membutuhkan `curl` dan menjalankan development server sementara sendiri.
Pastikan ekstensi `curl` PHP aktif (`php -m | grep curl`). Uji yang membuat fixture
menolak database non-disposable kecuali `SPARTA_ALLOW_NONDISPOSABLE_DB=1` diset.
Exit status `2` pada uji integrasi berarti database tidak tersedia, bukan lulus.

Konfigurasi koneksi memakai variabel `DB_*` yang sama. Status implementasi bukan
bukti bahwa seluruh acceptance criteria PRD sudah terpenuhi.

## Deployment Docker

Stack: `alpine:3.24` + nginx + PHP-FPM 8.5 dari paket Alpine (`php85*`) dan MariaDB 11.4.
Alpine v3.24 menyediakan `php85` 8.5.12-r0, versi terbaru pada cabang tersebut dan rilis
pertama yang memuat perbaikan **php2shell** (CVE-2026-109618 dan CVE-2026-109619) sesuai
Alpine Security Tracker. Versi paket PHP di-pin lewat build arg `PHP_APK_VERSION` pada
`Dockerfile` agar rebuild tidak diam-diam berganti interpreter; naikkan nilai itu saat
Alpine merilis patch yang lebih baru (`docker compose build --pull --build-arg PHP_APK_VERSION=<versi>`).

### Menjalankan

```sh
docker compose up --build -d
```

Aplikasi tersedia di `http://localhost:${APP_PORT}` (default `8080`). Port host diatur
lewat `APP_PORT` pada `.env`. `public/` menjadi document root di dalam container;
konfigurasi nginx meneruskan PHP ke php-fpm dan membatasi unggahan sesuai kebutuhan
aplikasi.

### Layanan, volume, dan inisialisasi

- `app`: image aplikasi (nginx + php-fpm). Port host `APP_PORT` (default `8080`) → container `80`.
- `db`: `mariadb:11.4` dengan healthcheck; `app` menunggu database sehat. Port host
  `DB_PUBLISH_PORT` (default `3307`) dipublikasikan hanya ke `127.0.0.1` untuk akses admin lokal.
- `db_data`: volume data MariaDB (persisten).
- `uploads`: volume `/var/www/html/public/uploads/alat` untuk foto alat (persisten).
- `sessions`: volume `/var/lib/php/sessions` untuk sesi PHP (persisten, jadi pengguna tidak
  ter-logout saat container `app` dibuat ulang).
- Inisialisasi database (hanya saat volume `db_data` masih kosong, urut):
  `database/schema.sql` → `database/routines.sql` → `database/seeders/seed.sql`.

Koneksi database dikonfigurasi lewat environment variable pada layanan `app` (dibaca oleh
`config/database.php`). `DB_HOST=db` dan `DB_PORT=3306` tetap di dalam jaringan Compose;
`DB_NAME`, `DB_USER`, dan `DB_PASSWORD` diambil dari `.env` di root proyek lewat interpolasi
Compose (`.env` tidak ikut masuk ke image; hanya dibaca saat `docker compose` berjalan).
Bila `.env` tidak ada, nilai fallback demo `sparta`/`sparta`/`sparta` dipakai. Untuk produksi,
salin `.env.example` menjadi `.env` dan ganti kredensial; `DB_ROOT_PASSWORD` (default `root`)
juga dapat diatur di `.env`. Di Docker, `DB_PUBLISH_PORT` menentukan port host untuk
mengakses MariaDB dari mesin ini (bind `127.0.0.1`); aplikasi di dalam container selalu
memakai `db:3306`. Seeder membuat akun demo (admin/petugas/peminjam) bila belum ada;
ganti/kunci kredensial demo sebelum deployment produksi.

Untuk menghentikan:

```sh
docker compose down        # pertahankan volume data
docker compose down -v     # hapus volume (data & unggahan hilang)
```
