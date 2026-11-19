# 08 — Dashboard

## Tujuan

Dashboard menampilkan ringkasan angka sesuai peran pengguna. Semua angka dihitung
langsung dari database pada setiap request; tidak ada angka contoh, cache, atau
nilai yang disimpan terpisah. Modul ini juga menjadi tujuan redirect setelah login
dan saat pengguna membuka `/`.

Requirement terkait: FR-01 (login/redirect) dan kebutuhan navigasi dashboard per
peran pada PRD. Dashboard tidak menambah wewenang lintas peran.

## Aktor dan route

| Route | Method | Aktor | Perilaku |
|---|---|---|---|
| `/` | GET | Pengguna terautentikasi | Redirect ke dashboard sesuai peran |
| `/admin/dashboard` | GET | Admin | Ringkasan inventaris, pengguna, dan transaksi |
| `/petugas/dashboard` | GET | Petugas | Ringkasan antrean persetujuan dan pengembalian |
| `/peminjam/dashboard` | GET | Peminjam | Ringkasan peminjaman milik sendiri |

Semua route bersifat privat. Pemeriksaan autentikasi, role, dan header keamanan
dilakukan terpusat di [`app/Core/App.php`](../../app/Core/App.php), bukan di
controller. Pengguna tanpa session diarahkan ke `/login`; role yang tidak sesuai
mendapat `403`; method selain `GET` pada path yang sama mendapat `405`.
Route didefinisikan di [`app/routes.php`](../../app/routes.php).

## Dependensi

| Komponen | Peran |
|---|---|
| [`DashboardController`](../../app/Controllers/DashboardController.php) | Menerima request, memilih view, meneruskan role dan user ID |
| [`Dashboard`](../../app/Models/Dashboard.php) | Menjalankan agregat SQL sesuai role |
| `App\Services\Auth` | Membaca identitas pengguna dari session |
| `App\Core\Session` | Menyediakan token CSRF untuk layout |
| `App\Core\View` | Merender `app/Views/dashboard.php` |
| `dashboard_path()` | Memetakan role ke URL dashboard; role tidak dikenal jatuh ke area peminjam |

Wiring dibuat manual di [`app/bootstrap.php`](../../app/bootstrap.php):
`new DashboardController($auth, $session, $view, new Dashboard($container['db']))`.

## Proses

### 1. Redirect halaman utama

| Tahap | Uraian |
|---|---|
| Input | Request `GET /`, session pengguna |
| Proses | `DashboardController::home()` membaca `Auth::user()`, lalu memanggil `dashboard_path((string) $user['role'])` |
| Output | `302 Location: /<role>/dashboard`; tanpa user diarahkan ke `/login` |
| Validasi | Route sudah privat, sehingga user null normalnya sudah ditangani kernel. Pemeriksaan null di controller tetap menjadi pengaman tambahan |
| Kegagalan | Role tidak dikenal dipetakan ke `/peminjam/dashboard` oleh helper. Kegagalan database saat resolusi session ditangani kernel sebagai `500` |

### 2. Render dashboard

| Tahap | Uraian |
|---|---|
| Input | Request `GET /<role>/dashboard`, user aktif pada session |
| Proses | Controller mengambil role, memanggil `Dashboard::forRole($role, (int) $user['id'])`, lalu merender `dashboard` |
| Output | Halaman `200` berisi kartu KPI, tautan tindakan, nama pengguna, dan layout aplikasi |
| Validasi | Role pada URL diperiksa metadata route. `Dashboard::forRole()` memakai `strtolower()` dan `match`; nilai selain `admin`/`petugas` diperlakukan sebagai peminjam |
| Kegagalan | Query database melempar exception, dicatat kernel, lalu ditampilkan sebagai halaman `500`. Tidak ada fallback angka nol diam-diam pada kegagalan query; nilai nol hanya dipakai bila agregat memang nol |

Data user yang dikirim ke view adalah hasil `Auth::user()`, bukan input form.
Dashboard tidak menerima parameter filter, pencarian, atau pagination.

## Perhitungan KPI

### Admin — `Dashboard::admin()`

Satu query agregat tanpa parameter:

| Kartu | Sumber | Tautan |
|---|---|---|
| Alat aktif | `COUNT(*) FROM alat WHERE is_active = 1` | `/admin/alat` |
| Pengguna aktif | `COUNT(*) FROM users WHERE is_active = 1` | `/admin/users` |
| Peminjaman aktif | status `approved` atau `return_requested` | `/admin/peminjaman` |
| Menunggu persetujuan | status `pending` | `/admin/peminjaman?status=pending` |

Input tidak ada. Proses membaca snapshot database. Output berupa empat elemen
`label`, `value`, `href`, dan `desc`; semua nilai di-cast ke `int`.

### Petugas — `Dashboard::petugas()`

Satu query agregat atas seluruh tabel `peminjaman`:

| Kartu | Kondisi |
|---|---|
| Menunggu persetujuan | `status = 'pending'` |
| Sedang dipinjam | `status = 'approved'` |
| Menunggu verifikasi | `status = 'return_requested'` |
| Terlambat | `status = 'approved'` dan `tanggal_jatuh_tempo < CURDATE()` |

Keterlambatan dihitung dari tanggal kalender server database, bukan selisih detik.
Peminjaman yang sudah diajukan kembali (`return_requested`) tidak dihitung sebagai
terlambat pada kartu ini. Output berupa empat kartu dengan tautan ke antrean
persetujuan atau monitoring pengembalian.

### Peminjam — `Dashboard::peminjam()`

Query terparameter `WHERE user_id = :user_id`, sehingga peminjam hanya melihat
transaksi miliknya. Nilai `user_id` berasal dari session dan di-cast ke `int`.

| Kartu | Kondisi |
|---|---|
| Menunggu persetujuan | `pending` |
| Disetujui | `approved` |
| Menunggu verifikasi | `return_requested` |
| Selesai | `returned` |

Transaksi `rejected` tidak memiliki kartu khusus. Output tetap empat kartu; tautan
mengarah ke daftar peminjaman atau pengembalian milik pengguna.

## Inventaris method

| Method | Input | Proses | Output |
|---|---|---|---|
| `DashboardController::__construct(Auth $auth, Session $session, View $view, Dashboard $dashboard)` | Empat dependensi | Menyimpan dependensi | Objek controller |
| `DashboardController::home()` | Session pengguna | Memilih tujuan berdasarkan role | Redirect `302` |
| `DashboardController::dashboard()` | Session pengguna | Mengambil statistik role lalu merender view | Halaman dashboard |
| `Dashboard::__construct(PDO $db)` | Koneksi PDO | Menyimpan koneksi | Objek model |
| `Dashboard::forRole(string $role, int $userId)` | Role dan ID user | Menormalisasi role lalu memilih agregat | Daftar kartu KPI |
| `Dashboard::admin()` | Tidak ada | Menghitung empat agregat admin | Daftar empat kartu |
| `Dashboard::petugas()` | Tidak ada | Menghitung empat agregat petugas | Daftar empat kartu |
| `Dashboard::peminjam(int $userId)` | ID user | Menghitung status milik user | Daftar empat kartu |

## Batas dan catatan

- Dashboard hanya menampilkan angka agregat. Tidak ada grafik, tren, ekspor, atau
  filter periode.
- Perubahan data langsung terlihat pada request berikutnya karena tidak ada cache.
- Jumlah pengguna dan alat hanya menghitung baris aktif; histori transaksi tidak
  dihapus oleh dashboard.
- Test HTTP terkait terdapat di
  [`tests/http_auth_test.php`](../../tests/http_auth_test.php): redirect login,
  akses dashboard per role, `403` lintas role, dan angka KPI admin. Dokumen ini
  tidak menyatakan test tersebut sudah dijalankan.
