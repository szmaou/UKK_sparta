# 02 — Autentikasi

Dokumen ini menjelaskan login, logout, identitas berbasis sesi, dan pembatasan percobaan login gagal. Implementasi mengikuti kode pada working tree saat ini.

## 1. Tanggung jawab

- Menampilkan formulir login dan memproses kredensial.
- Memverifikasi kata sandi hanya terhadap akun aktif.
- Mencegah enumerasi akun melalui pesan error yang seragam dan biaya verifikasi bcrypt yang setara.
- Membatasi percobaan login gagal per username.
- Menyimpan hanya ID pengguna di sesi; data pengguna dan status aktif dibaca ulang dari database pada setiap request.
- Mencatat audit login dan logout.
- Menghapus sesi saat logout atau saat akun tidak lagi aktif.

## 2. Aktor

| Aktor | Kepentingan |
|---|---|
| Pengunjung | Membuka `GET /login` dan mengirim `POST /login`. |
| Pengguna aktif (`admin`, `petugas`, `peminjam`) | Masuk, lalu diarahkan ke dashboard sesuai peran. |
| Pengguna nonaktif | Tidak dapat masuk; akun diperlakukan sama seperti kredensial salah. |
| Sistem | Menjaga throttle, sesi, audit, dan redirect. |

## 3. Rute

| Rute | Metode | Handler | Proteksi |
|---|---|---|---|
| `/login` | GET | `AuthController::showLogin` | Publik. Pengguna yang sudah login langsung diarahkan ke dashboard. |
| `/login` | POST | `AuthController::login` | Publik, tetapi wajib CSRF dari front controller. |
| `/logout` | POST | `AuthController::logout` | Wajib login dan CSRF. |

Definisi route ada di [`app/routes.php`](../../app/routes.php). Pemeriksaan login, role, dan CSRF dilakukan terpusat di [`app/Core/App.php`](../../app/Core/App.php); lihat [01 — Core dan Keamanan](01-core-dan-keamanan.md).

## 4. Dependensi

- [`app/Controllers/AuthController.php`](../../app/Controllers/AuthController.php)
- [`app/Services/Auth.php`](../../app/Services/Auth.php)
- [`app/Models/LoginThrottle.php`](../../app/Models/LoginThrottle.php)
- [`app/Models/User.php`](../../app/Models/User.php) untuk `findActiveByUsername` dan `findActiveById`.
- [`app/Models/ActivityLog.php`](../../app/Models/ActivityLog.php) untuk audit `login` dan `logout`.
- [`app/Core/Session.php`](../../app/Core/Session.php) dan [`app/Core/View.php`](../../app/Core/View.php).
- Helper `dashboard_path()` dan `redirect()` dari [`app/helpers.php`](../../app/helpers.php).
- Tabel `users` dan `login_attempts` dari [`database/schema.sql`](../../database/schema.sql).

## 5. Alur proses

| Proses | Input | Langkah | Output | Validasi dan error |
|---|---|---|---|---|
| Menampilkan login | Request `GET /login` | `showLogin()` memeriksa sesi; bila sudah login, redirect. Bila belum, render view dengan token CSRF. | Halaman login status 200, atau redirect dashboard. | Pengguna yang sudah login tidak melihat formulir lagi. |
| Validasi input login | `$_POST['username']`, `$_POST['password']` | Username di-trim; password diambil apa adanya. Keduanya harus string. | Nilai siap diverifikasi. | Kosong, bukan string, username > 50 byte, atau password > 200 byte menghasilkan status 422 dengan pesan `Username dan kata sandi wajib diisi.` dan username lama dikembalikan ke formulir. |
| Memeriksa lockout | Username | `LoginThrottle::lockedFor()` membaca `blocked_until`. | Sisa detik atau 0. | Bila > 0, response 429, header `Retry-After`, dan pesan menit tersisa. Verifikasi kata sandi tidak dijalankan. |
| Memverifikasi kredensial | Username dan password | `Auth::attempt()` mencari akun aktif; bila tidak ada, memakai hash dummy; lalu `password_verify`. | Data pengguna atau `null`. | Akun tidak ada, nonaktif, atau password salah semuanya menghasilkan `null`. |
| Mencatat kegagalan | Username | `recordFailure()` menaikkan hitungan dalam transaksi dan baris terkunci. | Baris `login_attempts` diperbarui. | Response tetap 401 generik `Username atau kata sandi tidak sesuai.`; informasi keberadaan akun tidak dibocorkan. |
| Login berhasil | Data pengguna aktif | Hapus throttle, regenerasi sesi, simpan `user_id`, catat audit `login`, redirect. | Status 302 ke dashboard peran. | Redirect memakai `dashboard_path((string) $user['role'])`. |
| Logout | Request `POST /logout` dan sesi | Bila ada pengguna, catat audit `logout`; lalu hancurkan sesi dan redirect. | Status 302 ke `/login`. | CSRF wajib; tanpa CSRF front controller mengembalikan 403. |
| Membaca identitas | `$_SESSION['user_id']` | `Auth::user()` membaca ulang akun aktif dari database; hasil di-cache per objek. | Data pengguna atau `null`. | ID tidak valid menghasilkan `null`; akun hilang/nonaktif menghancurkan sesi. |

## 6. Semantik throttle login

Throttle disimpan di tabel `login_attempts`, satu baris per hash username. Alamat klien tidak dipakai karena PHP-FPM hanya melihat proxy lokal.

Struktur tabel: `username_hash CHAR(64)` sebagai primary key, `attempts SMALLINT UNSIGNED`, `window_start DATETIME`, `blocked_until DATETIME NULL`, dan `updated_at TIMESTAMP` yang diperbarui otomatis.

- `MAX_ATTEMPTS = 5`, `WINDOW_MINUTES = 15`.
- Kunci baris adalah `sha256(mb_strtolower(trim(username)))`.
- `lockedFor()` mengembalikan `max(0, strtotime(blocked_until) - time())`.
- `recordFailure()` membuka transaksi, membuat baris dengan `attempts = 0` bila belum ada, lalu membaca baris dengan `SELECT ... FOR UPDATE`.
- Bila `window_start` lebih lama dari 15 menit, hitungan dimulai ulang dari 1 dan `window_start` diperbarui.
- Bila belum kedaluwarsa, `attempts` dinaikkan satu.
- Saat `attempts >= 5`, `blocked_until` diisi `now + 15 menit`.
- Percobaan kelima masih menghasilkan 401 karena pemeriksaan lockout dilakukan sebelum verifikasi. Percobaan keenam menghasilkan 429.
- Username yang tidak ada diperlakukan sama dan ikut terkunci.
- Login berhasil menghapus baris throttle untuk username tersebut.
- Setiap kegagalan menjalankan pembersihan `DELETE FROM login_attempts WHERE updated_at < NOW() - INTERVAL 1 DAY LIMIT 200`.
- Baris lama dibersihkan berdasarkan `updated_at` database, sedangkan perhitungan window dan lockout memakai waktu PHP.

Konsekuensi yang sudah disadari kode: penyerang yang mengetahui sebuah username dapat mengunci akun itu selama 15 menit. Window pendek dipilih agar dampak denial lebih kecil daripada percobaan tebakan tanpa batas.

## 7. Validasi dan pesan error

| Kondisi | Status | Pesan atau perilaku |
|---|---|---|
| Username/password kosong atau terlalu panjang | 422 | `Username dan kata sandi wajib diisi.` |
| Throttle aktif | 429 | `Terlalu banyak percobaan masuk. Coba lagi dalam N menit.` dan header `Retry-After` berisi sisa detik. |
| Kredensial salah, akun nonaktif, atau username tidak ada | 401 | `Username atau kata sandi tidak sesuai.` |
| CSRF salah atau tidak ada | 403 | Ditangani front controller sebelum controller. |
| Sudah login lalu membuka `GET /login` | 302 | Redirect ke dashboard sesuai peran. |
| Username atau password bukan string | 422 | Dianggap kosong; tidak ada coercion menjadi string. |

Batas panjang login: username 50 byte, password 200 byte. Pembuatan akun memakai batas password yang berbeda; lihat [03 — Pengguna](03-pengguna.md).

## 8. Inventaris method

### [`app/Controllers/AuthController.php`](../../app/Controllers/AuthController.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `__construct(Auth $auth, Session $session, ActivityLog $logs, View $view, LoginThrottle $throttle)` | Layanan auth, sesi, audit, view, throttle | Objek controller | Menyimpan dependency. |
| `showLogin(?string $error = null, int $status = 200, string $oldUsername = ''): void` | Pesan error, status, username lama | HTML atau redirect | Redirect ke dashboard bila sudah login; selain itu merender `auth/login` dengan token CSRF. |
| `login(): void` | `$_POST['username']`, `$_POST['password']` | Redirect atau HTML error | Menjalankan validasi, lockout, verifikasi, pencatatan kegagalan, audit login, dan redirect. |
| `logout(): void` | Sesi aktif | Redirect | Mencatat audit logout bila pengguna ada, menghancurkan sesi, lalu redirect ke `/login`. |

### [`app/Services/Auth.php`](../../app/Services/Auth.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `__construct(Session $session, User $users)` | Sesi dan model pengguna | Objek `Auth` | Menyimpan dependency; status resolusi pengguna dimulai kosong. |
| `user(): ?array` | Sesi | Data pengguna aktif atau `null` | Membaca `user_id`, memvalidasi bentuk ID, membaca ulang akun aktif; bila tidak ditemukan, sesi dihancurkan. Hasil di-cache per request. |
| `attempt(string $username, string $password): ?array` | Username dan password | Data pengguna atau `null` | Mencari akun aktif; memakai `DUMMY_HASH` bila tidak ada agar biaya bcrypt setara; mengembalikan data hanya bila `password_verify` berhasil. |
| `login(array $user): void` | Data pengguna | Tidak ada | Meregenerasi ID sesi, menyimpan `user_id`, dan mengisi cache pengguna. |
| `logout(): void` | Tidak ada | Tidak ada | Menghancurkan sesi dan mengosongkan cache. |

Konstanta privat `DUMMY_HASH` berisi hash bcrypt untuk verifikasi palsu. Nilainya tidak dimuat di dokumen ini.

### [`app/Models/LoginThrottle.php`](../../app/Models/LoginThrottle.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `__construct(PDO $db)` | Koneksi PDO | Objek throttle | Menyimpan koneksi. |
| `lockedFor(string $username): int` | Username | Sisa detik | Membaca `blocked_until`; mengembalikan 0 bila tidak ada atau sudah lewat. |
| `recordFailure(string $username): void` | Username | Tidak ada | Menaikkan hitungan dalam transaksi dengan row lock, menetapkan blokir pada ambang 5, membersihkan baris lama, lalu commit. Exception di-rollback dan dilempar ulang. |
| `clear(string $username): void` | Username | Tidak ada | Menghapus baris throttle untuk hash username. |
| `key(string $username): string` (static) | Username | Hash SHA-256 | `hash('sha256', mb_strtolower(trim($username)))`. |

## 9. Pemetaan pengujian yang ada

Belum ada pengujian yang dijalankan untuk dokumen ini.

| Berkas pengujian | Cakupan yang terlihat dari isi berkas |
|---|---|
| [`tests/http_auth_test.php`](../../tests/http_auth_test.php) | Halaman login dan CSRF, cookie `HttpOnly`, redirect anonim, penolakan password salah, akun nonaktif, username tidak dikenal, login tiap peran, dashboard, dan throttle lima/keenam percobaan termasuk `Retry-After` serta pemulihan. |
| [`tests/security_test.php`](../../tests/security_test.php) | Kontrak sesi, CSRF, regenerasi, HTTPS, dummy hash bcrypt, route publik/privat, dan batas akses role. |
| [`tests/http_users_test.php`](../../tests/http_users_test.php) | Perilaku sesi setelah akun dinonaktifkan. |
| [`tests/database/schema_test.php`](../../tests/database/schema_test.php) | Struktur tabel `users` dan `login_attempts`, enum role, serta akun demo. |

## 10. Catatan dan risiko

- Throttle berbasis username, bukan alamat IP. Ini mencegah tebakan tanpa batas, tetapi membuka kemungkinan lockout yang disengaja.
- Pesan login gagal sengaja generik; jangan menambahkan pesan yang membedakan akun tidak ada, nonaktif, atau password salah.
- `Auth::user()` membaca database pada request pertama yang membutuhkan identitas. Nonaktifkan akun langsung memutus akses pada request berikutnya.
- Sesi tidak memiliki batas idle atau batas umur absolut di kode aplikasi.
- Logout memakai `POST` dan CSRF, sehingga tidak dapat dipicu lewat tautan `GET`.
- Jangan mencatat password, hash, token CSRF, atau ID sesi ke audit.
