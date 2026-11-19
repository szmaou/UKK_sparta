# 07 — Pengembalian

Dokumen ini menjelaskan pengajuan pengembalian oleh Peminjam, monitoring oleh Petugas, verifikasi penerimaan fisik, serta koreksi dan penghapusan administratif oleh Admin. Isi mengikuti kode pada working tree saat ini, termasuk aturan stok, denda, dan audit yang berlaku sekarang.

## 1. Tanggung jawab

- Menampilkan peminjaman milik Peminjam yang masih aktif atau sudah masuk alur pengembalian.
- Membuat satu pengajuan pengembalian penuh untuk peminjaman berstatus `approved`.
- Menjaga agar pengajuan pengembalian tidak mengubah stok sebelum verifikasi fisik.
- Menyediakan monitoring pengembalian untuk Petugas berdasarkan status dan pencarian.
- Memverifikasi tanggal penerimaan dan kondisi fisik dalam satu transaksi.
- Memulihkan stok hanya untuk kondisi `baik`.
- Menghitung hari terlambat dan denda dari data peminjaman, bukan dari input request.
- Memungkinkan Admin mengoreksi pengembalian yang sudah terverifikasi dan menghapus pengajuan yang belum terverifikasi.
- Menyerahkan audit perubahan status peminjaman kepada trigger database.

## 2. Aktor

| Aktor | Kewenangan pada modul ini |
|---|---|
| Peminjam | Melihat daftar pengembalian miliknya dan mengajukan pengembalian untuk peminjaman miliknya yang berstatus `approved`. |
| Petugas | Melihat monitoring, membuka form verifikasi, dan mencatat penerimaan fisik. |
| Admin | Melihat semua data pengembalian, mengoreksi pengembalian terverifikasi, dan menghapus pengajuan yang belum diverifikasi. |
| Sistem/database | Mengunci baris, menghitung denda melalui fungsi SQL, menjaga constraint, dan menulis audit status. |
| Pengunjung | Ditolak oleh front controller sebelum controller dijalankan. |

## 3. Rute dan kontrak

Semua route di bawah bersifat privat. Front controller memeriksa autentikasi dan role sebelum controller dipanggil. Setiap `POST` wajib menyertakan `_token` CSRF yang valid.

| Rute | Metode | Handler | Role | Kontrak input | Hasil |
|---|---|---|---|---|---|
| `/peminjam/pengembalian` | GET | `PengembalianController::index` | `peminjam` | `page` opsional 1–7 digit. | `200` daftar peminjaman milik pengguna sesi dengan status `approved`, `return_requested`, atau `returned`. |
| `/peminjam/peminjaman/{id}/return` | POST | `PengembalianController::requestReturn` | `peminjam` | `_token`; `id` adalah ID peminjaman, 1–19 digit. | `302` ke `/peminjam/pengembalian`; `404` bila peminjaman tidak ada; `403` bila bukan milik pengguna; `409` bila status bukan `approved` atau pengajuan duplikat. |
| `/petugas/pengembalian` | GET | `PengembalianController::monitoring` | `petugas` | `status` salah satu `active`, `return_requested`, `returned`, `all`; `q` opsional; `page` opsional. | `200` monitoring; `active` berarti `approved` + `return_requested`; status tidak valid kembali ke `active`. |
| `/petugas/pengembalian/{id}/verify` | GET | `PengembalianController::verifyForm` | `petugas` | `id` adalah ID peminjaman, 1–19 digit. | `200` form verifikasi; `404` bila peminjaman tidak ada; `409` bila bukan `return_requested`, belum ada pengajuan, atau sudah diterima. |
| `/petugas/pengembalian/{id}/verify` | POST | `PengembalianController::verifyStore` | `petugas` | `_token`; `id` ID peminjaman; `tanggal_diterima` format tanggal; `kondisi` salah satu `baik`, `rusak_ringan`, `rusak_berat`, `hilang`. | `302` ke monitoring; `422` untuk input tanggal/kondisi atau tanggal di luar batas; `409` untuk konflik status/stok; `404` bila tidak ada. |
| `/admin/pengembalian` | GET | `AdminPengembalianController::index` | `admin` | `q` opsional; `status` salah satu `''`, `pending`, `verified`; `page` opsional. | `200` daftar pengembalian; `pending` berarti `tanggal_diterima IS NULL`; `verified` berarti sudah diterima. |
| `/admin/pengembalian/{id}/edit` | GET | `AdminPengembalianController::edit` | `admin` | `id` adalah ID peminjaman, 1–19 digit. | `200` form koreksi bila sudah terverifikasi; `409` bila belum terverifikasi; `404` bila tidak ada. |
| `/admin/pengembalian/{id}/edit` | POST | `AdminPengembalianController::update` | `admin` | `_token`; `id` ID peminjaman; `tanggal_diterima`; `kondisi`. | `302` bila berhasil; `422` bila tanggal/kondisi tidak valid; `409` bila belum terverifikasi, tanggal di luar batas, atau stok bentrok; `404` bila tidak ada. |
| `/admin/pengembalian/{id}/delete` | POST | `AdminPengembalianController::destroy` | `admin` | `_token`; `id` ID peminjaman. | `302` bila pengajuan belum terverifikasi terhapus; `409` bila sudah terverifikasi atau status tidak sesuai; `404` bila tidak ada. |

Parameter `{id}` pada seluruh route pengembalian adalah ID baris `peminjaman`, bukan ID baris `pengembalian`. Model `Pengembalian::adminFind()`, `adminUpdate()`, dan `adminDelete()` memakai `peminjaman_id` sebagai kunci.

Tidak ada route Admin untuk membuat pengembalian. Baris `pengembalian` hanya dibuat melalui `POST /peminjam/peminjaman/{id}/return`.

## 4. Dependensi

| Dependensi | Peran aktual |
|---|---|
| [`app/bootstrap.php`](../../app/bootstrap.php) | Merakit `Pengembalian`, `Pengaturan`, `PengembalianService`, `Auth`, `Session`, `View`, dan controller. `PengembalianService` dibuat tanpa argumen. |
| [`app/Models/Pengaturan.php`](../../app/Models/Pengaturan.php) | Menyediakan `tarifDenda()` dari tabel `pengaturan`, kunci `tarif_denda`; nilai hilang atau tidak valid menjadi 0. Detail pengelolaan tarif ada di [11 — Pengaturan](11-pengaturan.md). |
| [`app/Models/ActivityLog.php`](../../app/Models/ActivityLog.php) | Menulis audit aplikasi. |
| [`app/Services/Auth.php`](../../app/Services/Auth.php) | Menyediakan identitas pengguna sesi. |
| [`app/Core/Session.php`](../../app/Core/Session.php) | Menyimpan flash message dan token CSRF. |
| [`app/Core/View.php`](../../app/Core/View.php) | Merender template dengan status HTTP. |
| [`app/Models/Pengembalian.php`](../../app/Models/Pengembalian.php) | Query, transaksi, row lock, mutasi pengembalian, mutasi stok, dan perhitungan hari terlambat. |
| [`app/Services/PengembalianService.php`](../../app/Services/PengembalianService.php) | Validasi tanggal dan kondisi, normalisasi filter, dan pembatasan panjang pencarian. |
| [`database/schema.sql`](../../database/schema.sql) | Tabel `pengembalian`, `peminjaman`, `alat`, dan `activity_logs`; unique `peminjaman_id`, check jumlah/denda, foreign key, dan enum kondisi. |
| [`database/routines.sql`](../../database/routines.sql) | Fungsi `fn_hitung_denda` dan trigger `trg_peminjaman_audit_update`. |
| [`app/Views/peminjam/pengembalian/index.php`](../../app/Views/peminjam/pengembalian/index.php) | Daftar pengembalian Peminjam dan tombol pengajuan. |
| [`app/Views/petugas/pengembalian/index.php`](../../app/Views/petugas/pengembalian/index.php) | Monitoring, filter status, pencarian, dan tautan verifikasi. |
| [`app/Views/petugas/pengembalian/form.php`](../../app/Views/petugas/pengembalian/form.php) | Form tanggal diterima, kondisi, ringkasan pinjaman, dan tarif denda saat ini. |
| [`app/Views/admin/pengembalian/index.php`](../../app/Views/admin/pengembalian/index.php) | Daftar dan filter data pengembalian Admin. |
| [`app/Views/admin/pengembalian/form.php`](../../app/Views/admin/pengembalian/form.php) | Form koreksi tanggal dan kondisi pengembalian terverifikasi. |

### Fungsi denda dan trigger

[`fn_hitung_denda`](../../database/routines.sql) adalah fungsi SQL `DETERMINISTIC` dengan empat parameter: tanggal jatuh tempo, tanggal kembali, jumlah, dan tarif. Fungsi mengembalikan `DECIMAL(12,2)`. Nilainya:

```text
GREATEST(0, DATEDIFF(tanggal_kembali, tanggal_jatuh_tempo))
    * GREATEST(0, jumlah)
    * GREATEST(0, tarif)
```

Aplikasi memanggil fungsi ini saat verifikasi dan koreksi Admin yang mengubah tanggal diterima. `hari_terlambat` dihitung terpisah di PHP memakai selisih hari kalender `DateTimeImmutable::diff()`; denda dihitung oleh database memakai `DATEDIFF`. `kondisi` tidak mengubah rumus denda.

Trigger `trg_peminjaman_audit_update` dimiliki oleh database dan berjalan `AFTER UPDATE ON peminjaman` hanya bila `OLD.status <> NEW.status`. Trigger membaca `@sparta_actor_id` dan menulis `activity_logs` dengan aksi `status_change`. Aplikasi tidak menulis baris `status_change` sendiri; aplikasi hanya menetapkan variabel sesi sebelum `UPDATE` dan membersihkannya di blok `finally`.

## 5. Alur proses

| Proses | Input | Proses | Output | Validasi dan kegagalan | Efek transaksi, stok, dan audit |
|---|---|---|---|---|---|
| Melihat pengembalian sendiri | Pengguna sesi, `page` | `Pengembalian::own()` mengambil peminjaman milik pengguna dengan status `approved`, `return_requested`, atau `returned`, join alat dan data pengembalian bila ada. | `200` daftar dengan status, tanggal pengajuan/penerimaan, dan denda. | Kepemilikan dari sesi, bukan input. Halaman tidak valid menjadi 1. | Read-only. |
| Mengajukan pengembalian | ID peminjaman, pengguna sesi | Controller memvalidasi ID. `Pengembalian::requestReturn()` mengunci peminjaman, memeriksa pemilik dan status, menyisipkan satu baris pengembalian, lalu mengubah status peminjaman. | `302` ke daftar sendiri dengan flash sukses. | `404` bila peminjaman tidak ada; `403` bila bukan milik pengguna; `409` bila status bukan `approved` atau baris pengembalian sudah ada. | Transaksi: row lock `peminjaman`; `INSERT pengembalian` dengan `tanggal_pengajuan = NOW()`, `jumlah` = jumlah pinjam, `kondisi = 'baik'` awal; status menjadi `return_requested`; trigger menulis `status_change`; commit. Stok tidak berubah. Duplikat ditangkap dari SQLSTATE `23000` dan di-rollback. |
| Melihat monitoring | `status`, `q`, `page` | `PengembalianService` menormalkan filter; `Pengembalian::monitoring()` memilih lane status dan mencari kode/nama peminjam/nama alat. | `200` daftar urut jatuh tempo paling awal. | `status` tidak valid menjadi `active`. `q` dipotong 100 karakter. | Read-only. |
| Membuka form verifikasi | ID peminjaman | Controller mengambil baris lewat `findForVerify()`, memastikan status `return_requested`, pengajuan ada, dan belum diterima, lalu menyiapkan tanggal hari ini dan kondisi `baik`. | `200` form verifikasi; `404` bila tidak ada; `409` bila tidak sedang menunggu verifikasi. | Status, `tanggal_pengajuan`, dan `tanggal_diterima` diperiksa di controller. | Read-only. |
| Memverifikasi penerimaan | ID peminjaman, tanggal diterima, kondisi, Petugas sesi | Controller mengulang precheck status, memvalidasi format input, lalu `Pengembalian::verify()` mengunci alat, peminjaman, dan baris pengembalian, menghitung hari terlambat/denda, memperbarui baris, memulihkan stok bila layak, dan mengubah status peminjaman. | `302` ke monitoring dengan flash sukses. | `422` untuk tanggal/kondisi tidak valid atau tanggal di luar batas; `409` untuk status/stok konflik; `404` bila tidak ada. | Transaksi dengan urutan lock `alat` → `peminjaman` → `pengembalian`. Baris pengembalian diisi `tanggal_diterima`, `jumlah`, `kondisi`, `hari_terlambat`, `denda`, `verified_by`. `stok_tersedia` bertambah hanya bila `restoredStock()` menghasilkan nilai > 0, yaitu kondisi `baik`. Status peminjaman menjadi `returned`; trigger menulis `status_change`; commit. Semua guard gagal berarti rollback penuh. |
| Daftar Admin | `q`, `status`, `page` | `PengembalianService::search()` membersihkan pencarian, controller memvalidasi status, `adminPaginate()` memfilter `pg.tanggal_diterima`. | `200` daftar pengembalian. | `status` di luar `''`, `pending`, `verified` menjadi tanpa filter. | Read-only. |
| Membuka koreksi Admin | ID peminjaman | `findOr404()` memanggil `adminFind()`, lalu controller menolak baris yang belum diterima. | `200` form koreksi; `409` bila belum terverifikasi; `404` bila tidak ada. | `tanggal_diterima` harus tidak `NULL`. | Read-only. |
| Menyimpan koreksi Admin | ID peminjaman, tanggal diterima, kondisi, Admin sesi | Controller memvalidasi tanggal/kondisi, lalu `Pengembalian::adminUpdate()` mengunci alat, peminjaman, dan pengembalian, memeriksa status `returned`, menghitung hari terlambat/denda baru hanya bila tanggal diterima berubah, memperbarui kondisi/tanggal, dan menyesuaikan stok berdasarkan delta. | `302` bila berhasil; `422` untuk input/tanggal; `409` untuk status/stok; `404` bila hilang. | Hanya pengembalian terverifikasi pada peminjaman `returned`; tanggal antara tanggal pinjam/pengajuan dan hari ini; kondisi harus allowlist; stok hasil delta harus tetap `0..stok_total`. | Transaksi. Tidak mengubah `jumlah` pinjam, tanggal pinjam, jatuh tempo, atau status peminjaman. Stok berubah sebesar `restoredStock(kondisi_baru) - restoredStock(kondisi_lama)`. Aplikasi menulis `activity_logs` aksi `update`; tidak ada `status_change` karena status tidak berubah. |
| Menghapus pengajuan Admin | ID peminjaman, Admin sesi | `Pengembalian::adminDelete()` mengunci peminjaman dan baris pengembalian, menolak baris terverifikasi, menghapus pengajuan, dan mengembalikan status peminjaman ke `approved`. | `302` bila terhapus; `409` bila sudah terverifikasi atau status tidak sesuai; `404` bila tidak ada. | Hanya `peminjaman.status = 'return_requested'` dan `pengembalian.tanggal_diterima IS NULL`. | Transaksi: hapus baris `pengembalian`; set status peminjaman `approved`; trigger menulis `status_change`; aplikasi menulis `activity_logs` aksi `delete`; commit. Stok tidak berubah karena pengajuan belum pernah memulihkan stok. |

## 6. Kontrak administratif create, edit, dan delete

| Operasi | Kontrak aktual | Batasan |
|---|---|---|
| Create Admin | Tidak tersedia. Tidak ada route `/admin/pengembalian/create`, method `create`/`store`, form, atau service untuk Admin membuat pengembalian. | Baris pengembalian hanya dibuat oleh Peminjam melalui `requestReturn()`. Admin tidak dapat memilih peminjaman atau jumlah dari UI. |
| Edit form Admin | `GET /admin/pengembalian/{id}/edit`, role `admin`, `id` adalah ID peminjaman dan harus ada. | `409` bila `tanggal_diterima IS NULL` karena belum diverifikasi. Form hanya menampilkan tanggal diterima dan kondisi; jumlah, tanggal pinjam, jatuh tempo, dan denda tidak dapat diubah langsung. |
| Update Admin | `POST /admin/pengembalian/{id}/edit`, wajib CSRF. Body hanya `tanggal_diterima` dan `kondisi`. | Hanya pengembalian yang sudah diterima pada peminjaman berstatus `returned`. Tanggal harus valid, tidak sebelum tanggal pinjam, tidak sebelum tanggal pengajuan, dan tidak melebihi hari ini. Kondisi harus `baik`, `rusak_ringan`, `rusak_berat`, atau `hilang`. Jumlah selalu jumlah pinjam; denda hanya dihitung ulang memakai tarif saat koreksi bila tanggal diterima berubah. Perubahan kondisi dapat menghasilkan `stock_conflict` bila delta membuat stok keluar dari `0..stok_total`. |
| Delete Admin | `POST /admin/pengembalian/{id}/delete`, wajib CSRF. | Hanya pengajuan `return_requested` yang belum diterima. Pengembalian terverifikasi tidak boleh dihapus karena stok sudah dipulihkan untuk kondisi `baik`. Penghapusan pengajuan mengembalikan peminjaman ke `approved`, bukan menghapus peminjaman. |

## 7. Aturan pengembalian, stok, denda, dan audit

- Pengembalian selalu penuh. `requestReturn()` mengambil `jumlah` dari baris peminjaman yang dikunci; tidak ada field jumlah pada request pengembalian.
- Satu peminjaman hanya boleh memiliki satu baris pengembalian. Unique key `uq_pengembalian_peminjaman` menjaga aturan ini; pelanggaran menghasilkan `duplicate` dan rollback.
- Pengajuan awal menyimpan `kondisi = 'baik'` sebagai nilai awal. Kondisi fisik sebenarnya baru ditetapkan saat Petugas memverifikasi.
- Pengajuan pengembalian tidak memindahkan stok. Stok baru berubah pada verifikasi atau koreksi Admin.
- Verifikasi mengunci `alat` lebih dulu, lalu `peminjaman`, lalu `pengembalian`. Urutan ini mencegah dua verifikasi memulihkan stok dua kali.
- `restoredStock(kondisi, jumlah)` mengembalikan `max(0, jumlah)` hanya untuk `baik`; `rusak_ringan`, `rusak_berat`, dan `hilang` mengembalikan 0.
- Verifikasi kondisi `baik` menambah `stok_tersedia` dengan guard `stok_tersedia + jumlah <= stok_total`. Bila guard gagal, seluruh transaksi rollback dan hasilnya `stock_conflict`.
- `stok_total` tidak diubah oleh pengembalian rusak atau hilang. Unit tersebut tetap tercatat sebagai selisih antara total dan stok tersedia sampai Admin menyelesaikan kondisi fisik alat melalui modul alat.
- Hari terlambat dihitung sebagai selisih hari kalender antara tanggal jatuh tempo dan tanggal diterima; tanggal yang tidak terlambat menghasilkan 0. Perhitungan tidak memakai detik atau DST.
- Denda disimpan pada baris `pengembalian` sebagai snapshot historis. Perubahan `pengaturan.tarif_denda` tidak menulis ulang denda baris lama.
- Koreksi Admin mempertahankan `hari_terlambat`, `denda`, dan verifikator asli jika tanggal diterima tidak berubah. Jika tanggal diterima berubah, hari terlambat dan denda dihitung ulang memakai tarif saat koreksi. Identitas Admin pencatat koreksi disimpan di audit, bukan mengganti `verified_by`.
- Koreksi stok Admin memakai delta `restoredStock(kondisi_baru, jumlah) - restoredStock(kondisi_lama, jumlah)`. Perpindahan antara dua kondisi non-`baik` menghasilkan delta 0.
- Guard koreksi Admin mensyaratkan `stok_tersedia + delta BETWEEN 0 AND stok_total`; bila gagal, metadata dan stok di-rollback.
- Audit status peminjaman dimiliki trigger database. Aplikasi hanya menyetel `@sparta_actor_id` sebelum `UPDATE` yang mengubah status dan membersihkannya di `finally`.
- Pengajuan dan verifikasi tidak menulis audit aplikasi tambahan; audit yang muncul adalah `status_change` dari trigger. Koreksi dan penghapusan Admin menulis `activity_logs` aplikasi dengan aksi `update` atau `delete`.
- Exception dan semua kegagalan guard di dalam transaksi melakukan rollback sebelum mengembalikan hasil.

## 8. Validasi dan hasil kegagalan

| Pemeriksaan | Aturan aktual | Dampak |
|---|---|---|
| `tanggal_diterima` format | Harus `YYYY-MM-DD` dan tanggal kalender benar. | Error `Tanggal diterima tidak valid.` pada `validateVerify()`; `422`. |
| Batas tanggal verifikasi | Tidak sebelum `tanggal_pinjam`, tidak sebelum `tanggal_pengajuan`, dan tidak melebihi `date('Y-m-d')`. | Hasil model `invalid_date`; controller menampilkan `422` dengan pesan batas tanggal. |
| `kondisi` | Harus `baik`, `rusak_ringan`, `rusak_berat`, atau `hilang`. | Error `Kondisi alat tidak valid.`; `422` sebelum stok berubah. |
| Pemilik pengajuan | `peminjaman.user_id` harus sama dengan pengguna sesi. | Hasil `forbidden`; controller mengirim `403`. |
| Status pengajuan | Peminjaman harus `approved` saat mengajukan pengembalian. | Hasil `not_approved`; controller mengirim `409`. |
| Duplikat pengajuan | Unique `peminjaman_id`; SQLSTATE `23000` ditangkap. | Hasil `duplicate`; controller mengirim `409`. |
| Status verifikasi | Peminjaman harus `return_requested`, baris pengembalian ada, dan `tanggal_diterima` masih `NULL`. | Controller memeriksa lebih dulu; model mengembalikan `no_record`, `not_requested`, atau `conflict`; `409`. |
| Status koreksi | Peminjaman harus `returned` dan pengembalian sudah diterima. | Hasil `not_verified`; controller mengirim `409`. |
| Status hapus | Peminjaman harus `return_requested` dan pengembalian belum diterima. | Hasil `verified_conflict`; controller mengirim `409`. |
| Stok verifikasi | `stok_tersedia + restoredStock <= stok_total` untuk kondisi `baik`. | Hasil `stock_conflict`; rollback dan `409`. |
| Stok koreksi | `stok_tersedia + delta` harus berada di `0..stok_total`. | Hasil `stock_conflict`; rollback dan `409`. |
| Filter monitoring | `active`, `return_requested`, `returned`, `all`. | Nilai lain menjadi `active`. |
| Pencarian | String dipotong 100 karakter. | `q` kosong berarti tanpa filter pencarian. |
| Hasil verifikasi | `verified`, `missing`, `no_record`, `not_requested`, `conflict`, `invalid_date`, `stock_conflict`. | `missing` menjadi `404`; `invalid_date` menjadi `422`; hasil lain selain `verified` menjadi `409`. |
| Hasil koreksi Admin | `updated`, `missing`, `not_verified`, `invalid_date`, `stock_conflict`. | `missing` menjadi `404`; `invalid_date` menjadi `422`; `not_verified`/`stock_conflict` menjadi `409`. |
| Hasil hapus Admin | `deleted`, `missing`, `not_pending`, `verified_conflict`. | `missing` menjadi `404`; semua hasil selain `deleted` menjadi `409`. |

## 9. Inventaris method

### [`app/Controllers/PengembalianController.php`](../../app/Controllers/PengembalianController.php)

| Method | Input | Proses | Output |
|---|---|---|---|
| `__construct(Auth $auth, Session $session, View $view, Pengembalian $returns, PengembalianService $service, Pengaturan $pengaturan)` | Dependency | Menyimpan dependency pengembalian dan tarif. | Objek controller. |
| `index(): void` | Pengguna sesi, `page` | Memanggil `Pengembalian::own()`. | `200` daftar pengembalian sendiri. |
| `requestReturn(string $id): void` | ID peminjaman | Validasi ID, panggil `requestReturn()`, petakan hasil, flash, redirect. | `302`, `404`, `403`, atau `409`. |
| `monitoring(): void` | `status`, `q`, `page` | Normalisasi filter lewat service, panggil `monitoring()`. | `200` monitoring Petugas. |
| `verifyForm(string $id): void` | ID peminjaman | Ambil baris, cek status/pengajuan/penerimaan, siapkan nilai awal. | `200` form; `404`/`409`. |
| `verifyStore(string $id): void` | ID peminjaman, `$_POST` | Precheck status, `validateVerify()`, panggil `verify()`, petakan hasil. | `302`, `422`, `409`, atau `404`. |
| `findVerifyLoan(string $id): ?array` (private) | ID peminjaman | Regex 1–19 digit, panggil `findForVerify()`, `notFound()` bila gagal. | Baris atau `null`. |
| `renderForm(array $loan, array $values, array $errors = [], int $status = 200): void` (private) | Baris, nilai, error, status | Merender form verifikasi dengan tarif dan tanggal hari ini. | Response form. |
| `pageParam(): int` (private) | `page` | Regex 1–7 digit; selain itu 1. | Nomor halaman. |
| `actorId(): int` (private) | Pengguna sesi | Mengambil ID pengguna; `null` menjadi 0. | ID aktor. |
| `notFound(): void` (private) | Tidak ada | `abort(404, ...)`. | Response `404`. |
| `forbidden(): void` (private) | Tidak ada | `abort(403, ...)` untuk pengajuan milik orang lain. | Response `403`. |
| `conflict(string $message): void` (private) | Pesan | `abort(409, 'Status tidak valid', $message)`. | Response `409`. |
| `abort(int $status, string $title, string $message): void` (private) | Status, judul, pesan | Memilih tautan kembali sesuai role, mengirim status dan HTML error. | Response error. |
| `render(string $template, array $data, int $status = 200): void` (private) | Template, data, status | Mengambil flash, menambahkan data sesi, merender view. | Response HTML. |

### [`app/Controllers/AdminPengembalianController.php`](../../app/Controllers/AdminPengembalianController.php)

| Method | Input | Proses | Output |
|---|---|---|---|
| `__construct(Auth $auth, Session $session, View $view, Pengembalian $returns, PengembalianService $service)` | Dependency | Menyimpan dependency koreksi Admin. | Objek controller. |
| `index(): void` | `q`, `status`, `page` | Normalisasi pencarian, validasi status terhadap `STATUSES`, panggil `adminPaginate()`. | `200` daftar pengembalian. |
| `edit(string $id): void` | ID peminjaman | `findOr404()`, tolak baris belum diterima, siapkan nilai form. | `200` form; `409`; `404`. |
| `update(string $id): void` | ID peminjaman, `$_POST` | `findOr404()`, cek terverifikasi, `validateVerify()`, panggil `adminUpdate()`, petakan hasil. | `302`, `422`, `409`, atau `404`. |
| `destroy(string $id): void` | ID peminjaman | `findOr404()`, panggil `adminDelete()`, petakan hasil. | `302`, `409`, atau `404`. |
| `findOr404(string $id): ?array` (private) | ID peminjaman | Regex 1–19 digit, panggil `adminFind()`; gagal memicu `notFound()`. | Baris atau `null`. |
| `renderForm(array $row, array $values, array $errors = [], int $status = 200): void` (private) | Baris, nilai, error, status | Merender form koreksi dengan tanggal hari ini. | Response form. |
| `pageParam(): int` (private) | `page` | Regex 1–7 digit; selain itu 1. | Nomor halaman. |
| `actorId(): int` (private) | Pengguna sesi | Mengambil ID Admin; `null` menjadi 0. | ID aktor. |
| `notFound(): void` (private) | Tidak ada | `abort(404, ...)`. | Response `404`. |
| `conflict(string $message): void` (private) | Pesan | `abort(409, 'Status tidak valid', $message)`. | Response `409`. |
| `abort(int $status, string $title, string $message): void` (private) | Status, judul, pesan | Mengirim status dan HTML error dengan tautan kembali. | Response error. |
| `render(string $template, array $data, int $status = 200): void` (private) | Template, data, status | Mengambil flash, menambahkan data sesi, merender view. | Response HTML. |

### [`app/Models/Pengembalian.php`](../../app/Models/Pengembalian.php)

| Method | Input | Proses | Output |
|---|---|---|---|
| `__construct(PDO $db, Pengaturan $pengaturan)` | Koneksi dan pengaturan tarif | Menyimpan dependency. | Objek model. |
| `own(int $userId, int $page): array` | Pemilik, halaman | Query status `approved`/`return_requested`/`returned` milik pemilik, join alat dan pengembalian, urut terbaru, pagination. | `items`, `total`, `totalPages`. |
| `monitoring(string $status, int $page, string $q = ''): array` | Lane status, halaman, pencarian | Memilih filter status, mencari kode/nama, join peminjaman/pengguna/alat/pengembalian, urut jatuh tempo, pagination. | `items`, `total`, `totalPages`. |
| `findForVerify(int $loanId): ?array` | ID peminjaman | Join data peminjaman, pengguna, alat, dan pengembalian. | Baris atau `null`. |
| `requestReturn(int $loanId, int $userId, int $actorId): string` | ID peminjaman, pemilik sesi, aktor | Lookup, transaksi, lock peminjaman, cek pemilik/status, insert pengembalian, set aktor, ubah status, commit. | `created`, `missing`, `forbidden`, `not_approved`, atau `duplicate`. |
| `verify(int $loanId, int $actorId, string $acceptedDate, string $kondisi): string` | ID peminjaman, aktor, tanggal, kondisi | Transaksi; lock alat → peminjaman → pengembalian; validasi status/tanggal; hitung hari/denda; update baris; pulihkan stok bila `baik`; ubah status; commit. | `verified`, `missing`, `no_record`, `not_requested`, `conflict`, `invalid_date`, atau `stock_conflict`. |
| `adminPaginate(string $q, string $status, int $page): array` | Pencarian, status, halaman | Filter `tanggal_diterima`, pencarian kode/nama, urut pengajuan terbaru, pagination. | `items`, `total`, `totalPages`. |
| `adminFind(int $loanId): ?array` | ID peminjaman | Join pengembalian, peminjaman, pengguna, dan alat. | Baris atau `null`. |
| `adminUpdate(int $loanId, string $acceptedDate, string $kondisi, int $actorId): string` | ID peminjaman, tanggal, kondisi, aktor | Transaksi; lock alat → peminjaman → pengembalian; cek `returned`/terverifikasi; validasi tanggal; hitung hari/denda; update metadata; sesuaikan stok dengan delta; audit; commit. | `updated`, `missing`, `not_verified`, `invalid_date`, atau `stock_conflict`. |
| `adminDelete(int $loanId, int $actorId): string` | ID peminjaman, aktor | Transaksi; lock peminjaman dan pengembalian; cek status/belum diterima; hapus pengembalian; set aktor; ubah peminjaman ke `approved`; audit; commit. | `deleted`, `missing`, `not_pending`, atau `verified_conflict`. |
| `paginate(string $select, string $countSql, array $params, int $page): array` (private) | SQL, parameter, halaman | Menghitung total dan mengambil halaman `PER_PAGE` (10). | `items`, `total`, `totalPages`. |
| `lateDays(string $due, string $accepted): int` (private, static) | Tanggal jatuh tempo dan diterima | `DateTimeImmutable::diff()`, selisih hari kalender; tanggal lebih awal menghasilkan 0. | Jumlah hari terlambat. |
| `restoredStock(string $kondisi, int $jumlah): int` (private, static) | Kondisi dan jumlah | Mengembalikan `max(0, jumlah)` hanya untuk `baik`; kondisi lain 0. | Jumlah stok yang dipulihkan. |
| `isDate(string $value): bool` (private, static) | String tanggal | Regex dan round-trip `DateTimeImmutable` dengan format `!Y-m-d`. | `true`/`false`. |

### [`app/Services/PengembalianService.php`](../../app/Services/PengembalianService.php)

| Method | Input | Proses | Output |
|---|---|---|---|
| `validateVerify(array $input): array` | `$_POST` verifikasi | Normalisasi `tanggal_diterima` dan `kondisi`, validasi format tanggal dan allowlist kondisi. | `values` dan `errors`. |
| `statusFilter(mixed $value): string` | Nilai `status` | Trim; hanya `active`, `return_requested`, `returned`, `all` yang diterima. | Status valid atau `active`. |
| `search(mixed $value): string` | Nilai `q` | Trim dan potong maksimal 100 karakter; nilai bukan string menjadi string kosong. | Kata pencarian. |
| `isDate(string $value): bool` (private, static) | String tanggal | Regex dan round-trip `DateTimeImmutable` dengan format `!Y-m-d`. | `true`/`false`. |

`PengembalianService` tidak memiliki konstruktor eksplisit; bootstrap membuatnya dengan `new App\Services\PengembalianService()`.

## 10. Pemetaan pengujian yang ada

Belum ada pengujian yang dijalankan untuk dokumen ini. Tabel hanya memetakan skenario yang terlihat pada berkas test saat ini.

| Berkas pengujian | Cakupan yang terlihat dari isi berkas |
|---|---|
| [`tests/http_returns_test.php`](../../tests/http_returns_test.php) | Halaman peminjam, monitoring/filter/pencarian, form verifikasi, pembatasan role dan CSRF, kepemilikan pengajuan, pengajuan tanpa perubahan stok, duplikat, validasi tanggal/kondisi, verifikasi kondisi baik/rusak/hilang, denda terlambat, snapshot denda terhadap perubahan tarif, koreksi stok Admin, rollback stok, dan verifikasi bersamaan. |
| [`tests/http_backoffice_test.php`](../../tests/http_backoffice_test.php) | Daftar/filter Admin, penolakan edit pengembalian belum terverifikasi, penolakan hapus pengembalian terverifikasi, koreksi kondisi/tanggal, perhitungan ulang hari terlambat, penolakan tanggal masa depan, hapus pengajuan belum terverifikasi, pengembalian status ke `approved`, dan audit hapus. |
| [`tests/database/schema_test.php`](../../tests/database/schema_test.php) | Unique `pengembalian.peminjaman_id`, hasil `fn_hitung_denda`, dan trigger audit status. |
| [`tests/http_loans_test.php`](../../tests/http_loans_test.php) | Dasar peminjaman dan persetujuan yang menjadi prasyarat pengembalian; bukan pengujian utama modul pengembalian. |

## 11. Catatan dan risiko

- Tidak ada create pengembalian untuk Admin. Baris pengembalian hanya dibuat oleh Peminjam; Admin hanya mengoreksi atau menghapus pengajuan.
- Pengajuan pengembalian menyimpan kondisi awal `baik`. Kondisi final baru berlaku setelah verifikasi Petugas.
- Pengembalian tidak mendukung jumlah parsial. `jumlah` selalu disalin dari baris peminjaman saat pengajuan dan diverifikasi ulang dari baris yang dikunci.
- `rusak_ringan`, `rusak_berat`, dan `hilang` semuanya memulihkan 0 unit. Perpindahan antara kondisi-kondisi tersebut melalui koreksi Admin menghasilkan delta stok 0.
- Denda baris lama tidak berubah saat tarif diubah. Koreksi Admin hanya menghitung ulang denda bila tanggal diterima berubah, memakai tarif yang berlaku saat koreksi; koreksi kondisi saja mempertahankan denda historis.
- Verifikasi dan koreksi Admin membutuhkan `fn_hitung_denda` pada database. Bila [`database/routines.sql`](../../database/routines.sql) belum diimpor, pemanggilan fungsi gagal dan request berakhir sebagai error server.
- `lateDays()` di PHP dan `fn_hitung_denda()` di database menghitung keterlambatan secara terpisah. Keduanya harus tetap memakai basis tanggal yang sama; perubahan definisi salah satunya dapat membuat `hari_terlambat` dan `denda` tidak konsisten.
- Guard stok mencegah `stok_tersedia` melewati `stok_total`, tetapi tidak mengubah `stok_total`. Alat rusak atau hilang tetap tercatat sebagai selisih stok sampai ditangani melalui modul alat.
- Filter Admin `pending` berarti pengembalian belum diterima, bukan peminjaman berstatus `pending`.
- Pesan dan tautan error pada `abort()` bergantung pada role pengguna sesi; pengguna tanpa role Petugas diarahkan ke `/peminjam/peminjaman`.
- `Pengembalian::adminUpdate()` memeriksa `rowCount() === 1` setelah `UPDATE pengembalian`. Pada MySQL, koreksi tanpa perubahan nilai dapat menghasilkan `rowCount()` 0 dan controller menampilkan `404`, meskipun baris ada. Perilaku ini berasal dari konfigurasi PDO yang tidak memakai `MYSQL_ATTR_FOUND_ROWS`.
