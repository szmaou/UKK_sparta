# 06 — Peminjaman dan Persetujuan

Dokumen ini menjelaskan katalog peminjam, pengajuan peminjaman, riwayat milik peminjam, antrean persetujuan Petugas, serta koreksi dan penghapusan administratif oleh Admin. Isi mengikuti kode pada working tree saat ini. Detail method katalog alat yang dipakai bersama ada di [05 — Alat](05-alat.md) dan tidak diulang di sini.

## 1. Tanggung jawab

- Menampilkan alat aktif kepada Peminjam melalui katalog yang dapat dicari dan difilter.
- Membuat pengajuan berstatus `pending` dengan identitas peminjam dari sesi, bukan dari input request.
- Menjaga agar pengajuan `pending` tidak mengurangi stok.
- Menyetujui atau menolak pengajuan Petugas secara transaksional.
- Mengurangi `alat.stok_tersedia` tepat satu kali saat persetujuan berhasil.
- Menyediakan riwayat peminjaman milik Peminjam dan daftar seluruh peminjaman untuk Admin.
- Membatasi koreksi Admin pada pengajuan `pending`, dan penghapusan pada baris `pending` atau `rejected`.
- Mencatat audit aplikasi untuk pembuatan, koreksi, penghapusan, dan catatan penolakan.
- Menyerahkan audit perubahan status peminjaman kepada trigger database.

## 2. Aktor

| Aktor | Kewenangan pada modul ini |
|---|---|
| Peminjam | Membuka katalog, membuka form pengajuan, mengirim pengajuan, dan melihat riwayat miliknya. |
| Petugas | Melihat antrean `pending`, menyetujui, atau menolak dengan catatan opsional. |
| Admin | Melihat semua peminjaman, mengoreksi pengajuan `pending`, dan menghapus pengajuan `pending` atau `rejected`. |
| Sistem/database | Mengunci baris saat keputusan, menjaga constraint, menghitung audit status, dan menyimpan `activity_logs`. |
| Pengunjung | Ditolak oleh front controller sebelum controller dijalankan. |

## 3. Rute dan kontrak

Semua route di bawah bersifat privat. Front controller memeriksa autentikasi dan role sebelum handler dipanggil. Setiap `POST` juga wajib menyertakan `_token` CSRF yang valid; token salah atau tidak ada menghasilkan `403` tanpa menjalankan controller.

| Rute | Metode | Handler | Role | Kontrak input | Hasil |
|---|---|---|---|---|---|
| `/peminjam/alat` | GET | `KatalogController::index` | `peminjam` | `q` opsional; `kategori_id` opsional harus ID positif; `page` opsional 1–7 digit. | `200` katalog alat aktif; filter tidak valid diabaikan atau halaman kembali ke 1. |
| `/peminjam/peminjaman` | GET | `PeminjamanController::index` | `peminjam` | `page` opsional 1–7 digit. | `200` riwayat peminjaman milik pengguna sesi. |
| `/peminjam/peminjaman/create` | GET | `PeminjamanController::create` | `peminjam` | `alat_id` wajib ID positif, ada, dan `is_active = 1`. | `200` form pengajuan; `404` bila alat tidak ada atau nonaktif. |
| `/peminjam/peminjaman/create` | POST | `PeminjamanController::store` | `peminjam` | `_token`, `alat_id`, `jumlah`, `tanggal_pinjam`, `tanggal_jatuh_tempo`, `tujuan`. | `302` ke `/peminjam/peminjaman`; `422` form dengan error; `404` bila alat tidak ditemukan. |
| `/petugas/persetujuan` | GET | `ApprovalController::index` | `petugas` | `page` opsional 1–7 digit. | `200` antrean `pending`, urut paling lama. |
| `/petugas/peminjaman/{id}/approve` | POST | `ApprovalController::approve` | `petugas` | `_token`; `id` 1–19 digit dan harus ada. | `302` ke antrean; `404` bila tidak ada; kegagalan domain ditampilkan sebagai flash. |
| `/petugas/peminjaman/{id}/reject` | POST | `ApprovalController::reject` | `petugas` | `_token`; `id` 1–19 digit; `catatan` opsional maksimal 1.000 karakter. | `302` ke antrean; `404` bila tidak ada; catatan terlalu panjang membatalkan keputusan dengan flash. |
| `/admin/peminjaman` | GET | `AdminPeminjamanController::index` | `admin` | `q` opsional; `status` harus salah satu `''`, `pending`, `approved`, `rejected`, `return_requested`, `returned`; `page` opsional. | `200` daftar seluruh status; status di luar allowlist menjadi tanpa filter. |
| `/admin/peminjaman/{id}/edit` | GET | `AdminPeminjamanController::edit` | `admin` | `id` 1–19 digit dan harus ada. | `200` form koreksi; `404` bila tidak ada. Form tetap dirender untuk status apa pun, tetapi penyimpanan hanya boleh untuk `pending`. |
| `/admin/peminjaman/{id}/edit` | POST | `AdminPeminjamanController::update` | `admin` | `_token`; `id`; `jumlah`, `tanggal_pinjam`, `tanggal_jatuh_tempo`, `catatan`. | `302` bila berhasil; `422` bila validasi atau stok gagal; `409` bila status bukan `pending`; `404` bila tidak ada. |
| `/admin/peminjaman/{id}/delete` | POST | `AdminPeminjamanController::destroy` | `admin` | `_token`; `id` 1–19 digit dan harus ada. | `302` bila terhapus; `409` bila status bukan `pending`/`rejected`; `404` bila tidak ada. |

Tidak ada route Admin untuk membuat peminjaman. Pencarian `create` atau `store` pada `AdminPeminjamanController` tidak menemukan method, dan `app/routes.php` hanya mendaftarkan daftar, edit, dan delete untuk `/admin/peminjaman`.

## 4. Dependensi

| Dependensi | Peran aktual |
|---|---|
| [`app/bootstrap.php`](../../app/bootstrap.php) | Merakit `Alat`, `Peminjaman`, `PeminjamanService`, `Auth`, `Session`, `View`, dan controller secara manual. |
| [`app/Models/Alat.php`](../../app/Models/Alat.php) | Menyediakan `positiveId()`, `find()`, `catalog()`, dan `categories()`. Detail katalog dan master alat ada di [05 — Alat](05-alat.md). |
| [`app/Services/Auth.php`](../../app/Services/Auth.php) | Menyediakan pengguna sesi untuk `actorId()` dan tampilan role. |
| [`app/Core/Session.php`](../../app/Core/Session.php) | Menyimpan flash message dan token CSRF. |
| [`app/Core/View.php`](../../app/Core/View.php) | Merender template dengan status HTTP. |
| [`app/Models/ActivityLog.php`](../../app/Models/ActivityLog.php) | Menulis `activity_logs` untuk aksi aplikasi. |
| [`app/Models/Peminjaman.php`](../../app/Models/Peminjaman.php) | Query, transaksi, row lock, mutasi status, dan mutasi stok. |
| [`app/Services/PeminjamanService.php`](../../app/Services/PeminjamanService.php) | Validasi input, normalisasi nilai, dan delegasi pembuatan pengajuan. |
| [`database/schema.sql`](../../database/schema.sql) | Tabel `peminjaman`, `alat`, `users`, dan `activity_logs`; constraint jumlah, tanggal, status, kode unik, serta foreign key. |
| [`database/routines.sql`](../../database/routines.sql) | Trigger `trg_peminjaman_audit_update` yang menulis audit `status_change`. |
| [`app/Views/peminjam/alat/index.php`](../../app/Views/peminjam/alat/index.php) | Katalog dan kontrol pencarian/filter. |
| [`app/Views/peminjam/peminjaman/form.php`](../../app/Views/peminjam/peminjaman/form.php) | Form pengajuan dan ringkasan alat terpilih. |
| [`app/Views/peminjam/peminjaman/index.php`](../../app/Views/peminjam/peminjaman/index.php) | Riwayat peminjaman Peminjam. |
| [`app/Views/petugas/persetujuan/index.php`](../../app/Views/petugas/persetujuan/index.php) | Antrean persetujuan dan aksi setuju/tolak. |
| [`app/Views/admin/peminjaman/index.php`](../../app/Views/admin/peminjaman/index.php) | Daftar, pencarian, dan filter Admin. |
| [`app/Views/admin/peminjaman/form.php`](../../app/Views/admin/peminjaman/form.php) | Form koreksi dan tombol hapus. |

Katalog memakai `Alat::positiveId()` untuk menolak `0`, angka negatif, dan string bercampur huruf. `Alat::catalog()` hanya mengambil `is_active = 1`, mencari `kode` atau `nama` maksimal 100 karakter, memfilter `kategori_id`, dan memakai halaman 10 baris. `KatalogController` tidak menggandakan aturan tersebut; method dan tampilan katalog dijelaskan pada modul 05.

## 5. Alur proses

| Proses | Input | Proses | Output | Validasi dan kegagalan | Efek transaksi, stok, dan audit |
|---|---|---|---|---|---|
| Melihat katalog | `q`, `kategori_id`, `page` | `KatalogController` memvalidasi kategori dan halaman, memanggil `Alat::catalog()` dan `Alat::categories()`, lalu merender katalog. | `200` daftar alat aktif beserta kategori dan stok tersedia. | Kategori tidak valid menjadi tanpa filter; halaman tidak valid menjadi 1. Tidak ada write. | Tidak ada transaksi, perubahan stok, atau audit. |
| Membuka form pengajuan | `alat_id` | `PeminjamanController::create()` memvalidasi ID, mengambil alat, memastikan aktif, lalu menyiapkan nilai awal. | `200` form berisi alat, jumlah `1`, tanggal pinjam hari ini, jatuh tempo `+7` hari, dan tujuan kosong. | `404` untuk ID tidak valid, alat tidak ada, atau `is_active != 1`. | Read-only; stok dan audit tidak berubah. |
| Mengirim pengajuan | Field form dan pengguna sesi | `PeminjamanService::validate()` memeriksa alat, jumlah, tanggal, tujuan, status aktif, dan stok. Bila bebas error, `PeminjamanService::create()` memanggil `Peminjaman::create()`. | `302` ke riwayat dengan flash sukses; `422` form dengan nilai dan error; `404` bila alat hilang. | Alat harus ada dan aktif; jumlah bilangan bulat 1–4.294.967.295 dan tidak melebihi `stok_tersedia`; tanggal valid; jatuh tempo tidak sebelum tanggal pinjam; tujuan wajib dan maksimal 1.000 karakter. | `Peminjaman::create()` membuka transaksi, menyisipkan `pending`, menulis `activity_logs` aksi `create`, lalu commit. Stok tidak berubah dan status belum berubah sehingga trigger tidak menulis `status_change`. |
| Melihat riwayat sendiri | Pengguna sesi, `page` | `Peminjaman::history()` memfilter `p.user_id` dan merender seluruh status milik pengguna. | `200` riwayat terbaru lebih dahulu. | Kepemilikan berasal dari sesi; parameter `user_id` dari request tidak dibaca. | Read-only. |
| Melihat antrean persetujuan | `page` | `Peminjaman::pending()` mengambil `status = 'pending'` dengan data peminjam, alat, dan `stok_tersedia`. | `200` antrean urut `created_at` paling lama. | Tidak ada input domain selain halaman. | Read-only. |
| Menyetujui | ID pengajuan, Petugas sesi | `ApprovalController::decide()` memanggil `Peminjaman::decide()`. Model mengunci alat lalu peminjaman, memeriksa status dan stok, mengurangi stok, dan mengubah status. | `302` ke antrean dan flash sukses atau error. | `404` bila ID/pengajuan tidak ada; `not_pending`, `inactive`, atau `insufficient` tidak mengubah data. | Satu transaksi: row lock `alat` lalu `peminjaman`; `stok_tersedia = stok_tersedia - jumlah` dengan guard `stok_tersedia >= jumlah`; status menjadi `approved`, `approved_by` dan `approved_at` diisi; trigger menulis `status_change`; commit. Gagal guard berarti rollback penuh. |
| Menolak | ID pengajuan, Petugas sesi, `catatan` opsional | `PeminjamanService::note()` menormalisasi catatan; `Peminjaman::decide()` mengubah status menjadi `rejected` tanpa menyentuh stok. | `302` ke antrean dan flash. | Catatan kosong menjadi `null`; lebih dari 1.000 karakter melempar `DomainException`, controller menampilkan flash error dan tidak memanggil keputusan. `404` bila pengajuan tidak ada; `not_pending` bila sudah diproses. | Satu transaksi dengan row lock alat lalu peminjaman. `catatan` milik peminjam tidak ditimpa. Bila ada catatan penolakan, aplikasi menulis `activity_logs` aksi `reject_note`; trigger menulis `status_change`. Stok tetap. |
| Daftar Admin | `q`, `status`, `page` | `Peminjaman::adminPaginate()` membangun filter status dan pencarian kode/nama peminjam/nama alat. | `200` daftar semua status, terbaru lebih dahulu. | `status` di luar allowlist menjadi `''`; `q` dipotong 100 karakter di model. | Read-only. |
| Membuka koreksi Admin | `id` | `findOr404()` memvalidasi ID dan memanggil `Peminjaman::adminFind()`. Controller menyiapkan nilai form dari baris saat ini. | `200` form koreksi; `404` bila tidak ada. | ID wajib 1–19 digit dan ada. Status tidak diperiksa pada GET, sehingga form dapat terbuka untuk baris non-`pending`. | Read-only. |
| Menyimpan koreksi Admin | `jumlah`, dua tanggal, `catatan`, Admin sesi | Controller memastikan status `pending`, memvalidasi input, lalu memanggil `Peminjaman::adminUpdate()`. | `302` bila berhasil; `422` bila validasi/stok gagal; `409` bila status berubah; `404` bila hilang. | Hanya `pending`; jumlah 1–4.294.967.295 dan tidak melebihi stok tersedia saat baris alat dikunci; tanggal valid dan urut; catatan maksimal 1.000 karakter; alat, pemilik, status, dan metadata persetujuan tidak dapat diubah dari form. | Transaksi mengunci alat lalu peminjaman. Hanya `jumlah`, `tanggal_pinjam`, `tanggal_jatuh_tempo`, dan `catatan` yang diubah. Tidak ada perubahan stok atau status. Aplikasi menulis `activity_logs` aksi `update`; trigger tidak menulis `status_change`. |
| Menghapus Admin | `id`, Admin sesi | `Peminjaman::adminDelete()` mengunci baris, memeriksa status, lalu menghapus permanen. | `302` bila terhapus; `409` bila status tidak boleh dihapus; `404` bila tidak ada. | Hanya `pending` atau `rejected`. Baris `approved`, `return_requested`, dan `returned` dipertahankan sebagai riwayat. | Transaksi: row lock, `DELETE FROM peminjaman`, tulis `activity_logs` aksi `delete`, commit. Stok tidak berubah. Trigger audit hanya bereaksi pada `UPDATE`, sehingga tidak ada `status_change` dari penghapusan. |

## 6. Kontrak administratif create, edit, dan delete

| Operasi | Kontrak aktual | Batasan |
|---|---|---|
| Create Admin | Tidak tersedia. Tidak ada route, method controller, form, atau service khusus untuk Admin membuat peminjaman. | Peminjaman hanya lahir dari `POST /peminjam/peminjaman/create` oleh Peminjam; Admin tidak dapat memilih pemilik atau membuat baris langsung dari UI. |
| Edit form Admin | `GET /admin/peminjaman/{id}/edit`, role `admin`, `id` 1–19 digit dan ada. | `404` untuk ID tidak valid atau tidak ada. Form menampilkan kode, peminjam, alat, stok tersedia, jumlah, tanggal pinjam, jatuh tempo, dan catatan. GET tidak menolak status non-`pending`. |
| Update Admin | `POST /admin/peminjaman/{id}/edit`, wajib CSRF. Body hanya `jumlah`, `tanggal_pinjam`, `tanggal_jatuh_tempo`, `catatan`. | Status harus `pending`; bila bukan, `409` sebelum validasi body. Jumlah harus lolos format dan `<= stok_tersedia` yang dikunci; tanggal harus valid dan jatuh tempo `>=` tanggal pinjam; catatan boleh kosong dan maksimal 1.000 karakter. `catatan` menggantikan isi lama, bukan menambahkan riwayat. Tidak ada perubahan pemilik, alat, status, `approved_by`, atau `approved_at`. |
| Delete Admin | `POST /admin/peminjaman/{id}/delete`, wajib CSRF. | Hanya `pending` atau `rejected`. Selain itu `409` dan baris tetap ada. Penghapusan bersifat fisik dan menghapus riwayat baris tersebut. Stok tidak dikoreksi karena kedua status tersebut tidak pernah memotong stok. |

## 7. Aturan transaksi, stok, dan audit

- Pengajuan `pending` tidak mencadangkan stok. Dua pengajuan berbeda dapat menunggu untuk unit yang sama; keputusan Petugas yang menentukan ketersediaan aktual.
- Persetujuan mengunci baris `alat` lebih dulu, lalu baris `peminjaman`. Urutan ini sama dengan operasi alat lain dan mengurangi risiko deadlock.
- Stok dikurangi dengan `UPDATE ... WHERE stok_tersedia >= jumlah`. Bila `rowCount()` bukan 1, transaksi rollback dan hasilnya `insufficient`; stok tidak boleh negatif.
- Persetujuan memeriksa `is_active = 1`. Penolakan tetap boleh dilakukan meskipun alat sudah nonaktif.
- Keputusan hanya berlaku untuk status `pending`. Kondisi ini diperiksa setelah row lock, sehingga dua Petugas yang bersamaan tidak dapat mengurangi stok dua kali.
- Trigger `trg_peminjaman_audit_update` memiliki audit perubahan status `peminjaman`. Aplikasi hanya menetapkan `@sparta_actor_id` sebelum `UPDATE` dan tidak menyisipkan baris `status_change` sendiri. Variabel sesi dibersihkan di blok `finally`.
- Audit aplikasi untuk pembuatan, koreksi, penghapusan, dan catatan penolakan ditulis melalui `ActivityLog::record()` atau `INSERT` langsung oleh model. Catatan penolakan disimpan sebagai baris `activity_logs` terpisah agar `peminjaman.catatan` milik peminjam tidak tertimpa.
- Koreksi Admin terhadap pengajuan `pending` tidak mengubah stok. Alasannya, stok belum pernah dikurangi pada status tersebut.
- Penghapusan `pending`/`rejected` tidak mengubah stok. Penghapusan baris yang sudah memotong stok ditolak.
- Semua kegagalan guard di dalam transaksi melakukan `rollBack()` sebelum mengembalikan kode hasil. Exception juga memicu rollback dan diteruskan ke front controller.

## 8. Validasi dan hasil kegagalan

| Pemeriksaan | Aturan aktual | Dampak |
|---|---|---|
| `alat_id` pengajuan | `Alat::positiveId()` menerima bilangan positif atau string 1–19 digit; `Alat::find()` harus menemukan baris. | Error `Alat tidak ditemukan.`; POST dengan alat tidak valid menjadi `404`. |
| Status alat | `is_active` harus `1`. | Error `Alat sedang nonaktif dan tidak dapat diajukan.`. |
| `jumlah` | String `^[1-9]\d{0,9}$`, nilai `<= 4294967295`, dan `<= stok_tersedia`. | Error `Jumlah harus bilangan bulat lebih dari 0.` atau pesan stok maksimum. |
| Tanggal pengajuan | Format `YYYY-MM-DD` dan tanggal kalender benar. | Error `Tanggal pinjam tidak valid.` atau `Tanggal jatuh tempo tidak valid.`. |
| Urutan tanggal | `tanggal_jatuh_tempo >= tanggal_pinjam`. | Error `Jatuh tempo tidak boleh sebelum tanggal pinjam.`. |
| `tujuan` | Wajib, tidak kosong, maksimal 1.000 karakter. | Error `Tujuan peminjaman wajib diisi.` atau `Tujuan maksimal 1000 karakter.`. |
| `catatan` koreksi Admin | Opsional, maksimal 1.000 karakter; kosong menjadi `null`. | Error `Catatan maksimal 1000 karakter.`. |
| `catatan` penolakan | Opsional, maksimal 1.000 karakter. | `DomainException` ditangkap controller, flash error, status pengajuan tidak berubah. |
| Status koreksi | Harus `pending` saat `adminUpdate` mengunci baris. | `409` dengan pesan bahwa perubahan setelah persetujuan harus melalui alur Petugas. |
| Status hapus | Harus `pending` atau `rejected`. | `409` dengan pesan bahwa peminjaman yang sudah disetujui tidak dapat dihapus. |
| Hasil keputusan | `approved`, `rejected`, `missing`, `not_pending`, `inactive`, `insufficient`. | `missing` menjadi `404`; hasil lain menjadi flash sukses/error dan redirect. |

## 9. Inventaris method

### [`app/Controllers/KatalogController.php`](../../app/Controllers/KatalogController.php)

| Method | Input | Proses | Output |
|---|---|---|---|
| `__construct(Auth $auth, Session $session, View $view, Alat $alat)` | Dependency layanan | Menyimpan dependency untuk katalog. | Objek controller. |
| `index(): void` | `$_GET['q']`, `kategori_id`, `page` | Membersihkan kategori/halaman, memanggil `Alat::catalog()` dan `Alat::categories()`, lalu `render()`. | `200` katalog alat aktif. |
| `pageParam(): int` (private) | `$_GET['page']` | Menerima hanya string 1–7 digit; nilai lain menjadi 1. | Nomor halaman. |
| `render(string $template, array $data, int $status = 200): void` (private) | Template, data, status | Mengambil flash, menghapusnya, menambahkan user/role/CSRF, memanggil `View::render()`. | Response HTML. |

### [`app/Controllers/PeminjamanController.php`](../../app/Controllers/PeminjamanController.php)

| Method | Input | Proses | Output |
|---|---|---|---|
| `__construct(Auth $auth, Session $session, View $view, Alat $alat, Peminjaman $loans, PeminjamanService $service)` | Dependency | Menyimpan dependency pengajuan. | Objek controller. |
| `index(): void` | Pengguna sesi, `page` | Memanggil `Peminjaman::history()` untuk pemilik sesi. | `200` riwayat sendiri. |
| `create(): void` | `$_GET['alat_id']` | Memvalidasi ID, mengambil alat, menolak alat tidak ada/nonaktif, menyiapkan nilai awal. | `200` form; `404` untuk alat tidak valid/nonaktif. |
| `store(): void` | `$_POST` | Memanggil `PeminjamanService::validate()`, menangani `404`/`422`, lalu `create()` dengan pemilik dan aktor dari sesi. | `302` ke riwayat atau form error. |
| `renderForm(array $tool, array $values, array $errors = [], int $status = 200): void` (private) | Alat, nilai, error, status | Merender template form pengajuan. | Response form. |
| `pageParam(): int` (private) | `$_GET['page']` | Regex halaman 1–7 digit; selain itu 1. | Nomor halaman. |
| `actorId(): int` (private) | `Auth::user()` | Mengambil ID pengguna sesi; `null` menjadi 0. | ID aktor. |
| `notFound(): void` (private) | Tidak ada | Mengirim `404` dan HTML alat tidak ditemukan. | Response `404`. |
| `render(string $template, array $data, int $status = 200): void` (private) | Template, data, status | Mengambil flash, menambahkan data sesi, merender view. | Response HTML. |

### [`app/Controllers/ApprovalController.php`](../../app/Controllers/ApprovalController.php)

| Method | Input | Proses | Output |
|---|---|---|---|
| `__construct(Auth $auth, Session $session, View $view, Peminjaman $loans, PeminjamanService $service)` | Dependency | Menyimpan dependency persetujuan. | Objek controller. |
| `index(): void` | `page` | Memanggil `Peminjaman::pending()`. | `200` antrean pending. |
| `approve(string $id): void` | ID route | Memanggil `decide($id, 'approved', null)`. | Redirect dan flash. |
| `reject(string $id): void` | ID route, `$_POST['catatan']` | Menormalisasi catatan; `DomainException` menjadi flash error; lalu `decide(..., 'rejected', $note)`. | Redirect dan flash; keputusan dibatalkan bila catatan tidak valid. |
| `decide(string $id, string $decision, ?string $note): void` (private) | ID, keputusan, catatan | Validasi ID, panggil `Peminjaman::decide()`, petakan hasil ke pesan flash, redirect. | `404` atau redirect ke antrean. |
| `pageParam(): int` (private) | `page` | Regex 1–7 digit; selain itu 1. | Nomor halaman. |
| `actorId(): int` (private) | Pengguna sesi | Mengambil ID Petugas; `null` menjadi 0. | ID aktor. |
| `notFound(): void` (private) | Tidak ada | Mengirim `404` halaman pengajuan tidak ditemukan. | Response `404`. |
| `render(string $template, array $data, int $status = 200): void` (private) | Template, data, status | Mengambil flash, menambahkan data sesi, merender view. | Response HTML. |

### [`app/Controllers/AdminPeminjamanController.php`](../../app/Controllers/AdminPeminjamanController.php)

| Method | Input | Proses | Output |
|---|---|---|---|
| `__construct(Auth $auth, Session $session, View $view, Peminjaman $loans, PeminjamanService $service)` | Dependency | Menyimpan dependency koreksi Admin. | Objek controller. |
| `index(): void` | `q`, `status`, `page` | Memvalidasi status terhadap `STATUSES`, memanggil `adminPaginate()`. | `200` daftar semua peminjaman. |
| `edit(string $id): void` | ID route | `findOr404()`, menyiapkan nilai form dari baris. | `200` form; `404` bila tidak ada. |
| `update(string $id): void` | ID route, `$_POST` | `findOr404()`, cek `pending`, `validateAdmin()`, panggil `adminUpdate()`, petakan hasil. | `302`, `409`, `422`, atau `404`. |
| `destroy(string $id): void` | ID route | `findOr404()`, panggil `adminDelete()`, petakan hasil. | `302`, `409`, atau `404`. |
| `findOr404(string $id): ?array` (private) | ID route | Regex 1–19 digit, lalu `adminFind()`; gagal memicu `notFound()`. | Baris atau `null`. |
| `renderForm(array $loan, array $values, array $errors = [], int $status = 200): void` (private) | Baris, nilai, error, status | Merender form koreksi dengan tanggal hari ini. | Response form. |
| `pageParam(): int` (private) | `page` | Regex 1–7 digit; selain itu 1. | Nomor halaman. |
| `actorId(): int` (private) | Pengguna sesi | Mengambil ID Admin; `null` menjadi 0. | ID aktor. |
| `notFound(): void` (private) | Tidak ada | `abort(404, ...)`. | Response `404`. |
| `conflict(string $message): void` (private) | Pesan | `abort(409, 'Status tidak valid', $message)`. | Response `409`. |
| `abort(int $status, string $title, string $message): void` (private) | Status, judul, pesan | Mengirim status dan HTML error dengan tautan kembali. | Response error. |
| `render(string $template, array $data, int $status = 200): void` (private) | Template, data, status | Mengambil flash, menambahkan data sesi, merender view. | Response HTML. |

### [`app/Models/Peminjaman.php`](../../app/Models/Peminjaman.php)

| Method | Input | Proses | Output |
|---|---|---|---|
| `__construct(PDO $db, ActivityLog $logs)` | Koneksi dan audit | Menyimpan dependency. | Objek model. |
| `create(int $userId, array $values, int $actorId): array` | Pemilik, nilai tervalidasi, aktor | Membuka transaksi, membuat kode acak `PJM-yymmdd-` + 12 hex, menyisipkan `pending`, menulis audit `create`, commit. | `['id' => int, 'kode' => string]`. |
| `history(int $userId, int $page): array` | Pemilik, halaman | Query semua status milik pemilik, join alat, urut terbaru, pagination. | `items`, `total`, `totalPages`. |
| `pending(int $page): array` | Halaman | Query status `pending`, join peminjam/alat, urut terlama, pagination. | `items`, `total`, `totalPages`. |
| `decide(int $id, string $decision, int $actorId, ?string $note = null): string` | ID, keputusan, aktor, catatan | Lookup alat; transaksi; lock alat lalu peminjaman; validasi status/stok; set aktor trigger; setujui/tolak; commit. | `approved`, `rejected`, `missing`, `not_pending`, `inactive`, atau `insufficient`. |
| `adminPaginate(string $q, string $status, int $page): array` | Pencarian, status, halaman | Query dinamis semua status, cari kode/nama, urut terbaru, pagination. | `items`, `total`, `totalPages`. |
| `adminFind(int $id): ?array` | ID | Join peminjaman, peminjam, dan alat. | Baris atau `null`. |
| `adminUpdate(int $id, array $values, int $actorId): string` | ID, nilai tervalidasi, aktor | Transaksi; lock alat lalu peminjaman; cek pending dan stok; update jumlah/tanggal/catatan; audit; commit. | `updated`, `missing`, `not_pending`, atau `insufficient`. |
| `adminDelete(int $id, int $actorId): string` | ID, aktor | Transaksi; lock baris; cek status; hapus; audit; commit. | `deleted`, `missing`, atau `not_deletable`. |
| `paginate(string $select, string $countSql, array $params, int $page): array` (private) | SQL, parameter, halaman | Menghitung total dan mengambil halaman `PER_PAGE` (10). | `items`, `total`, `totalPages`. |

### [`app/Services/PeminjamanService.php`](../../app/Services/PeminjamanService.php)

| Method | Input | Proses | Output |
|---|---|---|---|
| `__construct(Alat $alat, Peminjaman $loans)` | Model alat dan peminjaman | Menyimpan dependency. | Objek service. |
| `validate(array $input): array` | `$_POST` pengajuan | Normalisasi string, validasi alat, jumlah, tanggal, urutan, tujuan, status aktif, dan stok. | `values`, `errors`, `tool`. |
| `create(int $userId, array $values, int $actorId): array` | Pemilik, nilai, aktor | Cast nilai dan mendelegasikan ke `Peminjaman::create()`. | `id` dan `kode`. |
| `validateAdmin(array $input): array` | `$_POST` koreksi | Normalisasi jumlah/tanggal/catatan; validasi format, urutan, dan panjang catatan. | `values` dan `errors`; catatan kosong menjadi `null`. |
| `note(mixed $value): ?string` | Catatan penolakan | Trim; kosong menjadi `null`; lebih dari 1.000 karakter melempar `DomainException`. | `?string`. |
| `isDate(string $value): bool` (private, static) | String tanggal | Regex dan round-trip `DateTimeImmutable` dengan format `!Y-m-d`. | `true`/`false`. |

## 10. Pemetaan pengujian yang ada

Belum ada pengujian yang dijalankan untuk dokumen ini. Tabel hanya memetakan skenario yang terlihat pada berkas test saat ini.

| Berkas pengujian | Cakupan yang terlihat dari isi berkas |
|---|---|
| [`tests/http_loans_test.php`](../../tests/http_loans_test.php) | Role katalog dan antrean, pencarian/filter/pagination katalog, form pengajuan, validasi create, kepemilikan dari sesi, stok `pending` tidak berubah, audit create, approve/reject, stok berkurang tepat sekali, penolakan tanpa stok, stok kurang, alat nonaktif, dan persetujuan bersamaan. |
| [`tests/http_backoffice_test.php`](../../tests/http_backoffice_test.php) | Daftar/filter/cari Admin, form edit, validasi jumlah, CSRF, koreksi `pending`, penolakan koreksi `approved`, penghapusan `rejected`, penolakan hapus `approved`, dan audit koreksi. |
| [`tests/database/schema_test.php`](../../tests/database/schema_test.php) | Constraint `jumlah > 0`, urutan tanggal, kode unik, foreign key, dan trigger audit status. |
| [`tests/security_test.php`](../../tests/security_test.php) | Pencocokan parameter route, termasuk pola `/peminjam/peminjaman/{id}`; bukan pengujian alur bisnis modul. |
| [`tests/http_auth_test.php`](../../tests/http_auth_test.php) | Login dan pembatasan akses dashboard; tidak memeriksa stok atau persetujuan. |

## 11. Catatan dan risiko

- Label FR-06 menyebut CRUD, tetapi implementasi Admin saat ini tidak menyediakan create. Peminjaman hanya dibuat melalui alur Peminjam.
- `GET /admin/peminjaman/{id}/edit` tidak menolak status non-`pending`; penolakan baru terjadi pada POST. Form juga menampilkan tombol hapus untuk semua status, meskipun server menolak penghapusan baris yang tidak boleh dihapus.
- `Peminjaman::adminUpdate()` memeriksa `rowCount() === 1`. Pada MySQL, update tanpa perubahan nilai dapat menghasilkan `rowCount()` 0 sehingga controller menampilkan `409` seolah status berubah. Perilaku ini berasal dari konfigurasi PDO yang tidak memakai `MYSQL_ATTR_FOUND_ROWS`.
- Pesan `insufficient` pada controller memakai `stok_tersedia` dari baris yang dibaca sebelum model mengunci ulang. Nilai pada pesan dapat sedikit tertinggal, tetapi keputusan tetap memakai stok yang dikunci.
- Audit `status_change` bergantung pada import [`database/routines.sql`](../../database/routines.sql). Tanpa trigger tersebut, perubahan status tetap terjadi tetapi tidak menghasilkan baris audit status.
- Stok hanya berubah pada approve dan verify pengembalian. Koreksi atau penghapusan peminjaman tidak boleh dipakai untuk menyeimbangkan stok secara manual.
