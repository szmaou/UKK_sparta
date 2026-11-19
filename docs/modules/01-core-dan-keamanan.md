# 01 — Core dan Keamanan

Dokumen ini menjelaskan inti aplikasi yang dipakai semua modul: front controller, router, sesi, CSRF, otorisasi peran, helper, autoload, bootstrap, konfigurasi database, serta batas keamanan berkas unggahan. Isi mengikuti kode pada working tree saat ini.

## 1. Tanggung jawab

- Menjalankan satu alur masuk untuk seluruh request melalui `public/index.php`.
- Mencocokkan metode dan path request ke route.
- Menolak request yang tidak terautentikasi atau tidak memiliki peran yang sesuai.
- Memverifikasi CSRF untuk setiap request `POST`.
- Mengirim header keamanan dasar dan menangani error 403, 404, 405, dan 500.
- Menyediakan sesi native PHP dengan cookie `HttpOnly`, `SameSite=Lax`, dan `Secure` saat HTTPS terdeteksi.
- Menyediakan helper escape HTML, ikon allowlist, URL dashboard, dan redirect.
- Memuat kelas `App\...` tanpa Composer dan merakit dependency aplikasi secara manual.
- Menjaga berkas unggahan tetap disajikan sebagai berkas statis, bukan dieksekusi sebagai PHP.

## 2. Aktor

| Aktor | Kepentingan |
|---|---|
| Pengunjung | Hanya boleh membuka route publik, yaitu `GET /login` dan `POST /login`. |
| Admin | Route dengan role `['admin']`, termasuk master data dan transaksi. |
| Petugas | Route dengan role `['petugas']`, termasuk persetujuan, pengembalian, dan laporan. |
| Peminjam | Route dengan role `['peminjam']`, termasuk katalog dan peminjaman sendiri. |
| Semua pengguna aktif | Route privat dengan daftar role `null`; cukup terautentikasi. |
| Operator server | Menyediakan PHP, database, Nginx/PHP built-in server, dan direktori unggahan. |

## 3. Rute dan proteksi

Route didefinisikan di [`app/routes.php`](../../app/routes.php). Route privat adalah default; hanya route yang diberi `public: true` yang melewati pemeriksaan login.

| Rute | Metode | Handler | Proteksi |
|---|---|---|---|
| `/login` | GET | `AuthController::showLogin` | Publik. |
| `/login` | POST | `AuthController::login` | Publik, tetapi tetap wajib CSRF. |
| `/logout` | POST | `AuthController::logout` | Wajib login dan CSRF. |
| `/` | GET | `DashboardController::home` | Wajib login; mengarahkan ke dashboard sesuai peran. |
| `/{role}/dashboard` | GET | `DashboardController::dashboard` | Role harus sama dengan segmen `{role}`. |
| `/peminjam/...` | GET/POST | Controller peminjam | Role `peminjam`. |
| `/petugas/...` | GET/POST | Controller petugas | Role `petugas`. |
| `/admin/...` | GET/POST | Controller admin | Role `admin`. |

Route modul dijelaskan lebih rinci di [02 — Autentikasi](02-autentikasi.md), [03 — Pengguna](03-pengguna.md), [04 — Kategori](04-kategori.md), dan [05 — Alat](05-alat.md).

## 4. Dependensi

- PHP 8.2+ dengan ekstensi sesi, PDO MySQL, `fileinfo`, dan `mbstring`.
- MariaDB/MySQL melalui PDO.
- `$_SERVER`, `$_SESSION`, `$_POST`, `$_FILES`, dan `header()` bawaan PHP.
- [`bootstrap/app.php`](../../bootstrap/app.php) untuk koneksi PDO.
- [`config/database.php`](../../config/database.php) untuk konfigurasi koneksi.
- [`docker/nginx/default.conf`](../../docker/nginx/default.conf) dan [`public/router.php`](../../public/router.php) untuk batas penyajian berkas.

## 5. Alur proses

| Proses | Input | Langkah | Output | Validasi dan error |
|---|---|---|---|---|
| Masuk aplikasi | Request HTTP | `public/index.php` memuat `app/bootstrap.php`, membuat `App`, lalu memanggil `run()`. | Response HTML atau redirect. | Kegagalan boot ditangkap, dicatat ke `error_log`, lalu dirender sebagai 500. |
| Memulai sesi | Request | `Session::start()` mengaktifkan strict mode, cookie-only, lalu `session_start()`. | Sesi aktif dan cookie `SPARTASESS`. | Jika sesi sudah aktif, method berhenti lebih awal. |
| Menyiapkan header | Request | `sendSecurityHeaders()` mengirim `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, dan `Cache-Control`. | Header keamanan. | Tidak mengirim header bila `headers_sent()` sudah benar. |
| Mencocokkan route | Metode dan path | `Router::match()` mencocokkan regex route dan mendekode parameter. | Handler, parameter, status auth, dan daftar role. | Path tidak dikenal menjadi 404; path dikenal dengan metode salah menjadi 405 dan header `Allow`. |
| Memeriksa login | Route dan sesi | `Auth::user()` membaca `user_id`, lalu membaca ulang akun aktif dari database. | Data pengguna atau `null`. | Akun nonaktif atau hilang menghancurkan sesi dan menghasilkan `null`. |
| Memeriksa role | Route dan pengguna | Front controller membandingkan `$user['role']` dengan daftar role route. | Lanjut ke handler atau 403. | `null` berarti semua pengguna terautentikasi boleh masuk. |
| Memeriksa CSRF | `$_POST['_token']` | `Session::verifyCsrf()` membandingkan token dengan `hash_equals`. | Lanjut ke handler atau 403. | Hanya berlaku untuk `POST`; token salah, kosong, atau bukan string ditolak. |
| Menjalankan handler | Handler dan parameter | `call_user_func_array()` memanggil handler dengan nilai parameter route. | Response dari controller. | Exception ditangkap, dicatat, lalu menjadi 500 tanpa detail internal ke pengguna. |
| Menyajikan unggahan | Path `/uploads/...` | Nginx menyajikan berkas statis; PHP built-in server hanya menyajikan ekstensi yang diizinkan. | Berkas gambar atau 404. | Ekstensi executable di `/uploads/` ditolak oleh Nginx; router built-in mengalihkan ekstensi tak dikenal ke front controller. |

## 6. Validasi dan pesan error

| Kondisi | Status | Perilaku |
|---|---|---|
| Route tidak ditemukan | 404 | `ErrorController::notFound()` merender `errors/404`. |
| Path ada, metode tidak didukung | 405 | Header `Allow` berisi metode yang tersedia; body `Method not allowed.` |
| Belum login pada route privat | 302 | Redirect ke `/login`. |
| Role tidak cocok | 403 | `ErrorController::forbidden()` merender `errors/403`. |
| CSRF tidak valid | 403 | Request tidak pernah mencapai controller. |
| Exception saat handler berjalan | 500 | Detail hanya masuk `error_log`; pengguna melihat halaman 500. |

Catatan: pengecekan role dilakukan sebelum pengecekan CSRF. Request `POST` publik tetap melewati pengecekan CSRF.

## 7. Inventaris method dan fungsi

### [`app/Core/App.php`](../../app/Core/App.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `__construct(Router $router, Session $session, Auth $auth, ErrorController $errors)` | Router, sesi, layanan auth, controller error | Objek `App` | Menyimpan dependency untuk front controller. |
| `run(): void` | Tidak ada; membaca superglobal | Response HTTP | Menjalankan sesi, header, pencocokan route, auth, role, CSRF, handler, dan penanganan exception. |
| `sendSecurityHeaders(): void` (private) | Tidak ada | Header HTTP | Mengirim empat header keamanan bila header belum terkirim. |

### [`app/Core/Router.php`](../../app/Core/Router.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `get(string $path, callable\|array $handler, ?array $roles = null, bool $public = false): void` | Path, handler, role, status publik | Tidak ada | Mendaftarkan route `GET`. |
| `post(string $path, callable\|array $handler, ?array $roles = null, bool $public = false): void` | Path, handler, role, status publik | Tidak ada | Mendaftarkan route `POST`. |
| `add(string $method, string $path, callable\|array $handler, ?array $roles = null, bool $public = false): void` | Metode, path, handler, role, status publik | Tidak ada | Menormalisasi path, mengubah `{nama}` menjadi regex `([^/]+)`, menyimpan metadata route. |
| `match(string $method, string $path): ?array` | Metode dan path | Handler, parameter, `auth`, `roles`; atau `null` | Mencari route pertama yang cocok dan mendekode parameter dengan `rawurldecode`. |
| `allowedMethods(string $path): array` | Path | Daftar metode unik | Mengumpulkan metode untuk path yang cocok; dipakai untuk response 405. |
| `normalize(string $path): string` (private) | Path | Path dengan slash awal dan tanpa slash akhir | Path `/` tetap `/`. |

Closure internal `preg_replace_callback` pada `add()` mengumpulkan nama parameter; bukan method publik.

### [`app/Core/Session.php`](../../app/Core/Session.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `start(): void` (static) | Tidak ada | Sesi aktif | Mengatur `session.use_strict_mode`, `session.use_only_cookies`, `session.cookie_httponly`, nama `SPARTASESS`, lalu `session_start()`. |
| `isHttps(): bool` (static) | Tidak ada | `true`/`false` | Hanya membaca `$_SERVER['HTTPS']`; nilai kosong atau `off` dianggap HTTP. |
| `get(string $key, mixed $default = null): mixed` | Kunci dan default | Nilai sesi atau default | Membaca `$_SESSION[$key]`. |
| `set(string $key, mixed $value): void` | Kunci dan nilai | Tidak ada | Menulis `$_SESSION[$key]`. |
| `forget(string $key): void` | Kunci | Tidak ada | Menghapus satu kunci sesi. |
| `regenerate(): void` | Tidak ada | ID sesi baru | `session_regenerate_id(true)` bila sesi aktif, lalu menghapus token CSRF lama. |
| `csrfToken(): string` | Tidak ada | Token 64 karakter hex | Membuat `random_bytes(32)` bila belum ada; token disimpan di sesi. |
| `verifyCsrf(mixed $token): bool` | Token dari request | `true`/`false` | Memastikan token dan nilai sesi string tidak kosong, lalu `hash_equals`. |
| `destroy(): void` | Tidak ada | Sesi dihapus | Mengosongkan `$_SESSION`, mengirim cookie kedaluwarsa, lalu `session_destroy()`. |

Konstanta: `NAME = 'SPARTASESS'`, `CSRF_KEY = '_csrf'`.

### [`app/Core/View.php`](../../app/Core/View.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `__construct(string $directory)` | Direktori view | Objek `View` | Menyimpan direktori template. |
| `render(string $template, array $data = [], int $status = 200): void` | Nama template, data, status | Response HTML | Menetapkan status, menggabungkan kontrak default (`user`, `csrfToken`, `error`, `oldUsername`, `role`), `extract(..., EXTR_SKIP)`, lalu `require` template. |

### [`app/helpers.php`](../../app/helpers.php)

| Fungsi | Input | Output | Perilaku aktual |
|---|---|---|---|
| `e(mixed $value): string` | Nilai apa pun | String HTML aman | `htmlspecialchars` dengan `ENT_QUOTES \| ENT_SUBSTITUTE` dan UTF-8. |
| `icon(string $name, string $classes = ''): string` | Nama ikon dan kelas | Potongan SVG atau string kosong | Nama di-lowercase, harus cocok `^[a-z0-9-]+$`, harus ada di `METADATA.json`, lalu SVG dibaca dari `public/assets/icons/lucide/`. Kelas dibersihkan dari karakter selain huruf, angka, spasi, `_`, dan `-`. |
| `dashboard_path(string $role): string` | Nama role | Path dashboard | Role `admin`, `petugas`, `peminjam` dipakai langsung; nilai lain menjadi `/peminjam/dashboard`. |
| `redirect(string $to): never` | URL tujuan | Tidak kembali | Mengirim `Location` dengan status 302 lalu `exit`. |

### [`app/Controllers/ErrorController.php`](../../app/Controllers/ErrorController.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `__construct(Auth $auth, Session $session, View $view)` | Auth, sesi, view | Objek controller | Menyimpan dependency untuk halaman error. |
| `forbidden(): void` | Tidak ada | HTML 403 | Merender `errors/403`. |
| `serverError(): void` | Tidak ada | HTML 500 | Merender `errors/500`. |
| `notFound(): void` | Tidak ada | HTML 404 | Merender `errors/404`. |
| `contract(): array` (private) | Tidak ada | Data view | Mengambil pengguna dan token CSRF untuk kontrak view error. |

### Autoload, bootstrap, dan konfigurasi

| Berkas | Input | Output | Perilaku aktual |
|---|---|---|---|
| [`app/autoload.php`](../../app/autoload.php) | Nama kelas | Berkas kelas dimuat | Mendaftarkan `spl_autoload_register`; `App\Foo\Bar` dipetakan ke `app/Foo/Bar.php`; selalu memuat `app/helpers.php`. |
| [`app/bootstrap.php`](../../app/bootstrap.php) | Tidak ada | Array container dan router | Menetapkan zona waktu `Asia/Jakarta`, memuat `bootstrap/app.php`, merakit PDO, model, service, controller, dan route secara manual. |
| [`bootstrap/app.php`](../../bootstrap/app.php) | Konfigurasi database | `['db' => PDO]` | Membuat PDO dengan `ERRMODE_EXCEPTION`, fetch asosiatif, dan `EMULATE_PREPARES = false`. |
| [`config/database.php`](../../config/database.php) | Environment dan file konfigurasi lokal | DSN, username, password | Mengisi `DB_*` yang belum ada dari `.env`; environment nyata menang. Lalu membaca `config/database.local.php` bila ada. Tidak menampilkan nilai rahasia. |
| [`public/index.php`](../../public/index.php) | Request | Response | Satu-satunya entrypoint web; menangkap kegagalan boot dan merender 500. |
| [`public/router.php`](../../public/router.php) | Request untuk PHP built-in server | Berkas statis atau front controller | Menolak NUL, traversal, dan dotfile; hanya menyajikan ekstensi `css`, `js`, `json`, `svg`, `png`, `jpg`, `jpeg`, `webp`, `ico`, `woff`, dan `woff2`. |

Perilaku `config/database.php` secara rinci: file `.env` dibaca baris per baris; baris kosong, komentar, dan baris tanpa `=` dilewati; key harus cocok `^[A-Za-z_][A-Za-z0-9_]*$`; key yang sudah ada di environment tidak ditimpa; nilai yang diapit tanda kutip tunggal atau ganda dilepas. Setelah itu `config/database.local.php` dipakai sebagai fallback. `bootstrap/app.php` selalu membaca hasil konfigurasi ini saat membuat PDO.

## 8. Batas keamanan unggahan

Detail unggahan ada di [05 — Alat](05-alat.md). Batas infrastruktur yang berlaku:

- Nginx memakai `root /var/www/html/public` dan `client_max_body_size 8m`.
- `location ^~ /uploads/` selalu mencoba berkas statis dan tidak meneruskan ke PHP-FPM.
- Regex bersarang di dalam `location ^~ /uploads/` menolak `php*`, `phtml`, `phar`, `cgi`, `pl`, `py`, `sh`, `shtml`, dan `htaccess`.
- Nginx hanya mengirim permintaan `*.php` ke PHP-FPM melalui handler global. Regex bersarang di blok unggahan menolak ekstensi executable dan harus dipertahankan.
- Pada perintah dev server proyek (`php -S ... public/router.php`), `/uploads/` tidak memiliki handler PHP; ekstensi di luar allowlist dialihkan ke front controller.

## 9. Pemetaan pengujian yang ada

Belum ada pengujian yang dijalankan untuk dokumen ini.

| Berkas pengujian | Cakupan yang terlihat dari isi berkas |
|---|---|
| [`tests/security_test.php`](../../tests/security_test.php) | Pencocokan router, normalisasi path, parameter, route publik/privat, helper `e`, `icon`, `dashboard_path`, cookie sesi, CSRF, regenerasi sesi, HTTPS, dummy hash bcrypt, keberadaan view. |
| [`tests/database_config_test.php`](../../tests/database_config_test.php) | Prioritas environment pada konfigurasi database dan kontrak bootstrap PDO. |
| [`tests/database/schema_test.php`](../../tests/database/schema_test.php) | Koneksi PDO, tabel InnoDB, enum role, foreign key, unique constraint, check stok, fungsi denda, trigger audit, dan seed. |
| [`tests/http_auth_test.php`](../../tests/http_auth_test.php) | Redirect login, CSRF, cookie, login per role, dan pembatasan dashboard. |

## 10. Catatan dan risiko

- Deteksi HTTPS hanya memakai `$_SERVER['HTTPS']`. Di belakang TLS-terminating proxy, nilai itu harus diisi oleh web server; kode tidak membaca `X-Forwarded-Proto`.
- Tidak ada header CSP atau HSTS di aplikasi.
- CSRF hanya diperiksa pada `POST`, bukan `PUT`, `PATCH`, atau `DELETE`; saat ini router hanya mendaftarkan `GET` dan `POST`.
- `Cache-Control: no-store` dikirim global, termasuk untuk aset yang disajikan lewat front controller.
- Pesan exception tidak ditampilkan ke pengguna; hanya status 500 dan halaman umum.
- Konfigurasi lokal dan `.env` tidak boleh dicetak atau di-commit.
