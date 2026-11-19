# Hasil Uji Coba Sistem SPARTA

## 1. Tujuan dan Lingkungan

Pengujian memverifikasi login, pengelolaan alat, peminjaman, pengembalian, denda, dan pembatasan hak akses. Hasil aktual berasal dari pengujian otomatis HTTP dan pemeriksaan database, bukan penilaian tampilan secara manual.

- Tanggal pelaksanaan: 19 November 2026.
- Commit aplikasi yang diuji: `c30add3`.
- Lingkungan: PHP 8.5.12 dengan ekstensi curl; MariaDB 13.0.2.
- Database: database disposable `sparta_accept_20261119` pada `127.0.0.1:33306`.
- Keamanan data: database operasional pada port `3306` tidak disentuh. Proses dan direktori database uji sudah dibersihkan.

## 2. Kasus dan Hasil Aktual

Status **Lulus** berarti assertion yang memeriksa hasil tersebut dijalankan dan berhasil. Status **Belum diuji** tidak boleh dianggap lulus.

| ID | Skenario | Kasus | Hasil yang Diharapkan | Hasil Aktual | Status |
|---|---|---|---|---|---|
| UJI-01 | Login user | Login dengan akun Admin aktif dan kredensial benar. | Login berhasil dan diarahkan ke dashboard Admin. | HTTP 302 dengan tujuan `/admin/dashboard`. | Lulus |
| UJI-02 | Login gagal | Login dengan password salah. | Login ditolak dan muncul pesan kesalahan. | HTTP 401; pesan `Username atau kata sandi tidak sesuai` tampil. | Lulus |
| UJI-03 | Akun nonaktif | Login dengan akun yang dinonaktifkan. | Login ditolak tanpa mengungkapkan detail keberadaan akun. | HTTP 401 dengan pesan generik yang sama seperti password salah. | Lulus |
| UJI-04 | Tambah alat | Admin menambah alat valid dengan stok awal 6 unit. | Data tersimpan dan stok tersedia sama dengan stok total. | HTTP 302 ke `/admin/alat`; stok tersedia pada database bernilai 6. | Lulus |
| UJI-05 | Kode alat duplikat | Admin menambah alat menggunakan kode yang sudah terdaftar. | Penyimpanan ditolak dan pesan validasi ditampilkan. | HTTP 422; pesan `Kode alat sudah digunakan` tampil. | Lulus |
| UJI-06 | Stok tidak valid | Admin memasukkan stok `-1`, `abc`, atau `1.5`. | Penyimpanan ditolak; stok harus bilangan bulat yang valid. | HTTP 422 untuk setiap nilai; pesan validasi tampil dan tidak ada baris dari percobaan tidak valid yang tersimpan. | Lulus |
| UJI-07 | Pinjam alat | Peminjam mengirim pengajuan dengan data valid. | Pengajuan tersimpan sebagai pending; stok belum berkurang. | HTTP 302 ke `/peminjam/peminjaman`; stok tetap 5 unit. | Lulus |
| UJI-08 | Stok saat persetujuan | Petugas menyetujui pengajuan ketika stok tersedia tidak mencukupi. | Persetujuan ditolak tanpa perubahan status, stok, atau audit perubahan status. | Status tetap `pending`, stok tetap 0, dan tidak ada audit perubahan status baru. | Lulus |
| UJI-09 | Stok saat pengajuan | Peminjam mengajukan jumlah melebihi stok tersedia saat pembuatan pengajuan. | Pengajuan ditolak dengan pesan stok tidak mencukupi. | Belum ada assertion khusus untuk kasus ini dalam suite yang dijalankan. | Belum diuji |
| UJI-10 | Tanggal peminjaman | Masukkan tanggal kalender tidak valid, jatuh tempo tidak valid, atau jatuh tempo sebelum tanggal pinjam. | Pengajuan ditolak dengan validasi. | HTTP 422 untuk seluruh variasi tersebut. | Lulus |
| UJI-11 | Kembalikan alat | Petugas memverifikasi pengembalian tepat waktu dengan kondisi baik. | Verifikasi berhasil, stok pulih, dan denda Rp0. | HTTP 302; hari terlambat 0, denda Rp0, stok tersedia menjadi 5 unit. | Lulus |
| UJI-12 | Alat rusak/hilang | Petugas memverifikasi pengembalian dengan kondisi rusak atau hilang. | Transaksi selesai tanpa menambah stok tersedia. | Status `returned`; assertion memastikan stok tersedia tidak bertambah. | Lulus |
| UJI-13 | Verifikasi berulang | Kirim verifikasi ulang terhadap transaksi yang sudah diverifikasi, termasuk dua permintaan bersamaan. | Hanya satu verifikasi berhasil; stok tidak bertambah dua kali. | Permintaan ulang HTTP 409; permintaan bersamaan menghasilkan 302 dan 409. Tidak ada tambahan stok atau audit ganda. | Lulus |
| UJI-14 | Pengembalian dengan denda | Verifikasi 2 unit yang terlambat 3 hari dengan tarif Rp1.000 per hari per unit. | Denda `3 × 2 × Rp1.000 = Rp6.000`. | Database mencatat hari terlambat 3 dan denda Rp6.000. Perubahan tarif berikutnya tidak mengubah denda tersimpan. | Lulus |
| UJI-15 | Privilege user | Peminjam membuka halaman tambah alat Admin atau mengirim verifikasi pengembalian; Petugas membuka pengelolaan alat Admin. | Akses ditolak karena role tidak sesuai. | Seluruh permintaan terkait mendapat HTTP 403. | Lulus |
| UJI-16 | Akses tanpa login | Pengunjung membuka dashboard, katalog, atau halaman pengembalian yang dilindungi. | Sistem mengarahkan pengunjung ke login. | HTTP 302 menuju `/login`. | Lulus |

## 3. Bukti Pengujian

Suite menampilkan jumlah pemeriksaan dan kegagalan. Label assertion di bawah menjadi rujukan hasil aktual; suite yang bersangkutan selesai tanpa kegagalan.

| Sumber | Kasus Terkait | Contoh Label Assertion |
|---|---|---|
| `tests/http_auth_test.php` | UJI-01–03, UJI-15–16 | `admin login redirects to admin dashboard`, `wrong password is rejected`, `inactive account is rejected`, `peminjam on admin dashboard is 403` |
| `tests/http_master_data_test.php` | UJI-04–06, UJI-15 | `alat inserted with stok_tersedia = stok_total`, `duplicate alat kode is 422`, `invalid alat attempts inserted nothing`, `peminjam GET alat create is 403` |
| `tests/http_loans_test.php` | UJI-07–08, UJI-10 | `valid request redirects to own history`, `pending does not decrement stock`, `insufficient approval leaves status pending`, `validation rejects ` dengan variasi input tanggal |
| `tests/http_returns_test.php` | UJI-11–15 | `on-time verification has zero late days and fine`, `damaged/lost return restores no available stock`, `duplicate verification is 409`, `late return applies configured tariff (3 days x 2 units x Rp1.000)` |

### Ringkasan Eksekusi

| Perintah | Hasil Aktual | Exit Code |
|---|---|---|
| `php tests/database_config_test.php` | PASS: database environment precedence and empty password | 0 |
| `php tests/security_test.php` | 44 checks, 0 failed | 0 |
| `php tests/database/schema_test.php` | 29 checks, 0 failed | 0 |
| `php tests/http_auth_test.php` | 74 checks, 0 failed | 0 |
| `php tests/http_master_data_test.php` | 106 checks, 0 failed | 0 |
| `php tests/http_users_test.php` | 55 checks, 0 failed | 0 |
| `php tests/http_loans_test.php` | 70 checks, 0 failed | 0 |
| `php tests/http_returns_test.php` | 65 checks, 0 failed | 0 |
| `php tests/http_backoffice_test.php` | 89 checks, 0 failed | 0 |

Kesembilan file uji selesai dengan exit code 0. Jumlah pemeriksaan terhitung adalah **532**, seluruhnya lulus; pengujian konfigurasi database juga menampilkan PASS secara terpisah. Tidak ada file uji yang dilewati dengan exit code 2.

Log eksekusi lokal: `/tmp/opencode/sparta-acceptance-20261119.log`. Lokasi ini bersifat sementara dan bukan artefak yang tersimpan dalam Git.

## 4. Kesimpulan dan Batasan

Lima kebutuhan utama—login, tambah alat, pinjam alat, kembalikan alat, dan privilege pengguna—memiliki bukti pengujian yang lulus. Pengembalian dengan denda juga berhasil diverifikasi.

Satu subkasus tambahan belum diuji: jumlah melebihi stok ketika pengajuan pertama kali dibuat (UJI-09). Penolakan stok tidak mencukupi pada tahap persetujuan sudah diuji, tetapi tidak menggantikan bukti untuk tahap pengajuan.

Pengujian ini tidak mencakup inspeksi visual browser, responsivitas, maupun runtime Docker/Nginx. Hasil lulus otomatis tidak berarti seluruh kemungkinan input dan konfigurasi produksi sudah tercakup.
