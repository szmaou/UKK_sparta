# SPARTA — Design Handoff & Audit Awal (Master Admin CRUD Focus)

> **Fase:** Eksekusi Terfokus — Master Admin CRUD (Pengguna, Kategori, Alat)  
> **Acuan Spesifikasi:** `docs/DESIGN.md` (Section 9.3 Pengguna, Section 9.4 Kategori, Section 9.5 Alat)  
> **Coverage Tracker:** `/home/san/Documents/sparta/design/coverage.json`  
> **Log Validasi:** `/home/san/Documents/sparta/design/master-admin-validation.log`, `/home/san/Documents/sparta/design/modules-validation.log`  
> **Tanggal Artefak:** 16 November 2026  
> **Status:** Scope desain inti selesai dan sudah dibersihkan. Total terkini: 8 halaman, 44 frame, 23.984 node (17 November 2026). Bukti terbaru: `design/cleanup-validation.log`.
>
> **Penting:** Penghapusan builder noninti mengubah 43 ID frame yang dipertahankan. ID, path module, ukuran, dan perintah ber-ID pada bagian riwayat di bawah adalah snapshot lama, bukan referensi eksekusi terkini. Gunakan `design/modules/index.json`, `design/coverage.json`, dan tabel remap lengkap di `design/cleanup-validation.log` untuk ID saat ini.

## Hasil Pembersihan Terbaru

- Dihapus: frame Typography Scale, Spacing & Radius System, Status Badges & Pills, Cards & Feedback Banners, berikut builder, module, dan screenshot terkait.
- Dihapus: modal tambah pengguna duplikat, tautan lupa kata sandi palsu pada login desktop, dan guard generator duplikat. File batch sementara dibersihkan setelah digunakan.
- Dipertahankan: seluruh 44 frame alur inti, contoh responsif, state autentikasi, sampel fokus/error, aset, generator, dan exporter.
- Validasi parent: `node --check design/generate_sparta.js && node --check design/export-modules.js && node design/export-modules.js --verify`, exit `0`. Hasil: 8 halaman, 44 frame, 23.984 node, 2.932 payload refs/blobs. Screenshot daftar pengguna dan login default ditinjau parent.
- JSON kanonik: 64.458.192 byte; module: 18.071.541 byte. Verifikasi lossless lulus.

Pekerjaan opsional tidak masuk backlog aktif. Penghapusan artefak hanya mencakup daftar yang disetujui pengguna; riwayat validasi tetap disimpan. PHP, DB, otorisasi, dan perubahan stok belum diuji.

## Riwayat Batch Sebelum Pembersihan

Bagian berikut menyimpan bukti pekerjaan sebelumnya. Angka dan ID di dalamnya tidak diperbarui agar riwayat tetap akurat.

---

## 1. Bukti 8 Frame Baru Master Admin CRUD

| No | Nama Frame Riil | Node ID Figcraft | Tipe & Field Penting (Sesuai Docs 9.3/9.4/9.5) | Aksi & Proteksi Bisnis | Evidence Screenshot |
|---|---|---|---|---|---|
| **1** | `Admin — Pengguna Tambah` | `8951` | Form Tambah: Nama Lengkap, Username unik, Password (wajib), Role, Status Aktif. | Tombol `Simpan Pengguna` & `Batal`. Validasi format field terstruktur. | `design/screenshots/Admin — Pengguna Tambah.png` |
| **2** | `Admin — Pengguna Edit` | `9527` | Form Edit: Nama Lengkap, Username unik (terkunci `budi.santoso`), Password baru (opsional, kosongkan jika tidak diubah), Role, Status. | Tombol `Simpan Perubahan` & `Batal`. Pencegahan nonaktif admin terakhir. | `design/screenshots/Admin — Pengguna Edit.png` |
| **3** | `Admin — Pengguna Detail` | `10102` | View Detail: Nama Lengkap, Username, Role, Status, Tanggal Terdaftar, Histori Peminjaman Terkait (`PJ-0001`, `PJ-0005`). | Password **tidak pernah dimunculkan** pada tampilan detail. Aksi `Edit Pengguna` & `Kembali`. | `design/screenshots/Admin — Pengguna Detail.png` |
| **4** | `Admin — Kategori Tambah` | `10668` | Form Tambah: Kode Kategori unik (3 huruf `OTO`), Nama Kategori (`Otomotif & Mesin`), Deskripsi opsional. | Tombol `Simpan Kategori` & `Batal`. | `design/screenshots/Admin — Kategori Tambah.png` |
| **5** | `Admin — Kategori Edit` | `11236` | Form Edit: Kode Kategori terkunci (`ELK`), Nama Kategori, Deskripsi. | **Proteksi Hapus Berelasi**: Mereferensikan 2 jenis alat (`ALT-002`, `ALT-004`), penghapusan fisik diblokir. Tombol `Simpan Perubahan` & `Batal`. | `design/screenshots/Admin — Kategori Edit.png` |
| **6** | `Admin — Alat Tambah` | `11804` | Form Tambah: Kode Alat unik (`ALT-005`), Nama Alat, Kategori (select dropdown), Lokasi Penyimpanan, Stok Total Awal, Kondisi Awal. | Tombol `Simpan Alat` & `Batal`. | `design/screenshots/Admin — Alat Tambah.png` |
| **7** | `Admin — Alat Edit` | `12382` | Form Edit: Kode Alat terkunci (`ALT-001`), Nama Alat, Lokasi Penyimpanan, Penyesuaian Stok Total. | **Proteksi Alokasi Stok**: 2 unit sedang dipinjam (`PJ-0001`). Stok tersedia tidak boleh diubah langsung tanpa audit rekonsiliasi. Tombol `Simpan Rekonsiliasi` & `Batal`. | `design/screenshots/Admin — Alat Edit.png` |
| **8** | `Admin — Alat Detail` | `12957` | View Detail: Foto thumbnail placeholder, Spesifikasi fisik, Lokasi penyimpanan, dan **3 Metrik Stok Terpisah**: Stok Tersedia (`5 Unit`), Sedang Dipinjam (`2 Unit (PJ-0001)`), dan Kondisi Rusak (`1 Unit`). | Aksi `Edit Master Alat` & `Kembali ke Daftar`. | `design/screenshots/Admin — Alat Detail.png` |

---

## 2. File Desain & Sinkronisasi Artefak

| Path File | Ukuran | Format & Keterangan |
|---|---|---|
| `/home/san/Documents/sparta/design/SPARTA.figcraft` | 10,642,917 B (10.6 MB) | Biner Figcraft native, sumber kanonik (8 halaman, 38 frame, 18.252 nodes) |
| `/home/san/Documents/sparta/design/SPARTA.json` | 48,996,733 B (46.7 MiB) | JSON Figcraft penuh, minified lossless (sebelumnya 121,975,732 B pretty) |
| `/home/san/Documents/sparta/design/modules/` | 15.417.670 B (2.982 file) | Read view modular lossless termasuk `index.json`: 8 page module, 38 frame module, 2.935 blob payload bersama |
| `/home/san/Documents/sparta/design/export-modules.js` | 19.384 B | Exporter + self-test + slice/verify CLI (dipanggil generator setelah assertions) |
| `/home/san/Documents/sparta/design/generate_sparta.js` | 140.721 B | Script otomasi mandiri dengan assertions + integrasi exporter |
| `/home/san/Documents/sparta/design/master-admin-validation.log` | 2.640 B | Log eksekusi perintah exact, exit status, dan bukti verifikasi |
| `/home/san/Documents/sparta/design/modules-validation.log` | 4.030 B | Log exporter, minify, verify, slice, dan ukuran before/after |
| `/home/san/Documents/sparta/design/coverage.json` | 3.446 B | Manifest pelacakan status master admin forms & sisa gap riil |
| `/home/san/Documents/sparta/design/screenshots/` | 4.2 MB (38 file) | File render visual PNG seluruh frame |

### 2.1 Cara Membaca Satu Area Admin (tanpa load SPARTA.json penuh)

Mulai dari `design/modules/index.json` (75 KB). Index memuat nama/ID page + frame,
path module, bytes, sha256, jumlah node, dependency eksplisit, metadata dokumen, dan
hash sumber kanonik.

```bash
# Index halaman Admin + daftar 13 frame (ID, nama, ukuran)
node -e "const i=require('./design/modules/index.json');const p=i.pages.find(p=>p.id===6520);console.log(p.frames.map(f=>[f.id,f.name,f.bytes]))"

# Satu frame Admin (~270 KB) — node, field layout, dan $ref payload
node -e "console.log(JSON.stringify(require('./design/modules/frames/6520-8951.json'),null,1))"

# Resolve payload path/vector hanya saat perlu
node -e "const n=require('./design/modules/frames/6520-8951.json');console.log(n.nodes[8952])"
# lalu baca design/modules/blobs/<hex>.json sesuai nilai $ref

# Slice full-schema + dependency closure (payload diekspansi)
node design/export-modules.js --frame 8951 > /tmp/admin-frame.json
node design/export-modules.js --page 6520 > /tmp/admin-page.json

# Integritas: modul direkonstruksi deep-equal terhadap canonical JSON
node design/export-modules.js --verify
```

Format module adalah **read view kustom, bukan file import Figcraft**: field berat
(`network`, `fills`, `stroke`, `strokes`, `child_layout`, `style`) diganti
`{"$ref":"sha256:<hex>"}`; nilai asli ada di `modules/blobs/<hex>.json` dan dipakai
bersama bila identik. Logo (node `2`) adalah shared master lintas halaman: 15.900 node
mereferensikannya; payload-nya di `modules/pages/1.json` dan blob terkait. Canonical
penuh tetap `design/SPARTA.json`; sumber desain kanonik tetap `SPARTA.figcraft`.

Ukuran: Admin page module **30.886 B**, frame Admin terbesar **293.477 B**, payload
module 15.342.372 B + index 75.298 B = 15.417.670 B vs pretty 121.975.732 B
(**−87,36%** untuk seluruh folder modules); minified canonical 48.996.733 B (**−59,83%**).


### Exact Commands & Exit Statuses (`master-admin-validation.log`)

```bash
# 1. Regenerasi dokumen Figcraft biner via script reproduksibel
node /home/san/Documents/sparta/design/generate_sparta.js
# Exit Status: 0 (OK) -> 18.252 nodes ditulis ke design/SPARTA.figcraft

# 2. Sinkronisasi konversi JSON Figcraft
figcraft-cli convert \
  /home/san/Documents/sparta/design/SPARTA.figcraft \
  /home/san/Documents/sparta/design/SPARTA.json
# Exit Status: 0 (OK) -> design/SPARTA.json sinkron (exporter generator lalu
# meminify ke 48.996.733 B dan me-refresh design/modules/)

# 3. Export render visual frame halaman Admin CRUD
figcraft-cli export \
  --automation-read-root /home/san/Documents/sparta \
  --automation-write-root /home/san/Documents/sparta \
  --page "04 — Admin CRUD" \
  --out /home/san/Documents/sparta/design/screenshots/ \
  /home/san/Documents/sparta/design/SPARTA.figcraft
# Exit Status: 0 (OK) -> 13 file PNG di halaman Admin CRUD diperbarui

# 4. Assert 8 frame Admin via module index (tanpa load SPARTA.json penuh)
node -e "
const i = require('./design/modules/index.json');
const admin = i.pages.find(p => p.id === 6520);
const want = [8951, 9527, 10102, 10668, 11236, 11804, 12382, 12957];
want.forEach(id => {
  if (!admin.frames.some(f => f.id === id)) throw new Error('Missing frame ' + id);
});
console.log('✓ All 8 frames verified via modules/index.json');"
# Exit Status: 0 (OK)
```

---

## 3. Penyelesaian Scope Inti dan Batas Backlog

Desain inti sebagai patokan implementasi selesai. Riwayat penyelesaian batch:
1. **Petugas Operasional — selesai 17 November 2026:**
   - Serah-terima `PJ-0003`: frame `18782`.
   - Verifikasi aktif `PJ-0006`: frame `19362`; `PJ-0005` tetap arsip read-only.
   - Monitoring pengembalian: frame `19946`.
   - Filter dan preview laporan: frame `20549`.
   - Bukti: `design/petugas-validation.log` dan empat screenshot bernama sama dengan frame di `design/screenshots/`.
   - Validasi akhir parent: `node design/export-modules.js --verify`, exit `0`; 8 halaman, 42 frame, 20.660 node, 2.940 blob. Screenshot preview laporan dan verifikasi aktif juga ditinjau parent.
   - Ini artefak desain, bukan implementasi perilaku PHP/DB.
2. **Peminjam — selesai 17 November 2026:**
   - Detail alat `ALT-001`: frame `21190`.
   - Detail dan konfirmasi pembatalan pending `PJ-0004` milik Siti Rahma: frame `21756`.
   - Pengajuan pengembalian `PJ-0001` milik Andi Pratama: frame `22326`.
3. **Admin Transaksi — selesai 17 November 2026:**
   - Tambah peminjaman: frame `22895`.
   - Edit pending `PJ-0004`: frame `23475`.
   - Koreksi pengembalian `PJ-0005` dengan alasan dan audit: frame `24054`.

Bukti batch inti: `design/core-design-validation.log`; screenshot mengikuti nama frame di `design/screenshots/`. Parent menjalankan `node --check design/generate_sparta.js && node design/export-modules.js --verify`, exit `0`: 8 halaman, 48 frame, 24.105 node, 2.941 blob. Parent juga meninjau screenshot koreksi pengembalian dan konfirmasi pembatalan.

### Dikeluarkan dari backlog aktif

Sesuai keputusan pengguna, desain cukup menjadi patokan implementasi. Pekerjaan berikut tidak wajib dilanjutkan; file dan frame lama tetap disimpan:

- Ekspansi varian mobile/tablet dan drawer lengkap. Contoh responsif yang sudah ada tetap menjadi referensi; aksesibilitas dan responsivitas aplikasi tetap wajib diperhatikan saat implementasi.
- Konversi seluruh sampel visual menjadi engine component/instance reusable.
- Jalur edit modular yang aman. `design/modules/` tetap read view lossless, bukan format impor Figcraft. Edit melalui sumber desain/generator lalu ekspor ulang.

Tidak ada gap tersisa dalam scope inti yang disepakati (`design/coverage.json`). Ini bukan bukti implementasi aplikasi: PHP, DB, otorisasi, mutasi stok, dan perilaku POST belum diuji.
