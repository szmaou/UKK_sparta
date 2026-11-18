# SPARTA

Sistem Peminjaman Barang & Alat untuk Admin, Petugas, dan Peminjam.
Baseline kebutuhan: [`docs/PRD-SPARTA.md`](docs/PRD-SPARTA.md).

## Status implementasi

Tersedia: schema MariaDB, SQL routines, seed demo, fondasi PHP MVC, login/logout,
CSRF, pemeriksaan role, dashboard dasar, serta CRUD pengguna, kategori, dan alat
untuk Admin. Master data mendukung pencarian, pagination, audit perubahan, dan
perlindungan stok serta histori transaksi. Akun dinonaktifkan tanpa menghapus
histori; Admin tidak dapat menonaktifkan atau mengganti role akunnya sendiri.
CRUD transaksi, alur peminjaman,
persetujuan, pengembalian, dan laporan belum tersedia. Dashboard tidak menampilkan
angka fiktif.

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

2. Export konfigurasi koneksi pada shell yang akan menjalankan PHP:

   ```sh
   export DB_HOST=127.0.0.1
   export DB_PORT=3306
   export DB_NAME=sparta
   export DB_USER=sparta
   read -rsp 'DB password: ' DB_PASSWORD; echo
   export DB_PASSWORD
   ```

   `.env.example` hanya referensi variabel. Aplikasi tidak memuat `.env` otomatis.
   Sebagai alternatif lokal, buat `config/database.local.php` yang mengembalikan
   array dengan key `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASSWORD`.
   File ini diabaikan Git; batasi izin ke `600`. Environment variable selalu
   mengalahkan konfigurasi lokal, termasuk password kosong.

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
```

Uji HTTP membutuhkan `curl` dan menjalankan development server sementara sendiri.
Exit status `2` pada uji integrasi berarti database tidak tersedia, bukan lulus.

Konfigurasi koneksi memakai variabel `DB_*` yang sama. Status implementasi bukan
bukti bahwa seluruh acceptance criteria PRD sudah terpenuhi.
