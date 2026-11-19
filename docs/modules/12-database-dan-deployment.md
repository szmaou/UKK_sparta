# 12 — Database dan Deployment

## Tujuan dan cakupan

Dokumen ini menjelaskan struktur database, migrasi, routine SQL, seed, konfigurasi
koneksi, dan cara aplikasi dijalankan. Fokusnya adalah kontrak operasional yang
benar-benar ada di working tree, bukan rancangan yang belum diimplementasikan.

Yang tidak dirinci di sini: class CSS, markup view, dan setiap endpoint modul lain.
Endpoint dijelaskan pada dokumen modulnya masing-masing.

## Peta file

| Path | Fungsi |
|---|---|
| [`database/schema.sql`](../../database/schema.sql) | Schema lengkap untuk instalasi baru |
| [`database/migrations/`](../../database/migrations/) | Perubahan untuk database yang sudah ada |
| [`database/routines.sql`](../../database/routines.sql) | Function, procedure, dan trigger |
| [`database/seeders/seed.php`](../../database/seeders/seed.php) | Seeder CLI untuk instalasi lokal |
| [`database/seeders/seed.sql`](../../database/seeders/seed.sql) | Seed untuk inisialisasi container MariaDB |
| [`config/database.php`](../../config/database.php) | Loader `.env` dan kontrak koneksi |
| [`bootstrap/app.php`](../../bootstrap/app.php) | Pembuatan PDO dari kontrak koneksi |
| [`app/bootstrap.php`](../../app/bootstrap.php) | Container aplikasi, service, controller, route |
| [`app/autoload.php`](../../app/autoload.php) | Autoloader tanpa Composer |
| [`app/routes.php`](../../app/routes.php) | Tabel route aktual |
| [`public/index.php`](../../public/index.php) | Front controller web |
| [`public/router.php`](../../public/router.php) | Router untuk PHP built-in server |
| [`Dockerfile`](../../Dockerfile) | Image nginx + PHP-FPM |
| [`docker-compose.yml`](../../docker-compose.yml) | Service aplikasi dan MariaDB |
| [`docker/entrypoint.sh`](../../docker/entrypoint.sh) | Entrypoint container aplikasi |
| [`docker/nginx/default.conf`](../../docker/nginx/default.conf) | Konfigurasi nginx |
| [`docker/php/php.ini`](../../docker/php/php.ini) | Konfigurasi PHP runtime |
| [`docker/php/www.conf`](../../docker/php/www.conf) | Konfigurasi PHP-FPM |
| [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) | Urutan pemeriksaan CI |

Tidak ada Composer. `app/autoload.php` memetakan namespace `App\Foo\Bar` ke
`app/Foo/Bar.php` dan memuat `app/helpers.php`.

## Entry point runtime

### Web

`public/index.php` adalah satu-satunya entry point web:

1. Memuat `app/bootstrap.php`.
2. Menjalankan `App\Core\App` dengan router, session, auth, dan error controller.
3. Menangkap `Throwable`; kegagalan dicatat ke `error_log`.
4. Merender `errors/500` bila view masih tersedia, atau HTML minimal bila bootstrap
   gagal sebelum view terbentuk.

Document root harus menunjuk ke `public/`, bukan root repository. `app/`, `config/`,
`database/`, `tests/`, dan `.env` berada di luar document root pada deployment yang
benar.

`public/router.php` hanya untuk PHP built-in server. Router menolak NUL byte,
traversal, dan dotfile; file statis hanya dilayani bila benar-benar berada di
`public/` dan ekstensinya termasuk daftar MIME yang dikenal. Selain itu request
diteruskan ke `public/index.php`.

### Bootstrap aplikasi

`bootstrap/app.php` hanya mengembalikan:

```php
['db' => new PDO(...)]
```

Opsi PDO: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, dan `EMULATE_PREPARES = false`.
`app/bootstrap.php` menetapkan zona waktu `Asia/Jakarta`, membuat session, service,
model, controller, dan memuat route. Tidak ada dependency injection otomatis;
setiap controller dirakit manual.

## Konfigurasi database dan environment loader

Urutan prioritas nilai koneksi:

1. Environment proses yang sudah ada (`getenv()`).
2. `.env` di root repository, hanya untuk key yang belum ada di environment.
3. `config/database.local.php`, bila file ada dan mengembalikan array.
4. Default di `config/database.php`.

### Loader `.env`

`config/database.php` membaca file `.env` baris per baris. Perilaku aktual:

| Input | Proses | Output |
|---|---|---|
| Baris kosong atau diawali `#` | Dilewati | Tidak ada perubahan |
| Baris tanpa `=` | Dilewati | Tidak ada perubahan |
| Key tidak cocok `^[A-Za-z_][A-Za-z0-9_]*$` | Dilewati | Tidak ada perubahan |
| Key sudah ada di environment | Dilewati | Environment asli menang |
| Value dibungkus kutip tunggal/ganda yang berpasangan | Kutip luar dilepas | Value dimasukkan tanpa kutip |
| Value lain | Di-`trim()` | Value dimasukkan |

Loader memakai `putenv()`. Loader tidak melakukan interpolasi shell, tidak
menjalankan perintah, dan tidak menimpa environment yang sudah diekspor. File `.env`
tidak di-commit; isi nyata tidak boleh dicetak ke dokumentasi atau log.

`config/database.local.php` adalah alternatif lokal dan juga tidak di-commit. File
ini mengembalikan array dengan key `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan
`DB_PASSWORD`.

### Nilai koneksi

`config/database.php` mengembalikan `dsn`, `username`, dan `password`:

```text
mysql:host=<DB_HOST>;port=<DB_PORT>;dbname=<DB_NAME>;charset=utf8mb4
```

Default bila tidak ada sumber lain: host `127.0.0.1`, port `3306`, database
`sparta`, user `sparta`, password kosong. Default ini untuk demo lokal; deployment
harus memberi kredensial terpisah dan tidak memakai akun root database.

Closure internal `$env(string $key, string $default)` menerima nama key dan default,
memeriksa environment lalu `database.local.php`, dan mengembalikan string. Password
kosong tetap dianggap nilai yang valid, bukan “ambil default”.

## Schema database

Semua tabel menggunakan InnoDB, `utf8mb4`, dan `utf8mb4_unicode_ci`. Schema bersifat
non-destruktif: hanya `CREATE TABLE IF NOT EXISTS`, tanpa `DROP DATABASE` atau
`DROP TABLE`.

| Tabel | Kolom penting | Constraint dan relasi |
|---|---|---|
| `users` | `id`, `name`, `username`, `password_hash`, `role`, `is_active`, `created_at` | PK `id`; unique `username`; role enum `admin`/`petugas`/`peminjam`; index role dan status aktif |
| `kategori` | `id`, `nama`, `deskripsi`, `created_at` | PK `id`; unique `nama` |
| `alat` | `id`, `kode`, `kategori_id`, `nama`, `deskripsi`, `foto`, `stok_total`, `stok_tersedia`, `is_active`, timestamp | Unique `kode`; FK kategori `RESTRICT`; `CHECK stok_tersedia <= stok_total`; foto nullable |
| `peminjaman` | `id`, `kode`, `user_id`, `alat_id`, `jumlah`, tanggal pinjam/jatuh tempo, `status`, `catatan`, `approved_by`, `approved_at`, timestamp | Unique `kode`; FK user/alat `RESTRICT`; `approved_by` `SET NULL`; `jumlah > 0`; jatuh tempo tidak sebelum tanggal pinjam; status enum lima nilai |
| `pengembalian` | `id`, `peminjaman_id`, `tanggal_pengajuan`, `tanggal_diterima`, `jumlah`, `kondisi`, `hari_terlambat`, `denda`, `verified_by` | Satu pengembalian per peminjaman (unique); FK peminjaman `RESTRICT`; `verified_by` `SET NULL`; jumlah > 0; denda >= 0; kondisi enum `baik`/`rusak_ringan`/`rusak_berat`/`hilang` |
| `pengaturan` | `kunci`, `nilai`, `updated_at` | PK `kunci`; key/value; saat ini menyimpan `tarif_denda` |
| `activity_logs` | `id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `created_at` | FK user `SET NULL`; index user, waktu, dan entitas; tidak ada unique pada entitas |
| `login_attempts` | `username_hash`, `attempts`, `window_start`, `blocked_until`, `updated_at` | PK hash username; index `updated_at`; satu baris per akun untuk throttle login |

Jumlah foreign key pada schema saat ini: tujuh. Perilaku `ON DELETE`:

- `alat.kategori_id` → `RESTRICT`;
- `peminjaman.user_id` dan `peminjaman.alat_id` → `RESTRICT`;
- `peminjaman.approved_by` → `SET NULL`;
- `pengembalian.peminjaman_id` → `RESTRICT`;
- `pengembalian.verified_by` → `SET NULL`;
- `activity_logs.user_id` → `SET NULL`.

Stok tersedia tidak dipulihkan untuk semua kondisi pengembalian. Hanya kondisi
`baik` yang menambah `stok_tersedia`; `rusak_ringan`, `rusak_berat`, dan `hilang`
tidak menambah stok. Aturan ini berada di model `Pengembalian`, bukan constraint
database.

Denda disimpan sebagai `DECIMAL(12,2)`. Tarif denda disimpan sebagai string pada
`pengaturan.nilai` dan dibaca sebagai integer oleh model. Baris `pengembalian.denda`
bersifat historis: perubahan tarif tidak memperbarui baris lama.

`login_attempts.username_hash` menyimpan hash nama akun, bukan password. Tabel ini
terpisah dari audit dan tidak memiliki foreign key ke `users`, sehingga pencatatan
percobaan login tidak membutuhkan akun yang valid.

## Migrasi

Tidak ada tabel pelacak migrasi dan tidak ada runner otomatis. Migrasi diterapkan
manual, berurutan, ke database yang sudah ada. Status penerapan tidak dapat
disimpulkan dari repository; periksa database target sebelum menjalankan.

| File | Perubahan | Sifat |
|---|---|---|
| [`001_add_alat_foto.sql`](../../database/migrations/001_add_alat_foto.sql) | `ALTER TABLE alat ADD COLUMN foto VARCHAR(255) NULL AFTER deskripsi` | Satu kali; `ADD COLUMN` akan gagal bila kolom sudah ada |
| [`002_add_pengaturan.sql`](../../database/migrations/002_add_pengaturan.sql) | `CREATE TABLE IF NOT EXISTS pengaturan`; seed `tarif_denda = '0'` bila belum ada | Dapat dijalankan ulang; nilai yang sudah ada tidak ditimpa |
| [`003_add_login_attempts.sql`](../../database/migrations/003_add_login_attempts.sql) | `CREATE TABLE IF NOT EXISTS login_attempts` | Dapat dijalankan ulang |

`database/schema.sql` sudah memuat struktur hasil ketiga migrasi: kolom `foto`,
tabel `pengaturan`, dan tabel `login_attempts`. Instalasi baru tidak perlu
menjalankan migrasi untuk struktur tersebut. Schema tidak menyisipkan baris
`tarif_denda` awal seperti migrasi 002; model menganggap nilai yang tidak ada
sebagai `0`, dan baris baru terbentuk saat Admin menyimpan tarif. Migrasi hanya
untuk database lama yang dibuat sebelum kolom atau tabel tersebut ada. Backup
database sebelum migrasi; tidak ada skrip rollback.

Docker **tidak** memasang folder migrasi ke init container. Aplikasi juga tidak
menjalankannya saat start. Karena itu jangan menganggap migrasi sudah diterapkan
hanya karena container berjalan.

## Routine SQL

`database/routines.sql` harus diimpor setelah schema. File memakai `DELIMITER //`,
sehingga klien harus mendukung delimiter atau menjalankannya melalui klien
`mariadb`/`mysql`. Import ulang aman karena semua objek memakai
`CREATE OR REPLACE`.

### Function `fn_hitung_denda`

```sql
fn_hitung_denda(
    p_tanggal_jatuh_tempo DATE,
    p_tanggal_kembali DATE,
    p_jumlah INT,
    p_tarif DECIMAL(12, 2)
) RETURNS DECIMAL(12, 2)
DETERMINISTIC
```

| Aspek | Uraian |
|---|---|
| Input | Tanggal jatuh tempo, tanggal kembali, jumlah unit, tarif per hari per unit |
| Proses | `GREATEST(0, DATEDIFF(kembali, jatuh_tempo)) * GREATEST(0, jumlah) * GREATEST(0, tarif)` |
| Output | Denda `DECIMAL(12,2)`; `0` bila tidak terlambat atau tarif `0` |
| Satuan hari | Hari kalender melalui `DATEDIFF`, bukan selisih detik |
| Validasi | Nilai negatif pada jumlah/tarif dibatasi ke `0` oleh `GREATEST` |
| Penggunaan | Dipanggil model `Pengembalian` saat verifikasi dan koreksi; nominal hasil disimpan ke baris pengembalian |

Function ini tidak membaca tabel dan tidak menyimpan histori. Histori ada pada
`pengembalian.denda`.

### Procedure `sp_rekap_peminjaman`

```sql
sp_rekap_peminjaman(
    IN p_tanggal_awal DATE,
    IN p_tanggal_akhir DATE
)
```

| Aspek | Uraian |
|---|---|
| Input | Rentang tanggal pinjam |
| Proses | `SELECT` peminjaman dengan join user, alat, kategori, dan left join pengembalian; filter `tanggal_pinjam BETWEEN` inklusif |
| Output | Result set `kode`, `peminjam`, `alat`, `kategori`, `jumlah`, `tanggal_pinjam`, `tanggal_jatuh_tempo`, `status`, `denda`; urut tanggal pinjam dan ID |
| Batas | Tidak ada filter status, pagination, batas baris, atau ringkasan |
| Penggunaan | Procedure tersedia untuk demonstrasi `CALL`/pemeriksaan database. Halaman laporan aplikasi memakai query di `Laporan::rekap()` dengan filter status dan batas 1.000 baris |

Procedure tidak dipanggil oleh controller laporan. Perbedaan hasil dengan halaman
laporan berasal dari filter dan batas tambahan pada query aplikasi.

### Trigger `trg_peminjaman_audit_update`

```sql
trg_peminjaman_audit_update
AFTER UPDATE ON peminjaman
FOR EACH ROW
```

| Aspek | Uraian |
|---|---|
| Input | Baris `OLD`/`NEW` dan variabel session `@sparta_actor_id` |
| Proses | Bila `OLD.status <> NEW.status`, insert ke `activity_logs` |
| Output | `action = 'status_change'`, `entity_type = 'peminjaman'`, `entity_id = NEW.id`, deskripsi `Status <lama> -> <baru>` |
| Aktor | `user_id = @sparta_actor_id`; `NULL` berarti sistem/tidak diketahui |
| Cakupan | Hanya perubahan status; perubahan kolom lain tidak dicatat |
| Transaksi | Insert berjalan dalam transaksi update; kegagalan insert membatalkan update |

Aplikasi tidak menulis baris `status_change` sendiri. Alur approval dan pengembalian
mengisi `@sparta_actor_id` sebelum update dan mengosongkannya setelah selesai.

## Seeder

### `seed.php` — lokal/CLI

- Menolak akses non-CLI dengan HTTP `404` dan `exit`, sehingga tidak dapat dipicu
  lewat web walau document root salah.
- Memuat `bootstrap/app.php` dan memakai PDO yang sama dengan aplikasi.
- Membuat akun demo `admin`, `petugas`, dan `peminjam` dengan `password_hash()`
  runtime.
- Membuat kategori `Alat Tangan` dan alat `HT-001` bila belum ada.
- Memeriksa keberadaan berdasarkan unique key sebelum insert; baris yang sudah ada
  dilewati. Menjalankan ulang tidak menimpa akun atau data nyata.
- Seluruh pembuatan dibungkus transaksi dan mencetak status `create`/`skip` ke
  STDOUT.

### `seed.sql` — init container

- Berisi tiga akun demo dengan hash bcrypt statis, satu kategori, dan satu alat.
- Akun memakai `INSERT IGNORE`; alat memakai `INSERT ... SELECT ... WHERE NOT EXISTS`.
- Dijalankan otomatis oleh image MariaDB hanya saat volume data pertama kali
  diinisialisasi.
- Hash statis setara untuk demo, tetapi bukan pengganti `seed.php` bila password
  demo diubah. Jangan memakai akun demo untuk produksi.

Tidak ada password produksi, kredensial `.env`, atau token pada kedua seeder.

## Menjalankan lokal

Prasyarat runtime: PHP 8.2 atau lebih baru dengan PDO MySQL, MariaDB/InnoDB, serta
Node.js hanya untuk membangun CSS.

```sh
cp .env.example .env
# isi kredensial DB pada .env; jangan commit file tersebut
php database/seeders/seed.php
npm ci
npm run build
php -S 127.0.0.1:8000 -t public public/router.php
```

Instalasi database baru:

```sh
mariadb -u <user> -p -e "CREATE DATABASE IF NOT EXISTS sparta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mariadb -u <user> -p sparta < database/schema.sql
mariadb -u <user> -p sparta < database/routines.sql
```

Gunakan akun database khusus aplikasi, bukan root. `npm run build` menghasilkan
`public/assets/css/app.css`; aset runtime tidak memakai CDN. PHP built-in server
bukan server produksi.

## Docker: build dan run

### Image

`Dockerfile` memakai `alpine:3.24`, memasang nginx, PHP 8.5 FPM, dan ekstensi
`ctype`, `fileinfo`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, serta `session`.
Versi paket PHP di-pin melalui `ARG PHP_APK_VERSION`; komentar image menyebut patch
keamanan yang menjadi alasan pin. Seluruh source disalin ke `/var/www/html`, lalu
konfigurasi nginx/PHP dan entrypoint dipasang.

Direktori upload dan session dibuat saat build/entrypoint. `EXPOSE 80`.
`ENTRYPOINT` menjalankan `/entrypoint.sh`.

### Compose

| Service | Detail |
|---|---|
| `app` | Build dari `Dockerfile`; port host `${APP_PORT:-8080}` ke port 80; `DB_HOST=db`; volume upload alat dan session |
| `db` | `mariadb:11.4`; port host hanya `127.0.0.1:${DB_PUBLISH_PORT:-3307}`; volume data; healthcheck |
| Init DB | `schema.sql` → `routines.sql` → `seed.sql` dipasang read-only ke `/docker-entrypoint-initdb.d` |
| Dependency | `app` menunggu `db` sehat |
| Restart | `unless-stopped` untuk kedua service |

Variabel `DB_NAME`, `DB_USER`, `DB_PASSWORD` diambil dari `.env` melalui interpolasi
Compose. `DB_HOST` dipaksa `db` agar aplikasi memakai jaringan container. Nilai root
database juga berasal dari environment Compose; nilai nyata tidak dicantumkan di
dokumen ini. `.env` dikecualikan dari build context oleh `.dockerignore`, tetapi
tetap dibaca Compose di host untuk interpolasi.

Perintah operasional:

```sh
docker compose build
docker compose up -d
docker compose logs -f app
docker compose ps
```

Hentikan tanpa menghapus data:

```sh
docker compose down
```

`docker compose down -v` menghapus volume database dan upload. Jangan jalankan pada
data yang perlu dipertahankan.

### Entrypoint

`docker/entrypoint.sh` memakai `set -eu`, memastikan direktori
`/var/www/html/public/uploads/alat` dan `/var/lib/php/sessions` ada, memberi
kepemilikan kepada `nobody`, menjalankan `php-fpm85 -D`, lalu
`exec nginx -g 'daemon off;'`. Nginx menjadi proses utama container.

### Init database container

Skrip init hanya dijalankan pada volume data kosong. Restart container dengan
volume yang sudah berisi database tidak mengulang `schema.sql`, `routines.sql`,
atau `seed.sql`. Folder migrasi tidak dipasang. Untuk database container yang sudah
ada, terapkan migrasi manual dari host atau shell database, lalu verifikasi.

## Konfigurasi keamanan deployment

### Document root dan nginx

- Root nginx: `/var/www/html/public`; source aplikasi di luar root web.
- `location /` memakai `try_files` ke front controller.
- Handler PHP hanya untuk URI berakhiran `.php` dan file harus ada.
- Dotfile ditolak dengan `location ~ /\.(?!well-known)`.
- `location ^~ /uploads/` melayani file upload sebagai statis. Di dalamnya ada
  regex yang menolak ekstensi `php*`, `phtml`, `phar`, `cgi`, `pl`, `py`, `sh`,
  `shtml`, dan `htaccess`.
- Regex nested tersebut harus berada di dalam blok `^~`. Bila dipindah atau
  dilemahkan, prefiks `^~` dapat mengalahkan handler PHP global dan file upload
  berbahaya berisiko dieksekusi atau source-nya terbaca.
- Aset statis CSS/JS/gambar/font diberi cache tujuh hari.
- Batas body nginx `8m`.

### PHP dan FPM

`docker/php/php.ini` mematikan `expose_php` dan `display_errors`, menyalakan
`log_errors`, membatasi memori `128M`, upload `2M`, `post_max_size` `8M`, maksimal
lima file upload, serta menetapkan `Asia/Jakarta`. Cookie session HttpOnly dan
strict mode aktif; session disimpan di `/var/lib/php/sessions` yang merupakan
volume.

`docker/php/www.conf` menjalankan worker sebagai `nobody`, listen
`127.0.0.1:9000`, `pm=dynamic`, maksimal 10 child, dan mengirim error ke stderr.
`clear_env=no` membuat environment container (termasuk `DB_*`) tersedia untuk PHP.

### Aplikasi

Autentikasi, role, dan CSRF diperiksa terpusat di `App\Core\App`; route privat
kecuali login. POST tanpa `_token` valid ditolak `403`. Header keamanan yang
dikirim: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
`Referrer-Policy: same-origin`, dan `Cache-Control: no-store`. Detail session dan
RBAC ada pada dokumen modul 01 dan 02.

Port database Compose di-bind ke `127.0.0.1` agar tidak terbuka ke jaringan. Tetap
gunakan firewall dan kredensial kuat pada deployment nyata.

## Pemetaan test

Test dijalankan sebagai file PHP mandiri, bukan melalui test runner. Exit `0`
lulus, `1` gagal, `2` skip karena database tidak tersedia atau tidak disposable.
Dokumen ini tidak menyatakan test sudah dijalankan.

| File | Cakupan yang diperiksa |
|---|---|
| [`tests/database_config_test.php`](../../tests/database_config_test.php) | Environment menang atas config, termasuk password kosong |
| [`tests/security_test.php`](../../tests/security_test.php) | Router, session, CSRF, helper tanpa database |
| [`tests/database/schema_test.php`](../../tests/database/schema_test.php) | Tabel/engine, enum, foreign key, check, unique, function, procedure, trigger, seeder idempoten |
| [`tests/http_auth_test.php`](../../tests/http_auth_test.php) | Login/logout, session, RBAC dasar lewat HTTP |
| [`tests/http_master_data_test.php`](../../tests/http_master_data_test.php) | CRUD kategori dan alat, audit terkait |
| [`tests/http_users_test.php`](../../tests/http_users_test.php) | CRUD/penonaktifan pengguna dan proteksi Admin |
| [`tests/http_loans_test.php`](../../tests/http_loans_test.php) | Katalog, pengajuan, approval, audit status |
| [`tests/http_returns_test.php`](../../tests/http_returns_test.php) | Pengajuan/verifikasi pengembalian, stok, denda |
| [`tests/http_backoffice_test.php`](../../tests/http_backoffice_test.php) | Koreksi transaksi Admin, audit, laporan, pengaturan |

Test HTTP membuat server PHP sementara sendiri, membutuhkan ekstensi `curl`, dan
menolak database non-disposable kecuali `SPARTA_ALLOW_NONDISPOSABLE_DB=1`. Fixture
memakai prefiks masing-masing dan hanya menghapus baris prefiks tersebut.

CI `.github/workflows/ci.yml` menjalankan MariaDB 11.4, mengimpor `schema.sql` dan
`routines.sql`, menjalankan `seed.php`, membuat `public/uploads/alat`, lalu
menjalankan seluruh test di atas berurutan. CI **tidak** menjalankan migrasi dan
tidak menguji rollback migrasi. Keberhasilan CI, bila dijalankan, bukan bukti bahwa
database deployment sudah dimigrasi.

## Batas dan catatan operasional

- Schema adalah sumber untuk instalasi baru; migrasi adalah jalur untuk database
  lama. Jangan menambahkan kolom ke schema lalu menganggap database lama otomatis
  berubah.
- Tidak ada pelacakan versi migrasi. Catat migrasi yang sudah diterapkan di
  lingkungan masing-masing.
- Inisialisasi Docker bersifat sekali per volume. Perubahan schema setelah volume
  dibuat memerlukan migrasi manual atau volume baru yang disengaja.
- `.env`, `config/database.local.php`, password, dan token tidak boleh masuk Git,
  image, log, atau dokumentasi.
- Tarif denda historis tidak dihitung ulang otomatis; hanya baris pengembalian yang
  diverifikasi atau dikoreksi ulang yang memakai tarif saat itu.
- Seed demo dan akun demo tidak untuk produksi.
