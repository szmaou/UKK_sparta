# 09 — Laporan

## Tujuan

Laporan memberi Petugas rekap peminjaman pada rentang tanggal, dengan filter
status opsional, ringkasan, dan halaman siap cetak. Halaman biasa dan halaman cetak
memakai query serta data yang sama, sehingga angka keduanya konsisten.

Requirement terkait: FR-11 (mencetak laporan).

## Aktor dan route

| Route | Method | Aktor | Output |
|---|---|---|---|
| `/petugas/laporan` | GET | Petugas | Form filter, ringkasan, dan tabel rekap |
| `/petugas/laporan/cetak` | GET | Petugas | Dokumen HTML mandiri untuk dicetak atau disimpan PDF |

Kedua route privat dan dibatasi role `petugas` melalui metadata
[`app/routes.php`](../../app/routes.php). Admin dan peminjam mendapat `403` dari
kernel. Pengguna tanpa session diarahkan ke `/login`. Tidak ada route `POST` pada
modul ini.

## Dependensi

| Komponen | Peran |
|---|---|
| [`LaporanController`](../../app/Controllers/LaporanController.php) | Membaca query string, memanggil model, memilih view |
| [`Laporan`](../../app/Models/Laporan.php) | Validasi tanggal/status, query rekap, ringkasan, batas baris |
| `App\Services\Auth` | Menyediakan user untuk data layout |
| `App\Core\Session` | Token CSRF dan flash message |
| `App\Core\View` | Rendering halaman laporan |
| `app/Views/petugas/laporan/index.php` | Halaman interaktif dengan layout aplikasi |
| `app/Views/petugas/laporan/cetak.php` | Halaman cetak mandiri tanpa layout aplikasi |

Wiring: `new LaporanController($auth, $session, $view, new Laporan($container['db']))`
di [`app/bootstrap.php`](../../app/bootstrap.php).

## Proses

### 1. Halaman laporan — `LaporanController::index()`

| Tahap | Uraian |
|---|---|
| Input | Query string `from`, `to`, `status`; session dan flash message |
| Proses | `parsedQuery()` menentukan nilai awal lalu memanggil `Laporan::parse()`. Nilai hasil parse dikirim ke `Laporan::rekap()`. Controller merender `petugas/laporan/index` |
| Output | `200` bila tidak ada error validasi; `422` bila ada error, tetapi halaman dan tabel tetap dirender. Data view: `values`, `errors`, `items`, `summary`, `truncated`, user, role, token CSRF, flash sukses/gagal |
| Validasi | Tanggal harus format `Y-m-d` yang benar-benar ada. Status harus salah satu nilai yang diizinkan. Lihat tabel validasi di bawah |
| Kegagalan | Error validasi tidak melempar exception dan tidak menghentikan query. Query database gagal ditangkap kernel dan menjadi halaman `500` |

Nilai awal diterapkan hanya bila parameter tidak ada, bukan string, atau string
kosong:

- `from` → tanggal pertama bulan berjalan (`Y-m-01`);
- `to` → tanggal hari ini (`Y-m-d`);
- `status` → string kosong (semua status).

Zona waktu proses adalah `Asia/Jakarta`, ditetapkan di
[`app/bootstrap.php`](../../app/bootstrap.php).

### 2. Halaman cetak — `LaporanController::cetak()`

| Tahap | Uraian |
|---|---|
| Input | Query string yang sama dengan halaman laporan |
| Proses | Parse dan query yang sama; controller merender `petugas/laporan/cetak` langsung melalui `View::render()`, tanpa layout aplikasi |
| Output | Dokumen HTML lengkap berstatus `200` atau `422`, berisi periode, status terpilih, ringkasan, tabel, waktu cetak, tombol `window.print()`, dan tautan kembali |
| Validasi | Sama dengan halaman laporan; error validasi menghasilkan status `422`. View cetak tidak merender daftar pesan error, hanya nilai filter yang dipakai |
| Kegagalan | Query database gagal menjadi `500`. Flash message tidak dibaca atau ditampilkan pada halaman cetak |

Halaman cetak bukan file PDF dan tidak memakai pustaka PDF. Pengguna mencetak
melalui browser. Data yang dipakai tetap data database saat request, bukan snapshot
tersimpan.

## Validasi input

`Laporan::parse(mixed $from, mixed $to, mixed $status)` hanya menerima nilai string;
nilai bertipe lain menjadi string kosong. String di-`trim()`.

| Kondisi | Hasil |
|---|---|
| `from` bukan tanggal valid | `errors['from'] = 'Tanggal awal tidak valid.'` |
| `to` bukan tanggal valid | `errors['to'] = 'Tanggal akhir tidak valid.'` |
| Kedua tanggal valid dan `from > to` | `errors['to'] = 'Tanggal akhir tidak boleh sebelum tanggal awal.'` |
| `to` valid, tidak ada error `to`, dan berada setelah hari ini | `to` diganti tanggal hari ini, tanpa error |
| `status` tidak ada dalam `['', 'pending', 'approved', 'rejected', 'return_requested', 'returned']` | `errors['status'] = 'Status laporan tidak valid.'` |

Tanggal divalidasi dengan regex empat digit-tahun, dua digit-bulan, dua digit-hari,
lalu `DateTimeImmutable::createFromFormat('!Y-m-d')` dan perbandingan hasil format.
Nilai seperti `2026-02-30` atau `2026-13-99` ditolak. Pembatasan tanggal akhir yang
melewati hari ini hanya membetulkan nilai filter, bukan input tanggal pinjam.

Controller tetap menjalankan `rekap()` walaupun ada error validasi. Karena itu
halaman `422` dapat menampilkan tabel kosong atau hasil dari nilai yang masih lolos
ke query. Nilai `status` yang tidak valid tetap di-bind sebagai parameter dan tidak
pernah disisipkan ke SQL.

## Filter dan isi rekap — `Laporan::rekap()`

| Aspek | Perilaku aktual |
|---|---|
| Rentang | `p.tanggal_pinjam BETWEEN :from AND :to`, inklusif pada kedua batas |
| Status | Bila `status` tidak kosong, tambah `AND p.status = :status`; kosong berarti semua status |
| Join | `users`, `alat`, dan `kategori` dengan `INNER JOIN`; `pengembalian` dengan `LEFT JOIN` |
| Urutan | `p.tanggal_pinjam, p.id` menaik |
| Batas | `MAX_ROWS = 1000`; query mengambil `1001` baris untuk mendeteksi pemotongan |
| Pagination | Tidak ada. Halaman menampilkan maksimum 1.000 baris pertama |
| Parameter | Semua nilai filter memakai prepared statement dan `bindValue` |

Kolom hasil: `kode`, `peminjam`, `alat`, `kategori`, `jumlah`, `tanggal_pinjam`,
`tanggal_jatuh_tempo`, `status`, dan `denda`.

Ringkasan dihitung dari baris yang benar-benar dikembalikan:

- `total` = jumlah baris transaksi, bukan jumlah unit;
- `unit` = jumlah kolom `jumlah`;
- `denda` = jumlah `COALESCE(pg.denda, 0)`;
- `per_status` = jumlah baris per status.

Bila hasil lebih dari 1.000 baris, `truncated` bernilai `true`, baris dipotong ke
1.000, dan **ringkasan hanya mencakup 1.000 baris tersebut**. Halaman menampilkan
peringatan untuk mempersempit rentang tanggal. Data tidak dipotong per halaman
seperti pagination; tidak ada tautan halaman berikutnya.

`LEFT JOIN pengembalian` aman terhadap penggandaan baris karena
`pengembalian.peminjaman_id` memiliki unique key. Denda yang ditampilkan adalah
nilai historis pada baris `pengembalian`, bukan hasil hitung ulang dengan tarif
saat ini.

## Perbedaan halaman laporan dan cetak

| Aspek | `/petugas/laporan` | `/petugas/laporan/cetak` |
|---|---|---|
| Layout aplikasi | Ya, termasuk navigasi dan sidebar | Tidak; HTML mandiri |
| Form filter | Ada, method `GET` | Tidak ada |
| Tombol cetak | Tautan membuka tab cetak | `window.print()` dan tautan kembali |
| Ringkasan | Tiga kartu statistik | Blok ringkasan teks |
| Kolom tabel | Kode, peminjam, alat/kategori, jumlah, tanggal, status, denda | Nomor, kode, peminjam, alat, kategori, jumlah, tanggal pinjam, jatuh tempo, status, denda |
| Peringatan pemotongan | Ada bila `truncated` | Ada bila `truncated` |
| Flash message | Dibaca dan dihapus setelah render | Tidak ditampilkan |
| CSS | Aset aplikasi | CSS inline dan aturan `@media print` |
| Status HTTP error validasi | `422` | `422` |

Keduanya memakai filter yang sama. Tautan cetak membentuk query string hanya dari
nilai yang tidak kosong.

## Inventaris method

| Method | Input | Proses | Output |
|---|---|---|---|
| `LaporanController::__construct(Auth, Session, View, Laporan)` | Empat dependensi | Menyimpan dependensi | Objek controller |
| `LaporanController::index()` | Query string dan session | Parse, rekap, render halaman | Halaman `200`/`422` |
| `LaporanController::cetak()` | Query string dan session | Parse, rekap, render dokumen mandiri | Dokumen `200`/`422` |
| `LaporanController::parsedQuery()` | `$_GET` | Memberi nilai awal lalu meneruskan ke model | `values` dan `errors` |
| `LaporanController::render(string $template, array $data, int $status = 200)` | Template, data, status | Mengambil user dan flash, menghapus flash, menambah data layout | Render view |
| `Laporan::__construct(PDO $db)` | Koneksi PDO | Menyimpan koneksi | Objek model |
| `Laporan::parse(mixed $from, mixed $to, mixed $status)` | Tiga nilai query | Trim, validasi tanggal/urutan/status, koreksi tanggal akhir | `values`, `errors` |
| `Laporan::rekap(string $from, string $to, string $status)` | Rentang dan status | Query join, batas 1.000 baris, hitung ringkasan | `items`, `summary`, `truncated` |
| `Laporan::isDate(string $value)` | Satu string | Regex dan validasi tanggal kalender | `bool` |

## Batas dan catatan

- Laporan memakai query milik `Laporan::rekap()`, bukan memanggil
  `sp_rekap_peminjaman`. Procedure SQL tersebut mempertahankan demonstrasi `CALL`
  dan tidak memiliki filter status maupun batas baris.
- Tidak ada ekspor CSV/Excel/PDF server-side dan tidak ada penyimpanan hasil laporan.
- Kombinasi tanggal awal di masa depan dan tanggal akhir di masa depan dapat
  menghasilkan `from > to` setelah `to` dibatasi ke hari ini tanpa validasi ulang;
  query akan mengembalikan hasil kosong.
- Test HTTP terkait ada di
  [`tests/http_backoffice_test.php`](../../tests/http_backoffice_test.php), termasuk
  status `422` untuk tanggal/status tidak valid dan pemeriksaan halaman cetak.
  Dokumen ini tidak menyatakan test sudah dijalankan.
