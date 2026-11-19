# Hasil Uji Browser pada Database `.env`

Tanggal: 19 November 2026. Aplikasi dijalankan melalui PHP development server pada `http://127.0.0.1:8000`. Browser dikendalikan dengan Playwright. Kredensial koneksi database tidak ditampilkan.

## Batas Pengujian

Pengguna menyetujui pengujian tanpa perubahan data bisnis. Tidak dilakukan tambah/edit/hapus alat, pengajuan/persetujuan pinjaman, verifikasi pengembalian, atau perubahan pengaturan. Login dan logout menulis sesi dan dapat menambah audit autentikasi.

## Masalah Awal dan Penanganan

Login pertama menghasilkan HTTP 500 karena tabel `login_attempts` belum tersedia. Audit read-only juga menemukan kolom `alat.foto` serta tabel `pengaturan` belum tersedia.

Setelah persetujuan pengguna, database dicadangkan dan migrasi berikut diterapkan:

1. `database/migrations/001_add_alat_foto.sql`.
2. `database/migrations/002_add_pengaturan.sql`.
3. `database/migrations/003_add_login_attempts.sql`.

Backup lengkap: `/tmp/opencode/sparta-backups/20261119-092145/full.sql`, 15.875 byte, izin file `0600`. Backup berisi data sensitif, berada di luar repository, dan tidak boleh dipublikasikan. Pemeriksaan struktur dump berhasil; pemulihan backup belum diuji. Backup parsial percobaan pertama tidak boleh digunakan.

Metadata migrasi tervalidasi. Jumlah baris tabel bisnis dan audit tetap sama sebelum dan sesudah migrasi. Pengaturan `tarif_denda` baru bernilai `0`; tidak diubah dalam pengujian browser. Log migrasi lokal: `/tmp/opencode/sparta-migrations-20261119.log`.

## Hasil Aktual Setelah Migrasi

| Kasus | Hasil Aktual | Status |
|---|---|---|
| Login Admin | Berhasil menuju `/admin/dashboard`. | Lulus |
| Halaman Admin | Dashboard, kategori, alat, pengguna, pengaturan, audit, peminjaman, dan pengembalian merespons HTTP 200. | Lulus |
| Admin membuka dashboard Petugas | HTTP 403. | Lulus |
| Login Petugas | Berhasil menuju `/petugas/dashboard`. | Lulus |
| Halaman Petugas | Dashboard, persetujuan, pengembalian, dan laporan merespons HTTP 200. | Lulus |
| Petugas membuka pengelolaan pengguna Admin | HTTP 403. | Lulus |
| Login Peminjam | Berhasil menuju `/peminjam/dashboard`. | Lulus |
| Halaman Peminjam | Dashboard, katalog alat, peminjaman, dan pengembalian merespons HTTP 200. | Lulus |
| Peminjam membuka tambah alat Admin dan monitoring Petugas | Kedua halaman merespons HTTP 403. | Lulus |
| Akses dashboard Admin setelah logout | Navigasi dialihkan ke `/login`; fetch mengikuti redirect dan berakhir HTTP 200 pada halaman login. | Lulus |
| Lebar dashboard mobile | Admin, Petugas, dan Peminjam: viewport 390 px, lebar dokumen 390 px. Tidak terdeteksi overflow horizontal dokumen. | Lulus untuk pengukuran ini |
| Lebar dashboard tablet | Petugas: viewport 768 px, lebar dokumen 768 px. | Lulus untuk pengukuran ini |
| Drawer mobile Admin | Tombol buka mengubah `aria-expanded` menjadi `true` dan checkbox drawer aktif. Escape menutup drawer dan mengembalikan `aria-expanded` menjadi `false`. | Lulus untuk buka dan Escape |
| Klik backdrop penutup drawer | Klik otomatis pada tombol overlay mengalami timeout; area tengah target tertutup panel. Tidak disimpulkan sebagai bug aplikasi tanpa uji klik area overlay yang terbuka. | Belum terverifikasi |

HTTP 403 yang sengaja dipicu pada pengujian privilege dapat muncul sebagai resource error pada console browser; ini merupakan respons yang diharapkan, bukan bukti kegagalan JavaScript.

## Kesimpulan

Kendala login akibat skema lama telah teratasi. Login tiga role, akses halaman, pembatasan role, dan pengalihan setelah logout berfungsi pada database `.env` setelah migrasi. Pengukuran dashboard mobile/tablet tidak menemukan overflow horizontal pada ukuran yang diuji.

Hasil ini tidak menggantikan uji visual lengkap semua halaman atau audit aksesibilitas. Klik backdrop drawer belum terverifikasi. Alur transaksi tidak dijalankan pada database `.env` sesuai batas persetujuan; hasil transaksi pada database disposable tetap tercatat terpisah di `docs/HASIL-UJI-COBA.md`. Runtime Docker/Nginx belum diuji.
