# Dokumentasi Modul SPARTA

Dokumen ini menjadi indeks dokumentasi input, proses, dan output (IPO) setiap
modul, beserta fungsi, procedure, trigger, dan method yang menjalankannya.
Dokumentasi mengikuti implementasi pada working tree, termasuk modul backoffice
dan konfigurasi yang belum tentu sudah di-commit. Dokumen ini bukan laporan
hasil pengujian atau pernyataan bahwa seluruh requirement UKK sudah terpenuhi.

## Daftar modul

| No. | Modul | Cakupan | Dokumentasi |
|---|---|---|---|
| 01 | Core dan keamanan | Bootstrap, routing, rendering, session, RBAC, CSRF, helper | [Core dan keamanan](modules/01-core-dan-keamanan.md) |
| 02 | Autentikasi | Login, logout, identitas session, pembatasan percobaan login | [Autentikasi](modules/02-autentikasi.md) |
| 03 | Pengguna | CRUD akun, hashing password, penonaktifan, proteksi Admin | [Pengguna](modules/03-pengguna.md) |
| 04 | Kategori | Pencarian, pagination, tambah, edit, hapus kategori | [Kategori](modules/04-kategori.md) |
| 05 | Alat | Master alat, foto, stok, status, katalog | [Alat](modules/05-alat.md) |
| 06 | Peminjaman dan persetujuan | Katalog, pengajuan, riwayat, approval, koreksi administratif | [Peminjaman dan persetujuan](modules/06-peminjaman-dan-persetujuan.md) |
| 07 | Pengembalian | Pengajuan, monitoring, verifikasi fisik, koreksi administratif | [Pengembalian](modules/07-pengembalian.md) |
| 08 | Dashboard | Ringkasan sesuai role | [Dashboard](modules/08-dashboard.md) |
| 09 | Laporan | Filter periode/status, rekap, halaman cetak | [Laporan](modules/09-laporan.md) |
| 10 | Audit aktivitas | Pencatatan aplikasi, audit status DB, penelusuran log | [Audit aktivitas](modules/10-audit-aktivitas.md) |
| 11 | Pengaturan | Tarif denda dan perubahan yang diaudit | [Pengaturan](modules/11-pengaturan.md) |
| 12 | Database dan deployment | Schema, migration, SQL routines, seed, konfigurasi, Docker | [Database dan deployment](modules/12-database-dan-deployment.md) |

## Cara membaca

Setiap dokumen menjelaskan tujuan modul, aktor, route atau pemicu, dependensi,
serta tabel **input → proses → output**. Inventaris fungsi dan method menyertakan
helper internal agar alur Controller → Service/Model → database dapat ditelusuri.
Constructor dicatat sebagai pengikatan dependensi, bukan proses bisnis terpisah.
View disebut sebagai keluaran rendering; class CSS dan elemen HTML tidak dianggap
sebagai fungsi bisnis.

`GET` membaca data atau menampilkan form. Semua `POST` diperiksa terhadap token
CSRF `_token`; autentikasi dan role diperiksa terpusat. Validasi domain, ownership,
transaksi DB, dan hasil kegagalan dijelaskan pada modul yang menanganinya.

## Batasan dan sumber kebenaran

- Requirement: [`PRD-SPARTA.md`](PRD-SPARTA.md).
- Route aktual: [`app/routes.php`](../app/routes.php).
- Wiring dependensi: [`app/bootstrap.php`](../app/bootstrap.php).
- Struktur database: [`database/schema.sql`](../database/schema.sql), dilengkapi
  [`database/migrations/`](../database/migrations/) untuk database yang sudah ada.
- Fungsi SQL, stored procedure, dan trigger:
  [`database/routines.sql`](../database/routines.sql).

Nama tabel, field, method, dan route mengikuti kode, bukan rancangan yang belum
tersedia. Referensi test menunjukkan lokasi skenario pemeriksaan, bukan bukti
bahwa test dijalankan saat dokumentasi ditulis. Kredensial lokal, isi `.env`, dan
password produksi tidak dicantumkan.
