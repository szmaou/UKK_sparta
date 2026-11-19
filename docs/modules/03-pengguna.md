# 03 — Pengguna

Dokumen ini menjelaskan pengelolaan akun pengguna oleh Admin. Akun tidak dihapus permanen; penonaktifan dipakai agar riwayat peminjaman, pengembalian, dan audit tetap utuh.

## 1. Tanggung jawab

- Menampilkan daftar pengguna dengan pencarian dan pagination.
- Menambah akun dengan kata sandi yang di-hash.
- Mengubah nama, username, role, status aktif, dan kata sandi secara opsional.
- Menonaktifkan akun tanpa menghapus baris `users`.
- Mencegah akun yang sedang dipakai mengubah role atau menonaktifkan dirinya sendiri.
- Mencegah admin aktif terakhir dihapus atau diturunkan.
- Mencatat setiap mutasi ke `activity_logs` tanpa menyimpan kata sandi.
- Menjaga aturan unik username dan integritas role.

## 2. Aktor

| Aktor | Kepentingan |
|---|---|
| Admin | Satu-satunya peran yang boleh membuka route `/admin/users`. |
| Pengguna (`admin`, `petugas`, `peminjam`) | Menjadi objek pengelolaan; akun nonaktif tidak dapat login. |
| Sistem | Menjalankan hash, transaksi, row lock, audit, dan validasi database. |

## 3. Rute

| Rute | Metode | Handler | Proteksi |
|---|---|---|---|
| `/admin/users` | GET | `UserController::index` | Role `admin`. |
| `/admin/users/create` | GET | `UserController::create` | Role `admin`. |
| `/admin/users/create` | POST | `UserController::store` | Role `admin` dan CSRF. |
| `/admin/users/{id}/edit` | GET | `UserController::edit` | Role `admin`. |
| `/admin/users/{id}/edit` | POST | `UserController::update` | Role `admin` dan CSRF. |
| `/admin/users/{id}/delete` | POST | `UserController::destroy` | Role `admin` dan CSRF. |

Route didaftarkan oleh loop master data di [`app/routes.php`](../../app/routes.php). Front controller di [`app/Core/App.php`](../../app/Core/App.php) menangani login, role, dan CSRF.

## 4. Dependensi

- [`app/Controllers/UserController.php`](../../app/Controllers/UserController.php)
- [`app/Models/User.php`](../../app/Models/User.php)
- [`app/Models/ActivityLog.php`](../../app/Models/ActivityLog.php)
- [`app/Services/Auth.php`](../../app/Services/Auth.php) untuk identitas admin yang sedang bertindak.
- [`app/Core/Session.php`](../../app/Core/Session.php) untuk flash message dan CSRF.
- [`app/Core/View.php`](../../app/Core/View.php)
- Tabel `users`, `activity_logs`, dan referensi `peminjaman`/`pengembalian` di [`database/schema.sql`](../../database/schema.sql).
- Konstanta `User::ROLES`, `User::PER_PAGE`, dan `User::MAX_PASSWORD_BYTES`.

## 5. Alur proses

| Proses | Input | Langkah | Output | Validasi dan error |
|---|---|---|---|---|
| Daftar pengguna | Query `q`, `page` | `index()` membaca pencarian, memvalidasi nomor halaman, memanggil `paginate()`, lalu merender daftar. | HTML daftar 10 baris per halaman. | `page` hanya diterima bila cocok `^[1-9]\d{0,6}$`; nilai lain menjadi halaman 1. Pencarian dipotong 100 karakter. Halaman di luar rentang menghasilkan tabel kosong, bukan error. |
| Form tambah | Tidak ada | `create()` merender nilai awal. | HTML form. | Default role `peminjam`, status `1`. |
| Tambah pengguna | `name`, `username`, `role`, `is_active`, `password` | `parse()` menormalkan input; bila valid, `create()` membuat hash, menyimpan baris, mencatat audit, dan commit. | Redirect 302 ke `/admin/users` dan flash sukses. | Error field menghasilkan 422 dan form diisi ulang tanpa password. Duplikat username menghasilkan 422. |
| Form ubah | `{id}` | `findOr404()` mencari baris; nilai diubah ke string untuk form. | HTML form atau 404. | ID harus angka positif 1–19 digit dan baris harus ada. |
| Ubah pengguna | `{id}` dan field form | `parse()` dengan ID; `update()` mengunci admin aktif dan baris target, memeriksa guard, memperbarui baris, mencatat audit, lalu commit. | Redirect 302 dan flash sukses, atau 422. | Password kosong mempertahankan hash lama. Password baru minimal 8 byte dan maksimal 72 byte. Pelanggaran guard menjadi 422. Baris hilang saat update menjadi 404. |
| Nonaktifkan pengguna | `{id}` | `findOr404()` lalu `delete()` mengunci baris, memeriksa guard, dan mengubah `is_active = 0`. | Redirect 302 ke daftar, atau kembali ke form dengan flash error. | Akun sendiri, admin aktif terakhir, dan ID yang hilang ditolak. Tidak ada `DELETE` fisik. |

## 6. Aturan validasi dan error

| Field atau kondisi | Aturan | Pesan atau hasil |
|---|---|---|
| `name` | Wajib, hasil trim, maksimal 100 karakter | `Nama pengguna wajib diisi.` / `Nama pengguna maksimal 100 karakter.` |
| `username` | Wajib, hasil trim, maksimal 50 karakter, unik | `Username wajib diisi.` / `Username maksimal 50 karakter.` / `Username sudah digunakan. Gunakan username lain.` |
| `password` saat tambah | Wajib, minimal 8 byte, maksimal 72 byte | `Password wajib diisi.` / `Password minimal 8 karakter.` / `Password maksimal 72 byte.` |
| `password` saat ubah | Opsional; bila diisi berlaku batas 8–72 byte | Kosong berarti hash tidak diubah. |
| `role` | Harus salah satu `admin`, `petugas`, `peminjam` | `Role pengguna tidak valid.` |
| `is_active` | Harus `0` atau `1` | `Status pengguna tidak valid.` |
| Status tidak dikirim saat tambah | Default `1` | Form tetap valid. |
| Status tidak dikirim saat ubah | Tidak ada default | Dianggap tidak valid; edit harus mengirim `0` atau `1`. |
| Role/status bukan string | Ditolak | Tidak ada coercion menjadi string. |
| Akun sendiri diubah role-nya | Ditolak | `Role akun yang sedang dipakai tidak dapat diubah.` |
| Akun sendiri dinonaktifkan | Ditolak | `Akun yang sedang dipakai tidak dapat dinonaktifkan.` |
| Admin aktif terakhir diturunkan/dinonaktifkan | Ditolak | `Admin aktif terakhir tidak dapat dihapus atau diturunkan.` |
| Duplikat username dari database | SQLSTATE `23000` dengan nama constraint `uq_users_username` | Diterjemahkan menjadi pesan duplikat yang ramah. |
| Pelanggaran integritas lain | SQLSTATE `23000` | `Data pengguna melanggar aturan integritas.` |
| ID route tidak valid atau tidak ada | Bukan angka positif atau tidak ditemukan | Halaman 404 `Pengguna tidak ditemukan.` |

Catatan validasi panjang password memakai `strlen()`, sehingga batas dihitung dalam byte, bukan karakter. Batas 72 byte mencegah pemotongan diam-diam oleh bcrypt.

## 7. Aturan konkurensi dan integritas

- `create()`, `update()`, dan `delete()` memakai transaksi.
- `update()` dan `delete()` memanggil `lockActiveAdmins()` untuk mengunci semua baris admin aktif dengan `ORDER BY id FOR UPDATE`, lalu mengunci baris target.
- Guard “admin aktif terakhir” dihitung dari daftar admin yang sudah dikunci, sehingga dua penurunan admin bersamaan tidak dapat sama-sama melihat admin lain yang tersisa.
- `update()` mengembalikan `false` bila baris target hilang setelah dikunci; controller merender 404.
- `delete()` mengembalikan `missing`, `deactivated`, atau melempar `DomainException` untuk guard.
- Akun yang sudah nonaktif dan kembali dinonaktifkan tetap menghasilkan outcome `deactivated`, tetapi tidak ada update dan tidak ada audit baru.
- Audit update menyimpan username, role lama/baru, dan status lama/baru. Audit tidak pernah menyimpan kata sandi atau hash.

## 8. Inventaris method

### [`app/Controllers/UserController.php`](../../app/Controllers/UserController.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `__construct(Auth $auth, Session $session, View $view, User $user)` | Auth, sesi, view, model pengguna | Objek controller | Menyimpan dependency. |
| `index(): void` | `$_GET['q']`, `$_GET['page']` | HTML | Mengambil daftar, total, dan jumlah halaman; merender `admin/users/index`. |
| `create(): void` | Tidak ada | HTML | Merender form tambah dengan nilai kosong. |
| `store(): void` | `$_POST` | Redirect atau HTML 422 | Parse, validasi, buat akun, tangani duplikat, set flash, redirect. |
| `edit(string $id): void` | ID route | HTML atau 404 | Cari baris, ubah ke nilai form, render form ubah. |
| `update(string $id): void` | ID route dan `$_POST` | Redirect, HTML 422, atau 404 | Parse dengan ID, panggil `update()`, petakan `DomainException` ke field, set flash, redirect. |
| `destroy(string $id): void` | ID route | Redirect atau 404 | Panggil deaktivasi; guard menghasilkan flash error dan redirect ke form; ID hilang menjadi 404. |
| `renderForm(?array $row, array $values, array $errors = [], int $status = 200): void` (private) | Baris, nilai, error, status | HTML | Menggabungkan nilai default dan merender `admin/users/form`. |
| `emptyValues(): array` (private) | Tidak ada | Nilai awal | `id`, `name`, `username` kosong; `role = peminjam`; `is_active = 1`. |
| `rowValues(array $row): array` (private) | Baris database | Nilai string | Mengubah `id`, `name`, `username`, `role`, `is_active` menjadi string. |
| `findOr404(string $id): ?array` (private) | ID route | Baris atau `null` | Memvalidasi `^[1-9]\d{0,18}$`, mencari baris, dan merender 404 bila gagal. |
| `notFound(): void` (private) | Tidak ada | HTML 404 | Menulis halaman 404 pengguna dan tautan kembali. |
| `pageParam(): int` (private) | `$_GET['page']` | Nomor halaman | Hanya menerima angka positif maksimal 7 digit; selain itu 1. |
| `actorId(): int` (private) | Sesi | ID admin | Mengembalikan ID pengguna atau 0 bila tidak ada. |
| `render(string $template, array $data, int $status = 200): void` (private) | Template, data, status | HTML | Mengambil dan menghapus flash `flash_success`/`flash_error`, lalu merender dengan kontrak view. |

### [`app/Models/User.php`](../../app/Models/User.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `__construct(PDO $db, ActivityLog $logs)` | PDO dan audit | Objek model | Menyimpan dependency. |
| `findActiveByUsername(string $username): ?array` | Username | Baris aktif dengan `password_hash` atau `null` | Query `username = ? AND is_active = 1 LIMIT 1`. Dipakai autentikasi. |
| `findActiveById(int $id): ?array` | ID | Baris aktif tanpa hash atau `null` | Query `id = ? AND is_active = 1 LIMIT 1`. Dipakai pembacaan identitas per request. |
| `parse(array $input, ?int $id = null): array` | Input mentah dan ID opsional | `values`, `errors`, `password` | Menormalkan field menjadi string, memisahkan password, memvalidasi panjang, keunikan, role, dan status. |
| `paginate(string $q, int $page): array` | Pencarian dan halaman | `items`, `total`, `totalPages` | Pencarian `LIKE` pada `name`/`username`, urut `name, id`, 10 baris per halaman. `totalPages` minimal 1. |
| `find(int $id): ?array` | ID | Baris atau `null` | Mengambil `id`, `name`, `username`, `role`, `is_active`, `created_at`. |
| `create(array $values, string $password, int $actorId): int` | Nilai valid, password, aktor | ID baru | Hash `PASSWORD_DEFAULT`, insert, audit `create`, commit. Gagal PDO di-rollback dan diterjemahkan. |
| `update(int $id, array $values, string $password, int $actorId): bool` | ID, nilai valid, password opsional, aktor | `true`, `false`, atau exception | Transaksi, lock admin aktif dan target, guard, update, audit, commit. `false` bila baris hilang. |
| `delete(int $id, int $actorId): string` | ID dan aktor | `deactivated` atau `missing` | Transaksi, lock, guard, ubah `is_active = 0`, audit `deactivate`, commit. Tidak menghapus baris. |
| `lockActiveAdmins(): array` (private) | Tidak ada | Daftar ID admin aktif | `SELECT id ... WHERE role = 'admin' AND is_active = 1 ORDER BY id FOR UPDATE`. |
| `usernameTaken(string $username, ?int $id): bool` (private) | Username dan ID opsional | `true`/`false` | Menghitung username, mengecualikan ID saat edit. |
| `translate(PDOException $exception): Throwable` (private) | Exception PDO | Exception asli atau `DomainException` | Hanya SQLSTATE `23000`; memetakan `uq_users_username` ke pesan duplikat, sisanya pesan integritas umum. |

Konstanta: `PER_PAGE = 10`, `ROLES = ['admin', 'petugas', 'peminjam']`, `MAX_PASSWORD_BYTES = 72`.

## 9. Pemetaan pengujian yang ada

Belum ada pengujian yang dijalankan untuk dokumen ini.

| Berkas pengujian | Cakupan yang terlihat dari isi berkas |
|---|---|
| [`tests/http_users_test.php`](../../tests/http_users_test.php) | Akses role, CSRF, tambah/ubah pengguna, hash password, audit tanpa password, pencarian, pagination, validasi field, guard akun sendiri, dan deaktivasi. |
| [`tests/http_auth_test.php`](../../tests/http_auth_test.php) | Login per peran dan perilaku sesi. |
| [`tests/security_test.php`](../../tests/security_test.php) | Route privat dan pembatasan role. |
| [`tests/database/schema_test.php`](../../tests/database/schema_test.php) | Struktur `users`, enum role, unique username, dan foreign key audit. |

## 10. Catatan dan risiko

- Tidak ada penghapusan fisik akun. Ini disengaja agar referensi transaksi dan audit tidak hilang.
- Perbandingan username mengikuti collation database `utf8mb4_unicode_ci`, sehingga pemeriksaan unik tidak membedakan huruf besar/kecil.
- Saat `update()` melempar `DomainException`, controller menebak field error: mengandung `Username` berarti field username; bila status baru `0` berarti `is_active`; selain itu `role`. Pesan guard admin terakhir karena itu dapat muncul di field role atau status.
- Form edit harus mengirim `is_active` secara eksplisit; nilai yang hilang ditolak.
- `destroy()` menampilkan pesan sukses “dinonaktifkan” juga untuk akun yang sebelumnya sudah nonaktif.
- Jangan menambahkan kata sandi, hash, token, atau ID sesi ke audit atau nilai form.
