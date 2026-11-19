# 10 — Audit Aktivitas

## Tujuan

Modul audit menyimpan jejak aktivitas penting dan menyediakan tampilan hanya-baca
bagi Admin. Log ditulis oleh aplikasi dan oleh trigger database untuk perubahan
status peminjaman. Halaman audit tidak menyediakan tambah, ubah, atau hapus manual.

Requirement terkait: FR-08 (log aktivitas) dan BR-11 (perubahan kritis memiliki
aktor dan waktu).

## Aktor dan route

| Route | Method | Aktor | Output |
|---|---|---|---|
| `/admin/activity-logs` | GET | Admin | Daftar log terbaru dengan pencarian, filter aksi, dan pagination |

Route privat dengan role `admin` di
[`app/routes.php`](../../app/routes.php). Petugas, peminjam, dan pengguna tanpa
session ditolak oleh kernel (`403` atau redirect login). Tidak ada route mutasi
untuk modul ini.

## Dependensi

| Komponen | Peran |
|---|---|
| [`ActivityLogController`](../../app/Controllers/ActivityLogController.php) | Membaca filter/pagination, merender daftar |
| [`ActivityLog`](../../app/Models/ActivityLog.php) | Query daftar, daftar aksi, dan penulisan log |
| [`database/routines.sql`](../../database/routines.sql) | Trigger audit perubahan status peminjaman |
| `App\Services\Auth`, `App\Core\Session`, `App\Core\View` | Identitas, flash, token, rendering |
| Tabel `activity_logs` | Penyimpanan append-only |

Wiring: `new ActivityLogController($auth, $session, $view, $logs)` dengan satu
instance `ActivityLog` yang juga dipakai model lain di
[`app/bootstrap.php`](../../app/bootstrap.php).

## Proses tampilan

### `ActivityLogController::index()`

| Tahap | Uraian |
|---|---|
| Input | Query string `q`, `action`, `page`; session dan flash message |
| Proses | Controller men-trim `q` dan `action`, memvalidasi halaman melalui `pageParam()`, lalu memanggil `ActivityLog::paginate($q, $action, $page)`. `ActivityLog::actions()` mengisi pilihan filter |
| Output | Halaman `200` berisi daftar maksimum 20 log, jumlah total, nomor halaman, dan navigasi sebelumnya/berikutnya |
| Validasi | `q` dan `action` hanya diterima bila string; nilai lain menjadi string kosong. `page` hanya diterima bila cocok `^[1-9]\d{0,6}$`; selain itu menjadi `1`. Pencarian dibatasi 100 karakter di model |
| Kegagalan | Query database gagal ditangkap kernel dan dirender sebagai `500`. Filter yang tidak menemukan hasil bukan error; halaman menampilkan keadaan kosong |

`pageParam()` tidak membatasi halaman terhadap jumlah halaman sebenarnya. Halaman
di luar rentang menghasilkan daftar kosong, sementara teks footer tetap memakai
nomor halaman yang diminta dan `totalPages` dari hitungan database.

### Filter dan pagination — `ActivityLog::paginate()`

| Aspek | Perilaku |
|---|---|
| Urutan | `created_at DESC, id DESC`, terbaru lebih dahulu |
| Filter aksi | `al.action = :action` bila `action` tidak kosong; nilai tidak dibatasi daftar tertentu, tetapi selalu di-bind |
| Pencarian | `description`, `entity_type`, `u.name`, atau `u.username` mengandung `q` |
| Join | `activity_logs al LEFT JOIN users u ON u.id = al.user_id` |
| Ukuran halaman | `PER_PAGE = 20` |
| Offset | `(max(1, page) - 1) * 20` |
| Total | `COUNT(*)` dengan WHERE yang sama; `totalPages = max(1, ceil(total / 20))` |
| Parameter | Semua filter memakai named placeholder; limit/offset di-bind sebagai integer |

`actions()` mengambil `DISTINCT action` dari seluruh tabel dan mengurutkannya
secara alfabetis. Pilihan filter di UI memakai label Indonesia untuk aksi yang
dikenal; aksi lain ditampilkan apa adanya.

## Proses penulisan log

### `ActivityLog::record()`

| Tahap | Uraian |
|---|---|
| Input | `userId` nullable, `action`, `entityType` nullable, `entityId` nullable, `description` nullable |
| Proses | `INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)` |
| Output | Tidak mengembalikan nilai; baris log baru |
| Validasi | Tidak ada validasi atau pemotongan tambahan di method ini. Panjang mengikuti kolom database (`action` 50, `entity_type` 50) |
| Kegagalan | Exception database diteruskan ke pemanggil. Pemanggil yang berada dalam transaksi akan melakukan rollback |

Log tidak menyimpan password, hash password, token CSRF, ID session, atau isi
`.env`. Nilai yang ditulis adalah ID aktor, nama aksi, jenis/ID entitas, dan
deskripsi operasional.

### Sumber log aplikasi

| Sumber | Aksi | Entitas | Keterangan |
|---|---|---|---|
| `AuthController::login()` | `login` | `user` | Login berhasil; login gagal tidak dicatat sebagai log audit, hanya dihitung throttle |
| `AuthController::logout()` | `logout` | `user` | Logout pengguna yang masih memiliki session |
| `Peminjaman::create()` | `create` | `peminjaman` | Pengajuan baru beserta kode |
| `Peminjaman::decide()` | `reject_note` | `peminjaman` | Catatan penolakan, bila diisi |
| `Peminjaman::adminUpdate()` | `update` | `peminjaman` | Koreksi administratif |
| `Peminjaman::adminDelete()` | `delete` | `peminjaman` | Penghapusan transaksi yang diizinkan |
| `Pengembalian::adminUpdate()` | `update` | `pengembalian` | Koreksi data pengembalian, ditulis dengan SQL langsung |
| `Pengembalian::adminDelete()` | `delete` | `pengembalian` | Penghapusan pengajuan yang belum diverifikasi, ditulis dengan SQL langsung |
| `Kategori` | `create`, `update`, `delete` | `kategori` | Perubahan master kategori |
| `Alat` | `create`, `update`, `delete`, `deactivate` | `alat` | Perubahan master alat; alat yang sudah dipakai transaksi dinonaktifkan, bukan dihapus |
| `User` | `create`, `update`, `deactivate` | `user` | Perubahan akun; akun tidak dihapus permanen |
| `Pengaturan::save()` | `update` | `pengaturan` | Perubahan tarif denda beserta nilai lama dan baru |

Perubahan status peminjaman tidak ditulis sebagai baris `update` oleh aplikasi.
Aplikasi mengeset variabel session database `@sparta_actor_id`, lalu trigger yang
menulis baris `status_change`. Ini menghindari dua baris audit untuk satu transisi.

## Trigger `trg_peminjaman_audit_update`

Sumber: [`database/routines.sql`](../../database/routines.sql).

| Aspek | Uraian |
|---|---|
| Signature | `CREATE OR REPLACE TRIGGER trg_peminjaman_audit_update AFTER UPDATE ON peminjaman FOR EACH ROW` |
| Input | Baris `OLD` dan `NEW`; nilai `@sparta_actor_id` pada session database |
| Proses | Bila `OLD.status <> NEW.status`, insert ke `activity_logs` |
| Output | Baris `action = 'status_change'`, `entity_type = 'peminjaman'`, `entity_id = NEW.id`, `description = 'Status <lama> -> <baru>'`, `user_id = @sparta_actor_id` |
| Aktor | `@sparta_actor_id` diset oleh alur approval dan pengembalian. Bila `NULL`, tampilan menyebut pelaku sebagai `Sistem` |
| Validasi | Hanya perubahan status; update kolom lain tidak menghasilkan log |
| Kegagalan | Trigger berjalan dalam transaksi yang sama dengan update. Gagal insert berarti update ikut gagal/rollback |

Nilai `@sparta_actor_id` bersifat per session koneksi. Alur yang memakainya
mengembalikan nilai ke `NULL` di blok `finally`. Koneksi PDO normalnya berumur satu
request, sehingga nilai tidak menyeberang ke request lain.

## Inventaris method

| Method | Input | Proses | Output |
|---|---|---|---|
| `ActivityLogController::__construct(Auth, Session, View, ActivityLog)` | Empat dependensi | Menyimpan dependensi | Objek controller |
| `ActivityLogController::index()` | `$_GET` dan session | Parse filter, paginate, ambil daftar aksi, render | Halaman daftar log |
| `ActivityLogController::pageParam()` | `$_GET['page']` | Validasi string bilangan positif | Nomor halaman aman |
| `ActivityLogController::render(string $template, array $data, int $status = 200)` | Template, data, status | Mengambil user/flash dan menambah kontrak view | Render halaman |
| `ActivityLog::__construct(PDO $db)` | Koneksi PDO | Menyimpan koneksi | Objek model |
| `ActivityLog::paginate(string $q, string $action, int $page)` | Pencarian, aksi, halaman | Hitung total, ambil baris dengan limit/offset | `items`, `total`, `totalPages` |
| `ActivityLog::actions()` | Tidak ada | `SELECT DISTINCT action` | Daftar nama aksi |
| `ActivityLog::record(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null)` | Metadata aktivitas | Insert baris log | `void` |
| `trg_peminjaman_audit_update` | `OLD`, `NEW`, `@sparta_actor_id` | Deteksi perubahan status | Baris `status_change` |

## Batas dan catatan

- Tampilan audit hanya-baca; tidak ada method aplikasi untuk mengubah atau
  menghapus log. Hak database runtime tetap menentukan siapa yang secara teknis
  dapat menulis langsung ke tabel.
- Login gagal tidak masuk `activity_logs`; pemantauannya berada pada modul
  autentikasi/throttle.
- Log lama tetap merujuk ID user. Karena akun dinonaktifkan dan tidak dihapus,
  nama pelaku masih dapat di-join; bila `user_id` null, UI menampilkan `Sistem`.
- Halaman di luar jumlah halaman tidak dinormalkan kembali ke halaman terakhir.
- Test terkait ada di [`tests/http_backoffice_test.php`](../../tests/http_backoffice_test.php)
  dan [`tests/database/schema_test.php`](../../tests/database/schema_test.php).
  Dokumen ini tidak menyatakan test sudah dijalankan.
