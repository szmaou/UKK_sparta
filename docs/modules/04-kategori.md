# 04 — Kategori

Dokumen ini menjelaskan master data kategori alat yang dikelola Admin. Kategori menjadi referensi wajib bagi setiap alat dan tidak boleh dihapus selama masih dipakai.

## 1. Tanggung jawab

- Menampilkan daftar kategori dengan pencarian, jumlah alat, dan pagination.
- Menambah, mengubah, dan menghapus kategori.
- Menjaga nama kategori wajib, panjang maksimal, dan unik.
- Menjaga deskripsi maksimal 1000 karakter.
- Menolak penghapusan kategori yang masih direferensikan alat.
- Mencatat setiap mutasi ke `activity_logs` dalam transaksi yang sama.
- Menjaga referensi foreign key `fk_alat_kategori` dengan `ON DELETE RESTRICT`.

## 2. Aktor

| Aktor | Kepentingan |
|---|---|
| Admin | Satu-satunya peran yang boleh mengelola kategori. |
| Petugas | Tidak memiliki akses ke route kategori Admin. |
| Peminjam | Tidak memiliki akses ke route kategori Admin. |
| Sistem | Menjaga unique constraint, foreign key, transaksi, row lock, dan audit. |

## 3. Rute

| Rute | Metode | Handler | Proteksi |
|---|---|---|---|
| `/admin/kategori` | GET | `KategoriController::index` | Role `admin`. |
| `/admin/kategori/create` | GET | `KategoriController::create` | Role `admin`. |
| `/admin/kategori/create` | POST | `KategoriController::store` | Role `admin` dan CSRF. |
| `/admin/kategori/{id}/edit` | GET | `KategoriController::edit` | Role `admin`. |
| `/admin/kategori/{id}/edit` | POST | `KategoriController::update` | Role `admin` dan CSRF. |
| `/admin/kategori/{id}/delete` | POST | `KategoriController::destroy` | Role `admin` dan CSRF. |

Route didaftarkan oleh loop master data di [`app/routes.php`](../../app/routes.php). Proteksi terpusat ada di [`app/Core/App.php`](../../app/Core/App.php).

## 4. Dependensi

- [`app/Controllers/KategoriController.php`](../../app/Controllers/KategoriController.php)
- [`app/Models/Kategori.php`](../../app/Models/Kategori.php)
- [`app/Models/ActivityLog.php`](../../app/Models/ActivityLog.php)
- [`app/Services/Auth.php`](../../app/Services/Auth.php)
- [`app/Core/Session.php`](../../app/Core/Session.php) dan [`app/Core/View.php`](../../app/Core/View.php)
- Tabel `kategori` dan `alat` di [`database/schema.sql`](../../database/schema.sql)
- `Kategori::PER_PAGE = 10`

## 5. Alur proses

| Proses | Input | Langkah | Output | Validasi dan error |
|---|---|---|---|---|
| Daftar kategori | Query `q`, `page` | `index()` membaca pencarian dan halaman, memanggil `paginate()`, lalu merender daftar. | HTML 10 baris per halaman dengan `jumlah_alat`. | `page` hanya diterima bila `^[1-9]\d{0,6}$`; nilai lain menjadi 1. Pencarian dipotong 100 karakter. |
| Form tambah | Tidak ada | `create()` merender nilai awal nama dan deskripsi kosong. | HTML form. | Tidak ada input diproses. |
| Tambah kategori | `nama`, `deskripsi` | `parse()` menormalkan dan memvalidasi; `create()` insert, audit, commit. | Redirect 302 ke `/admin/kategori` dan flash sukses. | Error field menjadi 422. Duplikat nama menjadi 422 dengan pesan ramah. |
| Form ubah | `{id}` | `findOr404()` mengambil baris dan mengisi form. | HTML form atau 404. | ID harus angka positif 1–19 digit dan baris harus ada. |
| Ubah kategori | `{id}`, `nama`, `deskripsi` | `parse()` dengan ID; `update()` memperbarui baris, audit, commit. | Redirect 302 dan flash sukses. | Error validasi atau duplikat menjadi 422. ID tidak ada menjadi 404 sebelum update. |
| Hapus kategori | `{id}` | `delete()` mengunci baris, menghitung referensi `alat`, menghapus bila aman, audit, commit. | `deleted`, `referenced`, atau `missing`. | `referenced` menghasilkan flash error dan redirect ke form edit. `missing` menjadi 404. Pelanggaran FK tetap ditangkap sebagai `referenced`. |

## 6. Validasi dan error

| Field atau kondisi | Aturan | Pesan atau hasil |
|---|---|---|
| `nama` | Wajib, hasil trim, maksimal 100 karakter, unik | `Nama kategori wajib diisi.` / `Nama kategori maksimal 100 karakter.` / `Nama kategori sudah digunakan.` |
| `deskripsi` | Opsional, hasil trim, maksimal 1000 karakter | `Deskripsi maksimal 1000 karakter.` |
| Deskripsi kosong | Disimpan sebagai `NULL` | Form dapat menampilkan string kosong. |
| ID route tidak valid | Bukan angka positif atau lebih dari 19 digit | 404 `Kategori tidak ditemukan.` |
| Baris tidak ditemukan | ID valid tetapi tidak ada | 404. |
| Kategori masih dipakai alat | Ada baris `alat.kategori_id` yang merujuk | Flash `Kategori masih digunakan oleh alat. Pindahkan atau hapus alat terkait sebelum menghapus kategori.` dan redirect ke form edit. |
| Duplikat nama dari database | SQLSTATE `23000` | `DomainException` dengan pesan `Nama kategori sudah digunakan.` |
| Integritas lain saat mutasi | SQLSTATE `23000` | `translate()` tetap memakai pesan duplikat yang sama; kode tidak membedakan nama constraint. |

## 7. Aturan konkurensi dan integritas

- `create()`, `update()`, dan `delete()` memakai transaksi.
- `delete()` mengunci baris kategori dengan `SELECT id ... FOR UPDATE` sebelum menghitung referensi. Ini mencegah alat baru disisipkan di antara pengecekan dan penghapusan.
- Foreign key `fk_alat_kategori` memakai `ON UPDATE CASCADE ON DELETE RESTRICT`, sehingga database tetap menolak referensi yang lolos dari pengecekan aplikasi.
- `delete()` menangkap SQLSTATE `23000` setelah rollback dan mengembalikan `referenced`.
- `create()` dan `update()` menangkap `PDOException` dan memanggil `translate()`; exception non-PDO di-rollback lalu dilempar ulang.
- Nama kategori unik dijaga oleh `uq_kategori_nama` dan pengecekan `nameTaken()`.

## 8. Inventaris method

### [`app/Controllers/KategoriController.php`](../../app/Controllers/KategoriController.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `__construct(Auth $auth, Session $session, View $view, Kategori $kategori)` | Auth, sesi, view, model | Objek controller | Menyimpan dependency. |
| `index(): void` | `$_GET['q']`, `$_GET['page']` | HTML | Mengambil daftar, total, dan halaman; merender `admin/kategori/index`. |
| `create(): void` | Tidak ada | HTML | Merender form tambah dengan `nama` dan `deskripsi` kosong. |
| `store(): void` | `$_POST` | Redirect atau HTML 422 | Parse, validasi, create, tangani duplikat, flash, redirect. |
| `edit(string $id): void` | ID route | HTML atau 404 | Cari baris, susun nilai string, render form ubah. |
| `update(string $id): void` | ID route dan `$_POST` | Redirect atau HTML 422 | Parse dengan ID, update, tangani duplikat, flash, redirect. |
| `destroy(string $id): void` | ID route | Redirect atau 404 | Panggil `delete()`; `missing` menjadi 404; `referenced` menjadi flash error dan redirect ke edit; `deleted` menjadi flash sukses. |
| `renderForm(?array $row, array $values, array $errors = [], int $status = 200): void` (private) | Baris, nilai, error, status | HTML | Merender `admin/kategori/form` dengan `isEdit`, `values`, dan `errors`. |
| `findOr404(string $id): ?array` (private) | ID route | Baris atau `null` | Memvalidasi `^[1-9]\d{0,18}$`, mencari baris, merender 404 bila gagal. |
| `notFound(): void` (private) | Tidak ada | HTML 404 | Menulis halaman 404 kategori dan tautan kembali. |
| `pageParam(): int` (private) | `$_GET['page']` | Nomor halaman | Angka positif maksimal 7 digit; selain itu 1. |
| `actorId(): int` (private) | Sesi | ID admin | ID pengguna atau 0. |
| `flash(string $key, string $message): void` (private) | Kunci dan pesan | Tidak ada | Menulis flash ke sesi. |
| `render(string $template, array $data, int $status = 200): void` (private) | Template, data, status | HTML | Mengambil dan menghapus flash, lalu merender dengan kontrak view. |

### [`app/Models/Kategori.php`](../../app/Models/Kategori.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `__construct(PDO $db, ActivityLog $logs)` | PDO dan audit | Objek model | Menyimpan dependency. |
| `parse(array $input, ?int $id = null): array` | Input mentah dan ID opsional | `values`, `errors` | Menormalkan `nama`/`deskripsi` menjadi string, memvalidasi wajib, panjang, dan keunikan nama di luar ID sendiri. |
| `paginate(string $q, int $page): array` | Pencarian dan halaman | `items`, `total`, `totalPages` | Pencarian `LIKE` pada nama/deskripsi, `LEFT JOIN alat`, hitung `jumlah_alat`, grup per kategori, urut nama, 10 baris per halaman. |
| `find(int $id): ?array` | ID | Baris atau `null` | Mengambil `id`, `nama`, `deskripsi`. |
| `create(array $values, int $actorId): int` | Nilai valid dan aktor | ID baru | Insert dengan deskripsi kosong menjadi `NULL`, audit `create`, commit. |
| `update(int $id, array $values, int $actorId): void` | ID, nilai valid, aktor | Tidak ada | Update nama/deskripsi, audit `update`, commit. |
| `delete(int $id, int $actorId): string` | ID dan aktor | `deleted`, `referenced`, atau `missing` | Lock baris, hitung alat, hapus bila tidak ada referensi, audit `delete`, commit. FK violation menjadi `referenced`. |
| `nameTaken(string $nama, ?int $id): bool` (private) | Nama dan ID opsional | `true`/`false` | Menghitung nama, mengecualikan ID saat edit. |
| `translate(PDOException $exception, string $duplicate): Throwable` (private) | Exception dan pesan duplikat | Exception asli atau `DomainException` | SQLSTATE `23000` dipetakan ke `DomainException($duplicate)`; kode lain dikembalikan apa adanya. |

## 9. Pemetaan pengujian yang ada

Belum ada pengujian yang dijalankan untuk dokumen ini.

| Berkas pengujian | Cakupan yang terlihat dari isi berkas |
|---|---|
| [`tests/http_master_data_test.php`](../../tests/http_master_data_test.php) | Akses role, CSRF, daftar, pencarian, pagination, tambah/ubah/hapus kategori, validasi field, audit, flash sekali tampil, dan penolakan hapus kategori yang masih dipakai. |
| [`tests/security_test.php`](../../tests/security_test.php) | Route privat dan pembatasan role Admin. |
| [`tests/database/schema_test.php`](../../tests/database/schema_test.php) | Unique `kategori.nama` dan foreign key `fk_alat_kategori` dengan aturan restrict. |

## 10. Catatan dan risiko

- Nama kategori diperiksa unik di aplikasi dan dijaga unique constraint database. Perbandingan mengikuti collation `utf8mb4_unicode_ci`.
- `translate()` tidak memeriksa nama constraint. Pelanggaran integritas SQLSTATE `23000` selain duplikat nama tetap ditampilkan sebagai “Nama kategori sudah digunakan.”
- `update()` tidak memeriksa jumlah baris terpengaruh. Bila baris dihapus bersamaan setelah `findOr404()`, update dapat menghasilkan nol baris tetapi audit tetap dicatat.
- Kategori yang masih dipakai tidak boleh dihapus. Alat harus dipindahkan ke kategori lain atau dihapus lebih dahulu.
- Jangan mengubah foreign key menjadi `ON DELETE CASCADE`; riwayat alat dan peminjaman akan kehilangan referensi kategori.
