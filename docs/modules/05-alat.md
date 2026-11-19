# 05 — Alat

Dokumen ini menjelaskan master data alat, stok, katalog alat aktif, dan unggahan foto alat. Implementasi mengikuti kode pada working tree saat ini, termasuk perilaku unggahan foto saat ini.

## 1. Tanggung jawab

- Menampilkan daftar alat dengan pencarian, kategori, stok, dan pagination.
- Menambah, mengubah, dan menghapus atau menonaktifkan alat.
- Menjaga kode alat wajib dan unik, nama wajib, kategori valid, serta stok total valid.
- Menjaga invariant stok: `stok_tersedia` tidak pernah diambil dari input dan selalu dihitung dari total dikurangi unit yang dipinjam.
- Menerima foto JPG, PNG, atau WebP maksimal 2 MB, memvalidasi isi berkas, menyimpannya dengan nama acak, dan menghapus berkas lama saat diganti atau dihapus.
- Menyediakan katalog alat aktif untuk Peminjam.
- Mencatat setiap mutasi alat ke `activity_logs` dalam transaksi yang sama.

## 2. Aktor

| Aktor | Kepentingan |
|---|---|
| Admin | Mengelola master data alat dan foto melalui `/admin/alat`. |
| Peminjam | Melihat katalog alat aktif melalui `/peminjam/alat`. |
| Petugas | Tidak mengelola master data alat; memakai alat dalam alur persetujuan dan pengembalian. |
| Sistem | Menjaga stok, foreign key, transaksi, audit, dan berkas unggahan. |

## 3. Rute

### Admin

| Rute | Metode | Handler | Proteksi |
|---|---|---|---|
| `/admin/alat` | GET | `AlatController::index` | Role `admin`. |
| `/admin/alat/create` | GET | `AlatController::create` | Role `admin`. |
| `/admin/alat/create` | POST | `AlatController::store` | Role `admin` dan CSRF. |
| `/admin/alat/{id}/edit` | GET | `AlatController::edit` | Role `admin`. |
| `/admin/alat/{id}/edit` | POST | `AlatController::update` | Role `admin` dan CSRF. |
| `/admin/alat/{id}/delete` | POST | `AlatController::destroy` | Role `admin` dan CSRF. |

### Katalog Peminjam

| Rute | Metode | Handler | Proteksi |
|---|---|---|---|
| `/peminjam/alat` | GET | `KatalogController::index` | Role `peminjam`. |

`KatalogController` memakai `Alat::catalog()` dan `Alat::categories()`. Controller katalog berada di luar cakupan dokumen ini, tetapi method modelnya termasuk dalam inventaris di bawah.

Route didaftarkan di [`app/routes.php`](../../app/routes.php). Proteksi login, role, dan CSRF ada di [`app/Core/App.php`](../../app/Core/App.php).

## 4. Dependensi

- [`app/Controllers/AlatController.php`](../../app/Controllers/AlatController.php)
- [`app/Models/Alat.php`](../../app/Models/Alat.php)
- [`app/Models/Kategori.php`](../../app/Models/Kategori.php) untuk validasi dan daftar kategori.
- [`app/Models/ActivityLog.php`](../../app/Models/ActivityLog.php)
- [`app/Controllers/KatalogController.php`](../../app/Controllers/KatalogController.php)
- [`app/Services/Auth.php`](../../app/Services/Auth.php)
- [`app/Core/Session.php`](../../app/Core/Session.php) dan [`app/Core/View.php`](../../app/Core/View.php)
- Tabel `alat`, `kategori`, dan `peminjaman` di [`database/schema.sql`](../../database/schema.sql)
- Direktori `public/uploads/alat/` dan konfigurasi penyajian di [`docker/nginx/default.conf`](../../docker/nginx/default.conf) serta [`public/router.php`](../../public/router.php)
- `Alat::PER_PAGE = 10`, `FOTO_MAX_BYTES = 2097152`, `FOTO_MIMES`, dan `MAX_UNSIGNED = 4294967295`

## 5. Alur proses

| Proses | Input | Langkah | Output | Validasi dan error |
|---|---|---|---|---|
| Daftar alat | Query `q`, `page` | `index()` membaca pencarian dan halaman; `paginate()` menghitung total, mengambil 10 baris, dan menghitung stok dipinjam. | HTML daftar alat. | `page` hanya `^[1-9]\d{0,6}$`; nilai lain menjadi 1. Pencarian dipotong 100 karakter. |
| Form tambah | Tidak ada | `create()` merender nilai awal. | HTML form. | Default stok total `0`, status `1`, `hapus_foto = 0`. |
| Tambah alat | `kode`, `nama`, `kategori_id`, `deskripsi`, `stok_total`, `is_active`, file `foto` | `parse()` memvalidasi field; `savePhoto()` memvalidasi dan menyimpan foto; `create()` insert dengan `stok_tersedia = stok_total`, audit, commit. | Redirect 302 ke `/admin/alat` dan flash sukses. | Error field atau foto menghasilkan 422. Duplikat kode menghapus foto baru dan menampilkan error pada `kode`. |
| Form ubah | `{id}` | `findOr404()` mengambil baris; foto saat ini ikut dikirim ke form. | HTML form atau 404. | ID harus angka positif 1–19 digit. |
| Ubah alat | `{id}`, field form, `hapus_foto`, file opsional | `parse()`; simpan foto baru tanpa menghapus foto lama; hitung stok dipinjam di bawah row lock; update; audit; commit; hapus foto lama setelah commit. | Redirect 302 dan flash sukses, atau 422. | Stok total tidak boleh kurang dari unit yang dipinjam. Error foto atau stok menghasilkan 422. Baris hilang menjadi 404 dan foto baru dibatalkan. |
| Hapus alat | `{id}` | `delete()` mengunci baris, menghitung referensi `peminjaman`; bila ada referensi, nonaktifkan; bila tidak, hapus baris. | `deleted`, `deactivated`, atau `missing`. | Alat bertransaksi menghasilkan flash error dan redirect ke form edit. Alat tanpa transaksi dihapus beserta fotonya. |
| Katalog alat | Query `q`, `kategori_id`, `page` | `catalog()` hanya mengambil `is_active = 1`, memfilter kode/nama dan kategori, lalu pagination. | HTML katalog 10 baris. | `kategori_id` tidak valid diabaikan menjadi tanpa filter melalui `positiveId()`. |

## 6. Aturan stok

- `stok_tersedia` tidak pernah dibaca dari request.
- Saat tambah, `stok_tersedia = stok_total`.
- Saat ubah, nilai `stok_dipinjam` dihitung dari baris lama: `stok_total - stok_tersedia`.
- Nilai `stok_total` baru harus `>= stok_dipinjam`.
- Setelah update, `stok_tersedia = stok_total - stok_dipinjam`.
- Perhitungan dilakukan setelah baris alat dikunci dengan `SELECT ... FOR UPDATE`.
- Database memiliki `CHECK (stok_tersedia <= stok_total)`.
- Alat dengan riwayat peminjaman tidak dihapus, hanya dinonaktifkan. Riwayat peminjaman tetap memiliki referensi alat.

Pesan stok: `Stok total tidak boleh kurang dari N unit yang sedang dipinjam.`

## 7. Validasi alat

| Field atau kondisi | Aturan | Pesan atau hasil |
|---|---|---|
| `kode` | Wajib, trim, maksimal 30 karakter, unik | `Kode alat wajib diisi.` / `Kode alat maksimal 30 karakter.` / `Kode alat sudah digunakan. Gunakan kode lain.` |
| `nama` | Wajib, trim, maksimal 150 karakter | `Nama alat wajib diisi.` / `Nama alat maksimal 150 karakter.` |
| `kategori_id` | Wajib ID positif dan harus ada | `Kategori wajib dipilih.` / `Kategori tidak ditemukan.` |
| `deskripsi` | Opsional, trim, maksimal 1000 karakter | `Deskripsi maksimal 1000 karakter.` |
| `stok_total` | Wajib angka `^\d{1,10}$`, maksimal 4294967295 | `Stok total harus bilangan bulat 0 atau lebih.` |
| `is_active` | Harus `0` atau `1` | `Status alat tidak valid.` |
| Status tidak dikirim saat tambah | Default `1` | Form valid. |
| Status tidak dikirim saat ubah | Tidak ada default | Ditampilkan sebagai `Status alat tidak valid.` |
| `hapus_foto` | Hanya nilai string `1` yang dianggap permintaan hapus | Nilai lain menjadi `0`. |
| Kode duplikat dari database | SQLSTATE `23000`, constraint `uq_alat_kode` | Pesan duplikat kode. |
| Kategori hilang dari database | SQLSTATE `23000`, constraint `fk_alat_kategori` | `Kategori tidak ditemukan.` |
| Integritas lain | SQLSTATE `23000` | `Data alat melanggar aturan integritas.` |
| ID route tidak valid/tidak ada | Bukan angka positif atau tidak ditemukan | 404 `Alat tidak ditemukan.` |

## 8. Semantik unggahan foto saat ini

### Validasi dan penyimpanan

| Tahap | Perilaku aktual |
|---|---|
| Sumber | `$_FILES['foto']`; bila tidak ada atau `error` tidak diset, dianggap tanpa file. |
| Tidak ada file | `UPLOAD_ERR_NO_FILE` menghasilkan `[null, null]`; form tetap valid. |
| Error upload lain | `Foto gagal diunggah. Coba lagi.` |
| Nama sementara | Harus string tidak kosong dan lolos `is_uploaded_file()`. Jika tidak, `Foto tidak valid.` |
| Ukuran | Harus `> 0` dan `<= 2097152` byte. Jika tidak, `Ukuran foto maksimal 2 MB.` |
| Tipe isi | `finfo_file()` bila tersedia; fallback `getimagesize()`. MIME harus `image/jpeg`, `image/png`, atau `image/webp`. Jika tidak, `Format foto harus JPG, PNG, atau WebP.` |
| Direktori | `public/uploads/alat`; dibuat dengan `mkdir(..., 0775, true)` bila belum ada. Gagal membuat direktori menghasilkan `Direktori unggahan foto tidak tersedia.` |
| Nama berkas | `bin2hex(random_bytes(16))` + ekstensi dari MIME terdeteksi: `.jpg`, `.png`, atau `.webp`. Nama asli dan ekstensi kiriman tidak dipakai. |
| Pemindahan | `move_uploaded_file()` ke direktori unggahan. Gagal menghasilkan `Foto gagal disimpan.` |
| Penyimpanan database | Kolom `alat.foto` hanya berisi nama berkas, bukan path lengkap. |
| URL tampilan | `/uploads/alat/<nama-berkas>` dengan `rawurlencode()` pada view. |

Konstanta `FOTO_MAX_BYTES` bernilai `2097152` byte. `FOTO_MIMES` memetakan tiga MIME ke ekstensi. SVG dan GIF tidak diterima.

### Siklus hidup berkas

- **Tambah:** foto disimpan sebelum insert. Bila insert gagal karena `DomainException`, controller memanggil `deletePhotoFile()` pada foto baru.
- **Ubah dengan foto baru:** `savePhoto($_FILES['foto'], null)` menyimpan berkas baru tanpa menghapus yang lama. Foto lama baru dihapus setelah `Alat::update()` commit. Bila update gagal, foto baru dihapus dan foto lama dipertahankan.
- **Ubah tanpa foto:** bila `hapus_foto = 1`, nilai `foto` menjadi kosong dan foto lama dihapus setelah commit. Selain itu field `foto` tidak diubah.
- **Hapus alat:** bila baris dihapus, `delete()` memanggil `deletePhotoFile()` setelah commit. Bila alat dinonaktifkan karena sudah dipakai transaksi, foto tetap disimpan.
- **Nama tidak valid:** `deletePhotoFile()` hanya menerima nama yang cocok `^[a-f0-9]{32}\.(jpg|png|webp)$`. Nama lain, termasuk path traversal, diabaikan.
- **Kegagalan hapus:** `@unlink()` menekan error; kegagalan menghapus berkas tidak membatalkan transaksi database.

Parameter `$existing` pada `savePhoto()` dapat menghapus berkas lama segera setelah pemindahan berhasil. Controller saat ini selalu memanggil dengan `null`, sehingga penghapusan foto lama dilakukan setelah commit oleh controller.

### Penyajian berkas

- Nginx memakai `root /var/www/html/public` dan `client_max_body_size 8m`.
- `location ^~ /uploads/` menyajikan berkas statis dan tidak meneruskan ke PHP-FPM.
- Regex bersarang menolak ekstensi `php*`, `phtml`, `phar`, `cgi`, `pl`, `py`, `sh`, `shtml`, dan `htaccess`.
- Regex bersarang wajib dipertahankan agar permintaan berekstensi executable di dalam `/uploads/` tetap ditolak dan tidak bergantung pada perilaku handler PHP global.
- Dengan `public/router.php`, PHP built-in server menyajikan `jpg`, `jpeg`, `png`, dan `webp` dengan `readfile()` dan `X-Content-Type-Options: nosniff`; ekstensi lain diarahkan ke front controller.
- Berkas unggahan tidak pernah dieksekusi sebagai PHP pada konfigurasi yang benar.

## 9. Inventaris method

### [`app/Controllers/AlatController.php`](../../app/Controllers/AlatController.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `__construct(Auth $auth, Session $session, View $view, Alat $alat)` | Auth, sesi, view, model | Objek controller | Menyimpan dependency. |
| `index(): void` | `$_GET['q']`, `$_GET['page']` | HTML | Mengambil daftar, total, dan halaman; merender `admin/alat/index`. |
| `create(): void` | Tidak ada | HTML | Merender form tambah dengan nilai default. |
| `store(): void` | `$_POST` dan `$_FILES['foto']` | Redirect atau HTML 422 | Parse field, validasi foto, simpan foto, create, bersihkan foto baru bila gagal, flash, redirect. |
| `edit(string $id): void` | ID route | HTML atau 404 | Cari baris, susun nilai string termasuk `foto`, render form. |
| `update(string $id): void` | ID route, `$_POST`, `$_FILES`, `hapus_foto` | Redirect, HTML 422, atau 404 | Parse; tentukan foto baru/hapus/pertahankan; update; bersihkan foto baru saat gagal; hapus foto lama setelah commit. |
| `destroy(string $id): void` | ID route | Redirect atau 404 | Panggil `delete()`; `missing` menjadi 404; `deactivated` menjadi flash error dan redirect edit; `deleted` menjadi flash sukses. |
| `renderForm(?array $row, array $values, array $errors = [], int $status = 200): void` (private) | Baris, nilai, error, status | HTML | Merender `admin/alat/form` dengan `categories`, `isEdit`, `values`, dan `errors`. |
| `emptyValues(): array` (private) | Tidak ada | Nilai awal | `kode`, `nama`, `kategori_id`, `deskripsi`, `foto` kosong; `stok_total = 0`; `is_active = 1`; `hapus_foto = 0`. |
| `findOr404(string $id): ?array` (private) | ID route | Baris atau `null` | Memvalidasi `^[1-9]\d{0,18}$`, mencari baris, merender 404 bila gagal. |
| `notFound(): void` (private) | Tidak ada | HTML 404 | Menulis halaman 404 alat dan tautan kembali. |
| `pageParam(): int` (private) | `$_GET['page']` | Nomor halaman | Angka positif maksimal 7 digit; selain itu 1. |
| `actorId(): int` (private) | Sesi | ID admin | ID pengguna atau 0. |
| `render(string $template, array $data, int $status = 200): void` (private) | Template, data, status | HTML | Mengambil dan menghapus flash, lalu merender dengan kontrak view. |

### [`app/Models/Alat.php`](../../app/Models/Alat.php)

| Method | Input | Output | Perilaku aktual |
|---|---|---|---|
| `__construct(PDO $db, ActivityLog $logs, Kategori $kategori)` | PDO, audit, model kategori | Objek model | Menyimpan dependency. |
| `parse(array $input, ?int $id = null): array` | Input mentah dan ID opsional | `values`, `errors` | Menormalkan field, memvalidasi kode/nama/kategori/deskripsi/stok/status, memeriksa kode unik, dan memvalidasi keberadaan kategori. |
| `savePhoto(?array $file, ?string $existing = null): array` | Entri `$_FILES` dan nama lama opsional | `[nama|null, error|null]` | Memvalidasi upload, tipe isi, ukuran, direktori; menyimpan dengan nama acak; menghapus `$existing` bila diberikan. |
| `deletePhotoFile(?string $filename): void` | Nama berkas | Tidak ada | Hanya menghapus nama yang cocok regex 32 hex + ekstensi; mengabaikan nama lain dan kegagalan unlink. |
| `detectImageMime(string $path): ?string` (private) | Path berkas | MIME atau `null` | Memakai `finfo_file()` bila tersedia, fallback `getimagesize()`. |
| `uploadDir(): string` (private) | Tidak ada | Path direktori | `dirname(__DIR__, 2) . '/public/uploads/alat'`. |
| `paginate(string $q, int $page): array` | Pencarian dan halaman | `items`, `total`, `totalPages` | Pencarian kode/nama, join kategori, hitung `stok_dipinjam`, urut nama, 10 baris per halaman. |
| `catalog(string $q, ?int $kategoriId, int $page): array` | Pencarian, kategori opsional, halaman | `items`, `total`, `totalPages` | Hanya `is_active = 1`; filter kode/nama dan kategori; menampilkan `stok_tersedia`; 10 baris per halaman. |
| `find(int $id): ?array` | ID | Baris atau `null` | Mengambil data alat termasuk `stok_dipinjam` hasil selisih. |
| `create(array $values, int $actorId): int` | Nilai valid dan aktor | ID baru | Insert dengan `stok_tersedia = stok_total`, audit `create`, commit. |
| `update(int $id, array $values, int $actorId): ?array` | ID, nilai valid, aktor | Stok hasil update atau `null` | Lock baris, hitung unit dipinjam, tolak total kurang, update total dan `stok_tersedia`, audit, commit. |
| `delete(int $id, int $actorId): string` | ID dan aktor | `deleted`, `deactivated`, atau `missing` | Lock baris; bila ada `peminjaman`, nonaktifkan dan audit `deactivate`; bila tidak, hapus dan audit `delete`, lalu hapus foto. |
| `categories(): array` | Tidak ada | Daftar `id`, `nama` | Query semua kategori urut nama dan ID. |
| `positiveId(mixed $value): ?int` | Nilai input | ID positif atau `null` | Menerima int `> 0` atau string `^[1-9]\d{0,18}$`; menolak `0`, negatif, `1abc`, dan string kosong. |
| `codeTaken(string $kode, ?int $id): bool` (private) | Kode dan ID opsional | `true`/`false` | Menghitung kode, mengecualikan ID saat edit. |
| `translate(PDOException $exception): Throwable` (private) | Exception PDO | Exception asli atau `DomainException` | SQLSTATE `23000`: `uq_alat_kode`, `fk_alat_kategori`, atau pesan integritas umum. |

## 10. Pemetaan pengujian yang ada

Belum ada pengujian yang dijalankan untuk dokumen ini.

| Berkas pengujian | Cakupan yang terlihat dari isi berkas |
|---|---|
| [`tests/http_master_data_test.php`](../../tests/http_master_data_test.php) | Akses role dan CSRF alat, daftar/pencarian/pagination, tambah/ubah/hapus, validasi stok, validasi field, audit, unggahan PNG valid, penolakan payload PHP menyamar sebagai PNG, PNG rusak, berkas di atas 2 MB, nama acak, penggantian foto, penghapusan foto, dan penolakan traversal pada `deletePhotoFile()`. |
| [`tests/http_loans_test.php`](../../tests/http_loans_test.php) | Katalog alat aktif, penyembunyian alat nonaktif, dan interaksi stok dengan peminjaman. |
| [`tests/security_test.php`](../../tests/security_test.php) | Route privat, pembatasan role, dan kontrak keamanan inti. |
| [`tests/database/schema_test.php`](../../tests/database/schema_test.php) | Unique `alat.kode`, check `stok_tersedia <= stok_total`, dan foreign key kategori dengan restrict. |

## 11. Catatan dan risiko

- Foto tidak di-recode dan tidak memiliki batas dimensi. Validasi memeriksa MIME isi, ukuran, dan nama acak, tetapi bukan dimensi gambar atau metadata EXIF.
- Berkas lama dihapus setelah commit. Bila proses berhenti tepat setelah commit dan sebelum `unlink`, akan ada berkas yatim di `public/uploads/alat`.
- `deletePhotoFile()` menekan kegagalan `unlink`; database tetap dianggap berhasil.
- Alat yang dinonaktifkan karena memiliki riwayat peminjaman tetap menyimpan fotonya.
- Nama berkas yang tersimpan di database harus tetap berupa nama 32 hex + ekstensi yang dihasilkan server. Jangan mengubahnya menjadi path dari input pengguna.
- Blok Nginx `location ^~ /uploads/` dan regex deny bersarang adalah batas eksekusi. Jangan melemahkannya.
- Menjalankan `php -S -t public` tanpa `public/router.php` tidak memberi jaminan penyajian statis untuk unggahan. Gunakan perintah dev server proyek atau Nginx.
- Batas unggahan aplikasi 2 MB, sedangkan Nginx menerima body hingga 8 MB; permintaan lebih besar dapat ditolak Nginx sebelum mencapai aplikasi.
- Perbandingan kode alat mengikuti collation database `utf8mb4_unicode_ci`, sehingga pemeriksaan unik tidak membedakan huruf besar/kecil.
