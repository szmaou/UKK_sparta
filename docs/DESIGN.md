# SPARTA — Design System & UI Specification

> **SPARTA** — Sistem Peminjaman Barang & Alat · SMK Negeri 1 Leuwimunding
> Versi: 2.0 · Status: Mengikuti implementasi · Bahasa antarmuka: Indonesia
>
> Dokumen ini hanya mengatur **tampilan dan perilaku UI**. Kebutuhan fitur, hak akses,
> aturan stok/denda, data model, dan pengujian ada di [`PRD-SPARTA.md`](PRD-SPARTA.md).
> Bila dokumen ini dan kode berbeda, kode adalah kebenaran; perbarui dokumen.

## 1. Tujuan dan cara pakai

Rujukan tunggal untuk membuat atau mengubah antarmuka SPARTA: token, komponen, pola
halaman, microcopy, dan kontrak handoff. Baca bagian 3–8 sebelum menulis markup baru,
lalu bagian 9 untuk halaman yang disentuh.

Stack UI: HTML5 semantik, Tailwind CSS 4 + daisyUI 5, JavaScript vanilla
(`public/assets/js/app.js`), dirender oleh PHP native. Tanpa React/Vue/Bootstrap,
tanpa CDN runtime, tanpa framework UI tambahan. CSS dibangun lokal ke
`public/assets/css/app.css`.

## 2. Prinsip desain

1. **Satu sistem untuk tiga peran.** Shell, komponen, dan token sama; yang berbeda hanya menu dan aksi yang tersedia.
2. **Kerja dulu, dekorasi belakangan.** Hierarki jelas, tabel mudah dipindai, status eksplisit, satu aksi primer per halaman.
3. **Ringkas dan tenang.** Light theme, latar `base-200`, permukaan putih, tanpa gradient dekoratif, glassmorphism, atau animasi berlebih.
4. **Jujur pada data.** Jangan tampilkan angka, foto, atau tombol yang tidak punya sumber di server. Aksi yang belum ada diberi label `Belum tersedia`.
5. **Konsisten pada kata.** Satu istilah untuk satu hal: `Alat`, `Peminjaman`, `Pengembalian`, `Pengguna`, `Kata sandi`.
6. **Aksesibel sejak markup.** Label nyata, fokus terlihat, status tidak hanya warna.

## 3. Identitas visual

- **Nada:** tegas, rapi, efisien, terpercaya. Operasional sekolah, bukan korporat.
- **Tagline:** `Kelola alat. Pinjam lebih mudah.`
- **Logo:** `public/assets/img/SMK Negeri 1 Leuwimunding.svg`, rasio asli `1153:1364`, mengandung transparansi. Jangan distorsi, crop, atau recolor. Kelas `.logo-school` menjaga rasio (`aspect-ratio: 1153 / 1364; object-fit: contain`).
- **Penempatan logo:** sidebar `h-12 w-auto` berdampingan wordmark SPARTA; login desktop di panel brand dalam kotak putih `size-16 rounded-xl`; login mobile di atas form.
- **Wordmark:** teks `SPARTA` (`text-xl font-black tracking-tight text-indigo-700`) + sublabel `Peminjaman barang & alat` (`text-[11px] text-slate-500`).
- **Panel brand login:** kelas `.brand-grid` — latar `#312E81` dengan grid garis putih 6% berukuran 32px. Satu-satunya area gelap besar di aplikasi.

## 4. Token warna

### 4.1 Tema daisyUI `sparta`

Sumber tunggal: `resources/css/input.css`. Jangan menetapkan hex baru di view; pakai
kelas semantik (`bg-base-200`, `text-base-content`, `btn-primary`).

| Token | Nilai | Peran |
|---|---|---|
| `--color-base-100` | `#ffffff` | Kartu, panel, navbar, sidebar |
| `--color-base-200` | `#f8fafc` | Latar aplikasi, tile info, footer tabel |
| `--color-base-300` | `#e2e8f0` | Border, divider |
| `--color-base-content` | `#0f172a` | Teks utama |
| `--color-primary` | `#4f46e5` | Aksi primer, tautan, aksen |
| `--color-secondary` | `#475569` | Aksi sekunder netral |
| `--color-neutral` | `#0f172a` | Tombol filter/cari (`btn-neutral`) |
| `--color-info` | `#1e40af` | Informasi |
| `--color-success` | `#166534` | Berhasil, tersedia |
| `--color-warning` | `#92400e` | Menunggu, perhatian |
| `--color-error` | `#991b1b` | Gagal, tolak, hapus |

Nilai lokal di `:root`: `--color-brand-soft: #eef2ff` (latar menu aktif),
`--color-text-secondary: #475569`, `--sidebar-width: 15.5rem`, `font-family: Inter, …`.
Aksen tambahan dipakai lewat utility: `indigo-700` untuk eyebrow/kode, `indigo-950`
untuk kartu aksen dashboard, `slate-500/600` untuk teks sekunder dan metadata.

### 4.2 Warna status (kelas manual, bukan `badge-success`)

Badge status memakai pasangan kelas Tailwind penuh. Wajib berupa string literal utuh,
bukan hasil rangkaian (`'text-' . $warna`) — pemindai Tailwind tidak menemukannya.

| Status | Label UI | Kelas badge |
|---|---|---|
| `pending` | Menunggu | `border-amber-200 bg-amber-50 text-amber-800` |
| `approved` | Disetujui | `border-emerald-200 bg-emerald-50 text-emerald-800` |
| `rejected` | Ditolak | `border-rose-200 bg-rose-50 text-rose-800` |
| `borrowed` | Sedang Dipinjam | `border-blue-200 bg-blue-50 text-blue-800` |
| `return_requested` | Menunggu Verifikasi | `border-sky-200 bg-sky-50 text-sky-800` |
| `returned` | Dikembalikan | `border-slate-200 bg-slate-100 text-slate-700` |
| aktif / tersedia | Aktif, Tersedia | `border-green-200 bg-green-50 text-green-800` |
| nonaktif / lain | Nonaktif | `badge-ghost` |

Varian label per konteks tetap boleh berbeda kata (mis. `Menunggu Persetujuan` di sisi
peminjam), tetapi warna dan makna harus sama. Warna tidak boleh satu-satunya pembeda:
selalu ada teks status.

### 4.3 Aturan pakai warna

- Latar halaman `bg-base-200`; semua konten duduk di `card bg-base-100`.
- Teks utama tanpa kelas warna; deskripsi `text-slate-600`; metadata `text-xs text-slate-500`.
- Merah hanya untuk destruktif dan error. Jangan pakai merah untuk angka netral.
- Angka stok, jumlah, tanggal, dan rupiah selalu `tabular-nums`.

## 5. Tipografi

Font: `Inter, ui-sans-serif, system-ui, sans-serif` (dideklarasikan di `:root`).
Tidak ada webfont eksternal yang dimuat; fallback sistem dipakai bila Inter tidak ada.

| Peran | Kelas aktual | Catatan |
|---|---|---|
| Judul hero login | `text-5xl font-black leading-[1.06] tracking-tight` | Hanya panel brand |
| Judul halaman | `text-3xl font-black tracking-tight` + `sm:text-4xl` di halaman katalog/persetujuan | Satu per halaman |
| Eyebrow | `text-sm font-semibold text-indigo-700` | Di atas judul, huruf kecil biasa |
| Judul kartu | `text-lg font-bold` s.d. `text-2xl font-bold` | `card-title` |
| Body / deskripsi | default `1rem`, `text-slate-600` | Maks `max-w-2xl` untuk paragraf |
| Label form | `fieldset-label font-semibold` (login: `fieldset-legend`) | Selalu di atas input |
| Metadata | `text-xs text-slate-500` | Kode, tanggal, `@username` |
| Kode transaksi | `font-mono text-xs font-bold text-indigo-700` | Kode alat/pinjaman |
| Angka KPI | `text-2xl font-bold tabular-nums` | `stat-value` |

Jangan memakai `font-black` untuk body atau label; hanya untuk judul halaman dan wordmark.

## 6. Spacing, radius, elevation

- **Grid 4px.** Jarak umum: `gap-3` (12), `gap-4` (16), `gap-5` (20), `space-y-6` (24), `gap-8` (32).
- **Kontainer:** `mx-auto max-w-7xl space-y-6` untuk halaman daftar/dashboard; `mx-auto max-w-3xl space-y-6` untuk form dan detail; `max-w-md` untuk kolom login.
- **Padding konten:** main `p-4 sm:p-6 lg:p-8`; `card-body` `p-4 sm:p-6` (form `p-5 sm:p-7`).
- **Radius:** `--radius-field: 0.375rem` (6px, input/tombol), `--radius-box: 0.75rem` (12px, kartu), `rounded-xl` untuk wrapper tabel dan empty state, `rounded-lg` untuk tile info, `rounded-full` untuk avatar/ikon bulat.
- **Elevasi:** `shadow-sm` pada kartu; `shadow-xs` + `hover:shadow-md` pada kartu katalog; tanpa shadow pada tabel. Tidak ada modal overlay kecuali dialog tolak (bagian 9.9).
- **Border:** semua kartu `border border-base-300`; wrapper tabel `rounded-xl border border-base-300`; empty state `border-dashed`.

## 7. Ikon

Lucide, disimpan lokal di `public/assets/icons/lucide/`. Render hanya lewat helper
`icon($name)` yang memvalidasi nama terhadap `METADATA.json`; nama tak dikenal
menghasilkan string kosong. Tanpa CDN, tanpa paket ikon runtime.

- Ukuran: `.app-icon svg { width: 1.125rem; height: 1.125rem; stroke-width: 2 }` (18px).
- Selalu `aria-hidden="true"` pada pembungkus ikon; teks label ada di DOM.
- Ikon tombol tanpa teks wajib punya `aria-label` (mis. tombol tutup drawer, reset filter);
  teks judul saja tidak cukup untuk `title` — gunakan `aria-label`.
- Ikon terpakai: `layout-dashboard`, `users`, `tags`, `wrench`, `clipboard-list`,
  `refresh-cw`, `logs`, `square-check`, `file-text`, `search`, `plus`, `x`, `check`,
  `triangle-exclamation-point`, `circle-exclamation-point`, `trash`, `menu`, `log-out`,
  `circle-user`.
- Tidak ada ilustrasi, emoji, atau ikon dekoratif besar. Ikon bulat pada empty state
  (`size-12`/`size-14`, `rounded-full`, `bg-indigo-50 text-indigo-700`) adalah satu-satunya ornamen.

## 8. App shell dan responsivitas

`app/Views/layouts/app.php`, kerangka daisyUI `drawer lg:drawer-open`.

```text
┌──────────────┬───────────────────────────────────────────┐
│ logo + brand │ navbar: SPARTA / Peran · Judul · Profil   │  sticky, h-16
│              ├───────────────────────────────────────────┤
│ MENU PERAN   │ main: p-4 sm:p-6 lg:p-8                   │
│ item aktif   │   konten halaman (max-w-7xl / max-w-3xl)  │
│              │                                           │
│ identitas    │ footer: SPARTA · Sistem Peminjaman …      │
│ Keluar       │                                           │
└──────────────┴───────────────────────────────────────────┘
```

- **Sidebar:** `w-64` saat drawer; `15.5rem` (248px) saat `lg` lewat override `.drawer-open`.
  Header 80px (logo + wordmark), label grup `Menu {Peran}` (`text-xs font-bold uppercase tracking-widest text-slate-400`), `<ul class="menu sidebar-menu">`, blok identitas (`bg-base-200`) dan tombol `Keluar` di bawah.
- **Menu aktif:** `aria-current="page"` pada `<a>`; gaya dari `.sidebar-menu a[aria-current="page"]` (`#eef2ff` + `#4338ca` + `font-weight: 650`). Jangan menambah kelas aktif manual.
- **Menu belum tersedia:** `<span class="menu-disabled" aria-disabled="true">` + badge `Belum tersedia`. Hanya dipakai bila rute benar-benar belum ada.
- **Navbar:** `navbar sticky top-0 z-20 min-h-16 border-b bg-base-100`, padding `px-4 lg:px-8`. Kiri: tombol hamburger `lg:hidden` + breadcrumb kecil `SPARTA / Peran` (`hidden sm:block`) + judul halaman. Kanan: nama, peran, avatar ikon `circle-user` dalam lingkaran `size-9 bg-indigo-50`.
- **Drawer mobile:** dikendalikan `public/assets/js/app.js` — toggle `checked`, `aria-expanded`, pindah fokus ke tombol tutup, tutup dengan `Escape`.
- **Breakpoint:** mobile-first; `sm` 640, `md` 768, `lg` 1024, `xl` 1280.
  Desktop ≥1024 sidebar tetap; <1024 drawer. Aksi header bertumpuk ke bawah sampai `sm`.
- **Tabel lebar:** selalu dalam `overflow-x-auto`; jangan biarkan seluruh halaman overflow horizontal.
- **Grid:** dashboard 1 kolom → `lg:grid-cols-[minmax(0,1.55fr)_minmax(18rem,0.75fr)]`; katalog `sm:grid-cols-2 lg:grid-cols-3`; form dua kolom `sm:grid-cols-2`.

## 9. Spesifikasi halaman

Semua halaman memakai pola kepala yang sama: eyebrow → judul → satu kalimat deskripsi,
lalu (bila ada) satu aksi primer di kanan saat `sm` ke atas. Form/detail memakai tautan
`← Kembali ke …` di atas judul, bukan tombol.

### 9.1 Login

Dua kolom `lg`, satu kolom di bawahnya. Kiri: panel `.brand-grid` gelap berisi logo,
wordmark, eyebrow `Sistem peminjaman sekolah`, hero `Kelola alat. / Pinjam lebih mudah.`,
dan satu paragraf. Kanan: `max-w-md` berisi logo ringkas (hanya <`lg`), `Selamat datang`,
judul `Masuk ke SPARTA`, deskripsi, dua field (`Username`, `Kata sandi`, tinggi `h-12`),
tombol `Masuk` `btn-primary h-12 w-full`, lalu catatan `Hubungi Admin SPARTA di sekolah.`
Error: `alert alert-error bg-red-50 text-red-900` di atas form, dengan judul
`Tidak dapat masuk`; kedua input diberi `aria-invalid` + `aria-describedby`.

### 9.2 Dashboard

Kepala sapaan (`Selamat datang, {nama}`) dengan badge peran dan intro, lalu satu baris
4 kartu `stat` KPI (`sm:grid-cols-2 xl:grid-cols-4`) yang masing-masing menampilkan
label, nilai angka nyata dari database, dan deskripsi singkat; seluruh kartu dapat
diklik ke modul terkait. KPI per peran:

- **Admin:** `Alat aktif`, `Pengguna aktif`, `Peminjaman aktif` (`approved` +
  `return_requested`), `Menunggu persetujuan` (`pending`).
- **Petugas:** `Menunggu persetujuan` (`pending`), `Sedang dipinjam` (`approved`),
  `Menunggu verifikasi` (`return_requested`), `Terlambat` (`approved` melewati
  `tanggal_jatuh_tempo`).
- **Peminjam:** milik sendiri — `Menunggu persetujuan`, `Disetujui`, `Menunggu verifikasi`,
  `Selesai` (`returned`).

Di bawah KPI: grid dua area — kartu utama berisi ikon, judul ruang kerja, dan deskripsi
per peran; kartu samping `bg-indigo-950 text-white` berisi pengingat `Akses sesuai peran`
dan `@username`. Tanpa grafik atau tren.

### 9.3 Pengguna

Halaman daftar: kepala `Master pengguna` → `Kelola pengguna`, aksi `Tambah Pengguna`.
Kartu berisi form pencarian (`input` + ikon `search` + `btn-neutral Cari`), lalu tabel:
`Nama dan username` (nama tebal + `@username` mono), `Peran` (badge), `Status`, `Dibuat`,
`Aksi`. Peran: `Admin` = `badge-primary`, `Petugas` = `badge-info`, `Peminjam` =
`badge-ghost`. Status: `Aktif` hijau, `Nonaktif` ghost. Aksi baris hanya `Edit`; status
akun diubah di form, bukan dari tabel. Footer kartu: `N hasil · Halaman X dari Y` + `join`
`Sebelumnya`/`Berikutnya`.

Form: `max-w-3xl`, fieldset dengan `fieldset-label`, field `Nama lengkap` (maks 100),
`Username`, `Password` (helper: wajib saat tambah, kosongkan saat edit), `Peran` (`select`),
`Status akun` (`Aktif`/`Nonaktif`). Error ringkas di atas form + pesan per field di bawah
input (`text-sm font-medium text-red-700`) dan `input-error`. Aksi: `Simpan` (`btn-primary`),
`Batal` (`btn-ghost`), lalu kartu `Nonaktifkan akun pengguna` di luar form utama.
Password tidak pernah tampil di tabel/detail.

### 9.4 Kategori

Kepala `Master inventaris` → `Kategori alat`, aksi `Tambah Kategori`, pencarian nama.
Tabel: `Nama`, `Deskripsi`, `Jumlah alat` (rata kanan), `Aksi` (`Edit`). Deskripsi kosong
ditampilkan `—`. Form `max-w-3xl`: `Nama kategori`, `Deskripsi` (`textarea`, opsional).
Kartu `Hapus kategori` berwarna `border-red-200 bg-red-50` dengan penjelasan konsekuensi
dan tombol `btn-error`. Bila data tidak bisa dihapus, jelaskan alasannya di kartu itu —
jangan sembunyikan tombol tanpa penjelasan.

### 9.5 Alat

Daftar: `Tambah Alat`, pencarian kode/nama, tabel `Kode dan alat` (thumbnail foto atau
placeholder ikon + nama tebal + kode mono), `Kategori`, `Total`, `Tersedia` (angka tebal
+ `N dipinjam` di bawahnya), `Status`, `Aksi` (`Edit`). Angka rata kanan `tabular-nums`.

Form `max-w-3xl`, `enctype="multipart/form-data"`: `Kode alat` (maks 30 karakter),
`Nama alat` (maks 150), `Kategori` (`select`, placeholder `Pilih kategori`),
`Stok total`, `Deskripsi`, `Foto alat` (opsional, JPG/PNG/WebP, maks 2 MB — saat edit
menampilkan foto saat ini dan opsi `Hapus foto ini`, unggahan baru menggantikan foto),
lalu `Status alat` (`Aktif`/`Nonaktif`). Stok tersedia tidak diedit langsung — nilainya
bergerak lewat transaksi, dan tabel daftar menampilkan turunannya. Tanpa foto, daftar dan
katalog menampilkan placeholder ikon.
Kartu hapus: `Hapus atau nonaktifkan alat`, menjelaskan bahwa alat dengan riwayat akan
dinonaktifkan, bukan dihapus. Foto fisik dihapus hanya saat baris alat benar-benar
dihapus; alat yang dinonaktifkan karena riwayat tetap menyimpan fotonya.

### 9.6 Peminjaman (Admin)

Tabs filter status dalam `join` (`role="tablist"`, tiap tab `role="tab"` +
`aria-selected`): `Semua`, `Menunggu`, `Disetujui`, `Ditolak`, `Menunggu Verifikasi`,
`Dikembalikan`. Tab aktif `btn-active btn-neutral`, sisanya `btn-ghost`. Di kanan, form
cari `input-sm` (kode/peminjam/alat) + `Cari` + `Reset`.
Tabel: `Kode & peminjam`, `Alat & jumlah` (+ `stok tersedia N`), `Jadwal`
(`pinjam → jatuh tempo`), `Status`, `Aksi` rata kanan.
Aksi baris: `Edit` hanya saat `pending`; `Hapus` (teks merah) saat `pending`/`rejected`;
selain itu teks `Terkunci` (`text-xs italic text-slate-400`).
Form koreksi `max-w-3xl`: tile ringkas kode/peminjam/alat/stok, field `Jumlah unit`,
`Tanggal pinjam`, `Jatuh tempo`, `Tujuan / catatan`, lalu `Simpan Perubahan` + `Batal`.
Halaman form juga memuat kartu hapus terpisah.

Semua konfirmasi destruktif saat ini memakai `onsubmit="return confirm('…')"`. Bila
diganti dialog, gunakan `<dialog class="modal">` seperti 9.9 dan pertahankan teks
konsekuensinya.

### 9.7 Pengembalian (Admin)

Tabs: `Semua`, `Belum Diverifikasi`, `Sudah Diverifikasi`; pencarian sama seperti 9.6.
Tabel: `Kode & peminjam`, `Alat & jumlah`, `Pengajuan`, `Penerimaan`, `Denda` (rata kanan),
`Aksi`. Kolom penerimaan menampilkan tanggal + badge kondisi + `Terlambat N hari`
(`text-[11px] font-semibold text-rose-700`) bila ada; yang belum diverifikasi menampilkan
badge `Menunggu verifikasi`. Aksi: `Edit` untuk yang sudah diverifikasi, `Hapus` untuk
yang belum.
Form koreksi: tile kode/peminjam/alat/jatuh tempo, `Tanggal diterima` (`min` = tanggal
pinjam/pengajuan, `max` = hari ini, helper teks), `Kondisi alat` (`select` dengan opsi
`Baik`, `Rusak Ringan`, `Rusak Berat`, `Hilang` beserta penjelasan singkat), `Simpan Perubahan`.

### 9.8 Log Aktivitas

Filter: `input` cari (`Cari aksi, entitas, atau pelaku`) + `select` aksi + `Terapkan`
+ `Reset`. Tabel: `Waktu` (`d M Y H:i`, `tabular-nums`), `Pelaku` (nama + `@username`, atau
`Sistem` miring), `Aksi` (`badge badge-sm badge-ghost` dengan label Indonesia), `Entitas`
(`tipe #id`), `Keterangan` (`max-w-md`). Read-only: tanpa aksi baris, tanpa hapus.
Halaman ini tidak boleh menampilkan kata sandi, token, atau ID sesi.

### 9.9 Persetujuan Peminjaman (Petugas)

Kepala: badge amber `Menunggu Verifikasi` + judul. Tabel: `Kode & Peminjam`,
`Alat & Ketersediaan`, `Jumlah`, `Jadwal Peminjaman`, `Tujuan & Catatan`,
`Aksi Verifikasi`. Aksi `Setujui` (`btn-success btn-xs sm:btn-sm`) dengan
`onsubmit="return confirm('Setujui peminjaman ini? Stok akan dicadangkan sejumlah N unit.')"`,
dinonaktifkan + `title` alasan bila stok kurang. `Tolak` (`btn-error`) membuka dialog native:
`<dialog class="modal">` → `modal-box max-w-md` dengan judul, ringkasan pinjaman,
`textarea` alasan wajib, `modal-action` berisi `Batal` (`method="dialog"`) dan
`Tolak Pengajuan`. Dialog dibuka lewat `showModal()` pada tombol dengan `aria-controls`;
`Escape` menutup. Ini satu-satunya modal di aplikasi — pola ini yang dipakai bila
konfirmasi butuh input.

### 9.10 Monitoring Pengembalian (Petugas)

Tabs `join`: `Aktif (Perlu Dipantau)`, `Menunggu Verifikasi`, `Selesai Dikembalikan`,
`Semua Status`; pencarian kode/peminjam/alat + `Cari` + `Reset`.
Tabel: `Kode & Peminjam`, `Alat & Jumlah`, `Jadwal & Keterlambatan`
(`Pinjam:` / `Tempo:` + chip rose `Terlambat N hari` dengan ikon), `Status & Catatan Fisik`,
`Denda`, `Aksi`. Chip terlambat memakai latar rose 50, bukan latar baris penuh.
Aksi `Verifikasi Fisik` (`btn-primary btn-sm`) hanya untuk `return_requested`, menuju form
verifikasi: tile ringkasan pinjaman, `Jumlah` read-only (`N unit (Pengembalian penuh)`),
`Tanggal diterima`, `Kondisi Fisik Saat Diterima`, lalu kartu info
`Ketentuan Pemulihan Stok & Denda` yang menyebut tarif aktif dan rumus
`hari terlambat × jumlah unit × tarif`. Tombol submit menyebut dampaknya:
`Simpan Verifikasi`. Stok tersedia hanya bertambah bila kondisi `Baik`; rusak atau
hilang tidak menambah stok. Semua angka dan tarif berasal dari server; tarif dikelola
Admin di halaman Pengaturan.

### 9.11 Laporan (Petugas)

Kepala + aksi `Cetak Laporan` (`btn-primary`, `target="_blank" rel="noopener"`).
Form filter dalam kartu `sm:grid-cols-4`: `Dari tanggal`, `Sampai tanggal` (`max` hari ini),
`Status`, `Tampilkan`. Tiga tile `stat`: `Jumlah transaksi`, `Total unit dipinjam`,
`Total denda` (`Rp` tanpa desimal). Tabel: `Kode`, `Peminjam`, `Alat & kategori`,
`Jumlah`, `Tanggal`, `Status`, `Denda`. Bila hasil melebihi batas, tampilkan
`alert alert-warning alert-soft` yang menyebut batas baris dan saran mempersempit rentang.
Angka ringkasan dan baris tabel diambil dari query yang sama agar konsisten.

### 9.12 Katalog (Peminjam)

Kepala: badge indigo `Peminjaman Sekolah`, judul `Katalog Alat`, aksi `Peminjaman Saya`
(`btn-outline`). Kartu filter: cari + `select` kategori + `Filter` + reset ikon `x`
(`btn-square`, `aria-label="Reset filter"`).
Grid kartu `article.card`: area foto `aspect-[4/3]` (gambar `object-cover`, atau
placeholder ikon bila tanpa foto), badge kategori, kode mono, nama, deskripsi `line-clamp-2`
(kosong → `Tidak ada deskripsi tambahan.` miring), footer `bg-base-200/40` berisi
`Stok Tersedia` + angka (`text-emerald-700` bila >0, `text-slate-500` bila 0) dan tombol
`Pinjam` (`btn-primary btn-sm`) atau tombol `Habis` (`btn-disabled`, `aria-disabled`).
Empty state membedakan "belum ada data" dan "tidak ada hasil filter", masing-masing
dengan aksi lanjut.

### 9.13 Ajukan Peminjaman (Peminjam)

Form `max-w-3xl` dengan tile ringkasan alat (foto/placeholder, nama, kode, stok). Field: `Jumlah`,
`Tanggal pinjam`, `Jatuh tempo`, `Tujuan` (`textarea`, helper menyebut maksimal 1.000
karakter). Semua label di atas input, error di bawah, `input-error`/`textarea-error`.
Aksi `Kirim Pengajuan Peminjaman` (`btn-primary`, `disabled` bila stok 0) dan `Batal`
(ghost, kembali ke katalog). Setelah sukses, redirect ke riwayat dengan `alert-success`;
isian tidak hilang saat validasi gagal.

### 9.14 Peminjaman Saya (Peminjam)

Kepala: badge indigo `Riwayat & Status`, aksi `Ajukan Pinjaman Baru` (`btn-primary`).
Tabel: `Kode & Tanggal` (kode mono + `Diajukan {tanggal}`), `Nama Alat`, `Jumlah`,
`Jadwal Pinjam`, `Status`, `Catatan Petugas`. Tanpa kolom aksi; halaman ini read-only.
Badge status memakai peta 4.2 dengan label khusus peminjam (`Menunggu Persetujuan`,
`Menunggu Verifikasi Kembali`). Catatan kosong ditampilkan `—`, bukan tooltip.

### 9.15 Pengembalian Saya (Peminjam)

Bukan riwayat pengembalian, melainkan daftar pinjaman yang masih bisa/telah dikembalikan.
Tabel: `Kode & Alat`, `Jumlah`, `Jadwal Pinjam`, `Status & Riwayat` (badge + tanggal
pengajuan/penerimaan + kondisi), `Denda`, `Aksi`. Badge: `approved` → `Siap Dikembalikan`,
`return_requested` → `Menunggu Verifikasi`, `returned` → `Sudah Dikembalikan`.
Aksi `Ajukan Pengembalian` hanya untuk `approved`, berupa form POST dengan
`onsubmit="return confirm('Kembalikan alat … (N unit)? Permintaan pengembalian akan dikirim ke Petugas.')"`.
Tidak ada halaman form terpisah: tanggal pengajuan dicatat server. Setelah terkirim,
badge berubah menjadi `Menunggu Verifikasi` dan stok belum berubah — jangan tampilkan
angka stok yang bertambah.

### 9.16 Pengaturan Denda (Admin)
Halaman `max-w-3xl`: kepala `Pengaturan sistem` → `Tarif Denda`, dua tile `stat`
(`Tarif saat ini` + `Rumus`), lalu form `Rp` + input angka (`0`–`1.000.000`, bilangan
bulat per hari per unit) dan tombol `Simpan Tarif`. Teks bantuan menjelaskan bahwa `0`
berarti bebas denda, dan kartu info menegaskan perubahan hanya berlaku untuk verifikasi
berikutnya — denda yang sudah tercatat tidak dihitung ulang. Nilai disimpan pada tabel
`pengaturan` (key/value) dan perubahan dicatat di log aktivitas.

## 10. Pola daftar, filter, dan state

Urutan baku satu halaman: kepala (eyebrow → judul → deskripsi → aksi primer) → alert
flash → kartu berisi form filter → isi → footer pagination.
Flash `success`/`error` tampil sebagai `alert alert-*-soft` dengan ikon dan `role`
(`status` untuk sukses, `alert` untuk error), hilang setelah navigasi berikutnya
(session flash).

- **Pagination:** server-side, 10 baris per halaman (log aktivitas 20); teks
  `N data · Halaman X dari Y`;
  `join` dengan `Sebelumnya`/`Berikutnya`; tombol nonaktif adalah `<span class="btn … btn-disabled" aria-disabled="true">`, bukan `<a>`.
  Pagination disembunyikan bila hanya satu halaman.
- **Empty state:** `rounded-xl border border-dashed border-base-300 bg-base-200/60 px-6 py-12 text-center`,
  ikon bulat, judul `text-lg font-bold`, satu paragraf `text-sm text-slate-600`, satu aksi.
  Bedakan "belum ada data" (aksi = buat) dari "tidak ada hasil filter" (aksi = reset).
- **Tabel:** `table` tanpa zebra; `<caption class="sr-only">`; header teks biasa;
  aksi di kolom terakhir rata kanan; kode selalu mono; angka `tabular-nums`.
- **Form:** satu kolom, `gap-5`; pasangan field dua kolom pada `sm`; aksi di footer
  dengan `border-t` — `Batal` (ghost) lalu aksi primer, urutan terbalik di mobile
  (`flex-col-reverse sm:flex-row`).
- **Belum ada** skeleton, toast, atau loading indicator. Jangan merancangnya tanpa
  menambah implementasinya; halaman saat ini render sinkron dari server.

## 11. Microcopy dan format

| Situasi | Teks |
|---|---|
| Login gagal | Judul `Tidak dapat masuk`, isi `Username atau kata sandi tidak sesuai.` |
| Akses ditolak | `Anda tidak memiliki izin untuk mengakses halaman ini.` |
| Data tersimpan | `… berhasil disimpan.` / `… berhasil dikoreksi.` |
| Kode duplikat | `Kode sudah digunakan. Gunakan kode lain.` |
| Stok kurang | `Jumlah melebihi stok tersedia saat ini (N unit).` |
| Konflik status | `Status pengajuan sudah berubah. Muat ulang data.` |
| Belum ada data | `Belum ada …` + satu kalimat penjelas + aksi buat |
| Tidak ada hasil | `Tidak ada … yang cocok dengan filter tersebut.` + aksi reset |
| Error server | `Gagal memproses permintaan. Coba lagi.` |
| Konfirmasi hapus | `Hapus …? Tindakan tidak dapat dibatalkan.` |

Format lokal: `16 Nov 2026` (daftar/tabel), `2026-11-16` (nilai `<input type="date">`),
`16 Nov 2026 14:30` (log), `Rp 10.000` (tanpa desimal, spasi setelah `Rp`),
`5 unit`. Jangan tampilkan istilah internal (`SQLSTATE`, `PDO`, nama tabel, stack trace).
Tombol memakai kata kerja dan kapitalisasi kalimat: `Simpan`, `Cari`, `Filter`, `Terapkan`,
`Cetak Laporan`, `Simpan Perubahan`.

## 12. Aksesibilitas

- HTML semantik: `header`/`nav`/`main`/`footer`, satu `h1` per halaman (judul di navbar), `h2` untuk judul konten, `th` + `caption` pada tabel.
- Semua input punya `<label for>` nyata. Placeholder bukan pengganti label.
- Ikon dekoratif `aria-hidden="true"`; tombol ikon saja punya `aria-label`.
- Fokus: `:focus-visible { outline: 2px solid #4f46e5; outline-offset: 2px }`. Jangan hapus outline.
- Drawer: `aria-controls`, `aria-expanded`, fokus pindah ke tombol tutup, `Escape` menutup.
- Dialog tolak: `showModal()` memberi focus trap bawaan; sediakan tombol `Batal` dan `Escape`.
- Error field: `aria-invalid="true"`; bila pesan error punya `id`, hubungkan dengan `aria-describedby`.
- Kontras target WCAG AA: 4.5:1 teks normal, 3:1 teks besar dan batas kontrol. Badge memakai latar 50 + teks 800 agar lolos.
- Target sentuh: aksi utama dan field minimal 40px (login `h-12`); aksi baris tabel boleh `btn-sm` (~32px) selama masih nyaman diklik.
- Jangan mengandalkan hover, warna, atau posisi saja untuk menyampaikan status.

## 13. Print

Hanya halaman `/petugas/laporan/cetak`: dokumen HTML mandiri tanpa drawer, navbar,
filter, atau JavaScript aplikasi.

- CSS inline di dalam view (tidak memuat `app.css`), `font-size: 12px`, `padding: 32px`,
  teks `#0f172a`, tabel `border-collapse` dengan garis `#cbd5e1`.
- Kepala: `SPARTA — Laporan Peminjaman Barang & Alat`, periode, status.
- Ringkasan tiga kotak: jumlah transaksi, total unit, total denda.
- Tabel 10 kolom dengan `thead` berulang; `No` urut 1..n.
- Footer: `Dicetak: {tanggal}` dan `SPARTA · Sistem Peminjaman Barang & Alat`.
- Toolbar (`Cetak / Simpan PDF`, `Kembali`) hanya layar; `@media print` menyembunyikannya,
  menghapus padding body, memakai `thead { display: table-header-group }` dan
  `tr { break-inside: avoid }`.
- Tidak ada logo pada cetakan saat ini; menambahkannya harus tetap hitam-putih dan tidak
  mengurangi area tabel.

## 14. Handoff implementasi

**Entry CSS aktual** (`resources/css/input.css`, dibangun ke `public/assets/css/app.css`):

```css
@import "tailwindcss" source("../../");
@plugin "daisyui";
@plugin "daisyui/theme" {
  name: "sparta";
  default: true;
  prefersdark: false;
  color-scheme: light;
  --color-base-100: #ffffff;
  --color-base-200: #f8fafc;
  --color-base-300: #e2e8f0;
  --color-base-content: #0f172a;
  --color-primary: #4f46e5;
  --color-primary-content: #ffffff;
  --color-secondary: #475569;
  --color-neutral: #0f172a;
  --color-info: #1e40af;
  --color-success: #166534;
  --color-warning: #92400e;
  --color-error: #991b1b;
  --radius-field: 0.375rem;
  --radius-box: 0.75rem;
  --radius-selector: 0.375rem;
  --depth: 1;
}
```

`<html lang="id" data-theme="sparta">`. Tema `sparta` adalah default dan satu-satunya;
dark mode di luar scope (`prefersdark: false`, `color-scheme: light`).

**Aturan kelas non-dinamis.** Tailwind memindai sumber; kelas yang dirangkai saat runtime
tidak ikut terbangun. Tulis kelas lengkap di string literal, termasuk peta status 4.2.
Pola yang dipakai kode saat ini: array `match` mengembalikan string kelas utuh.

**Pemetaan komponen → daisyUI**

| Komponen | Kelas |
|---|---|
| Shell + drawer | `drawer`, `drawer-toggle`, `drawer-content`, `drawer-side`, `drawer-overlay`, `drawer-open` |
| Navbar | `navbar`, `navbar-start`, `navbar-end` |
| Sidebar nav | `menu`, `menu sidebar-menu`, `menu-disabled` |
| Tombol | `btn`, `btn-primary`, `btn-neutral`, `btn-outline`, `btn-ghost`, `btn-error`, `btn-sm`, `btn-square`, `btn-disabled` |
| Input | `input`, `input-bordered`, `input-error`, `input-sm`, `select`, `textarea`, `fieldset`, `fieldset-label`, `fieldset-legend` |
| Kartu | `card`, `card-body`, `card-title`, `stat`, `stat-title`, `stat-value` |
| Badge | `badge`, `badge-sm`, `badge-ghost`, `badge-primary`, `badge-info` |
| Tabel | `table` + wrapper `overflow-x-auto rounded-xl border` |
| Tabs filter | `join`, `join-item`, `btn-active` |
| Alert | `alert`, `alert-success`, `alert-error`, `alert-warning`, `alert-soft` |
| Dialog | `modal`, `modal-box`, `modal-action`, `modal-backdrop` |
| Pagination | `join`, `join-item` |

**Kontrak frontend/backend.** CSS dibangun lokal (`npm ci && npm run build`), output
`public/assets/css/app.css` dimuat dari layout. Interaksi UI (drawer, dialog) di
`public/assets/js/app.js`, bukan otorisasi. Semua data dinamis di-escape `e()`. Flash
message lewat session + redirect (Post/Redirect/Get). Form mutasi memakai POST + `_token`.
Aplikasi harus jalan tanpa Node.js setelah CSS dibangun.

## 15. Gap desain vs implementasi

Catat di sini sebelum mengklaim sesuatu "sudah didesain":

1. **Konfirmasi destruktif** — masih `window.confirm()`, kecuali dialog tolak di 9.9. Belum ada modal konfirmasi hapus yang seragam.
2. **Skeleton / toast / loading** — tidak ada. Semua halaman render sinkron.
3. **Dashboard** — menampilkan KPI ringkasan per peran; belum menampilkan daftar transaksi atau aktivitas terbaru.
4. **Detail halaman** — peminjaman/pengembalian tidak punya halaman detail terpisah; informasi ada di baris tabel dan form koreksi.
5. **Dark mode** — sengaja tidak disediakan.
6. **Cetak** — hanya laporan Petugas; belum ada cetak bukti pinjam/kembali.
7. **Rujukan silang handoff** — `docs/DESIGN-HANDOFF.md` mengutip Section 9.3/9.4/9.5 dokumen ini; nomor bagian itu dipertahankan agar rujukan tetap valid.
8. **Label kata sandi** — login memakai `Kata sandi`, form pengguna memakai `Password`. Standar dokumen: `Kata sandi`; samakan saat form pengguna disentuh.

Perubahan UI yang menyentuh salah satu poin di atas wajib memperbarui bagian ini
sekaligus dokumen PRD bila aturan bisnisnya ikut berubah.
