# 11 — Pengaturan

## Tujuan

Modul pengaturan menyimpan konfigurasi aplikasi berbentuk key/value. Saat ini satu
kunci yang dikelola adalah `tarif_denda`, yaitu tarif denda keterlambatan dalam
rupiah per hari per unit. Nilai bawaan adalah `0` (bebas denda).

Perubahan tarif tidak menghitung ulang denda yang sudah tersimpan pada riwayat
pengembalian. Tarif baru hanya dipakai saat verifikasi berikutnya atau saat Admin
secara eksplisit mengoreksi sebuah baris pengembalian.

Requirement terkait: perhitungan denda pada alur pengembalian dan kebutuhan Admin
mengatur tarif. PRD menetapkan denda opsional dengan tarif bawaan Rp0.

## Aktor dan route

| Route | Method | Aktor | Output |
|---|---|---|---|
| `/admin/pengaturan` | GET | Admin | Form tarif dan nilai saat ini |
| `/admin/pengaturan` | POST | Admin | Simpan tarif, lalu redirect |

Route privat dengan role `admin` di
[`app/routes.php`](../../app/routes.php). `POST` wajib menyertakan token `_token`;
pemeriksaan CSRF dilakukan terpusat sebelum controller. Role lain mendapat `403`,
pengguna tanpa session diarahkan ke `/login`, dan token salah/tidak ada mendapat
`403`. Tidak ada route hapus atau reset tarif.

## Dependensi

| Komponen | Peran |
|---|---|
| [`PengaturanController`](../../app/Controllers/PengaturanController.php) | Menampilkan form dan memproses perubahan |
| [`Pengaturan`](../../app/Models/Pengaturan.php) | Membaca, memvalidasi, dan menyimpan tarif |
| [`ActivityLog`](../../app/Models/ActivityLog.php) | Mencatat perubahan beserta nilai lama/baru |
| `App\Services\Auth`, `App\Core\Session`, `App\Core\View` | Identitas aktor, flash, token, rendering |
| Tabel `pengaturan` | Penyimpanan `kunci`/`nilai` |
| `fn_hitung_denda` | Menghitung denda memakai tarif yang sedang aktif |

Wiring: `$pengaturan = new Pengaturan($container['db'], $logs)` dan
`new PengaturanController($auth, $session, $view, $pengaturan)` di
[`app/bootstrap.php`](../../app/bootstrap.php). Instance yang sama diberikan kepada
`Pengembalian`, sehingga tarif yang dibaca saat verifikasi adalah nilai terbaru.

## Proses

### 1. Tampilkan form — `PengaturanController::index()`

| Tahap | Uraian |
|---|---|
| Input | Request `GET`, session dan flash message |
| Proses | `Pengaturan::tarifDenda()` membaca `nilai` untuk kunci `tarif_denda`; controller memanggil `renderForm()` |
| Output | Halaman `200` berisi nilai tarif saat ini, input angka, bantuan rumus, dan peringatan bahwa histori tidak dihitung ulang |
| Validasi | Tidak ada input pengguna. Bila baris tidak ada atau nilai bukan string digit, getter mengembalikan `0` |
| Kegagalan | Query database gagal menjadi `500` melalui kernel |

### 2. Simpan tarif — `PengaturanController::update()`

| Tahap | Uraian |
|---|---|
| Input | `POST` berisi `tarif_denda`; token CSRF diperiksa kernel; aktor dari session |
| Proses | `Pengaturan::parse($_POST)` memvalidasi. Bila valid, `Pengaturan::save((int) $tarif, $actorId)` melakukan upsert dan mencatat audit dalam satu transaksi. Controller menyetel flash sukses lalu redirect |
| Output | `302 Location: /admin/pengaturan` dengan flash `Tarif denda berhasil diperbarui.` |
| Validasi | Kosong, bukan digit, atau lebih dari `1.000.000` ditolak. Lihat tabel di bawah |
| Kegagalan | Error validasi merender ulang form dengan status `422` dan nilai yang diketik. Kegagalan database membatalkan transaksi dan diteruskan ke kernel sebagai `500`, tanpa flash sukses |

`actorId()` mengambil ID dari `Auth::user()` dan mengembalikan `0` bila user null.
Pada route ini user seharusnya selalu ada karena RBAC; nilai `0` adalah pengaman
agar signature model tetap terpenuhi.

## Validasi — `Pengaturan::parse()`

| Kondisi input `tarif_denda` | Hasil |
|---|---|
| Bukan string | Dianggap string kosong |
| String kosong setelah `trim()` | `errors['tarif_denda'] = 'Tarif denda wajib diisi.'` |
| Tidak cocok `^\d{1,7}$` | Error: tarif harus bilangan bulat 0–1.000.000 |
| Nilai numerik lebih dari `TARIF_MAX = 1000000` | Error yang sama |
| `0` sampai `1000000` | Lolos, nilai dipertahankan sebagai string |

Input harus bilangan bulat tanpa tanda minus, titik, koma, atau spasi. `0` valid
dan berarti tidak ada denda. Spasi di awal/akhir dibuang. Nilai yang lolos
kemudian di-cast ke `int` oleh controller.

## Penyimpanan — `Pengaturan::save()`

| Tahap | Uraian |
|---|---|
| Input | Tarif bulat tervalidasi dan ID aktor |
| Proses | Baca tarif lama, mulai transaksi, jalankan `INSERT ... ON DUPLICATE KEY UPDATE` untuk kunci `tarif_denda`, catat audit `update` pada entitas `pengaturan`, commit |
| Output | Baris `pengaturan` baru atau nilai diperbarui; satu baris `activity_logs` |
| Audit | Deskripsi: `Tarif denda per hari per unit: Rp <lama> -> Rp <baru>`; `entity_id` null |
| Kegagalan | `Throwable` apa pun memicu `rollBack()` bila transaksi masih aktif, lalu exception dilempar kembali. Upsert dan audit tidak dapat tersimpan setengah |

Nilai disimpan sebagai string di kolom `nilai` (`VARCHAR(255)`). Kunci `tarif_denda`
merupakan primary key, sehingga hanya ada satu nilai aktif. `updated_at` diperbarui
otomatis oleh database.

## Hubungan dengan denda historis

- `Pengaturan::tarifDenda()` dipanggil oleh `Pengembalian` saat verifikasi dan saat
  koreksi administratif pengembalian.
- Kedua alur memanggil `fn_hitung_denda(tanggal_jatuh_tempo, tanggal_diterima, jumlah, tarif)`
  lalu menyimpan hasilnya ke `pengembalian.denda`.
- Mengubah tarif tidak menjalankan `UPDATE` pada `pengembalian`. Baris lama tetap
  memakai nominal yang tersimpan saat verifikasi/koreksi terakhir.
- Bila Admin mengoreksi sebuah pengembalian, baris itu dihitung ulang memakai tarif
  yang aktif saat koreksi. Ini adalah perhitungan ulang eksplisit, bukan efek
  samping perubahan pengaturan.
- Laporan membaca `COALESCE(pg.denda, 0)` dari baris pengembalian; laporan tidak
  mengalikan ulang dengan tarif saat ini.

## Inventaris method

| Method | Input | Proses | Output |
|---|---|---|---|
| `PengaturanController::__construct(Auth, Session, View, Pengaturan)` | Empat dependensi | Menyimpan dependensi | Objek controller |
| `PengaturanController::index()` | Session | Baca tarif, render form | Halaman `200` |
| `PengaturanController::update()` | `$_POST`, session | Validasi, simpan, flash, redirect | `302` atau form `422` |
| `PengaturanController::renderForm(array $values, array $errors = [], int $status = 200)` | Nilai, error, status | Ambil user/flash dan render view | Halaman form |
| `PengaturanController::actorId()` | Session | Ambil ID user atau `0` | `int` |
| `Pengaturan::__construct(PDO $db, ActivityLog $logs)` | Koneksi dan logger | Menyimpan dependensi | Objek model |
| `Pengaturan::tarifDenda()` | Tidak ada | Query kunci `tarif_denda`, validasi digit | Tarif `int`, fallback `0` |
| `Pengaturan::parse(array $input)` | Data form | Trim dan validasi batas | `values`, `errors` |
| `Pengaturan::save(int $tarif, int $actorId)` | Tarif dan aktor | Upsert + audit dalam transaksi | `void` |

## Batas dan catatan

- Hanya tarif denda yang dapat diubah dari UI. Tabel `pengaturan` dapat menampung
  kunci lain, tetapi belum ada layar atau validasi generik untuk itu.
- Tidak ada tombol reset ke bawaan. Untuk kembali bebas denda, isi `0`.
- Nilai rusak di database tidak diperbaiki otomatis; getter menganggapnya `0`
  sampai Admin menyimpan nilai baru.
- Perubahan tarif bukan transaksi moneter dan tidak memengaruhi denda yang sudah
  tercatat.
- Test terkait ada di [`tests/http_backoffice_test.php`](../../tests/http_backoffice_test.php)
  dan [`tests/database/schema_test.php`](../../tests/database/schema_test.php).
  Dokumen ini tidak menyatakan test sudah dijalankan.
