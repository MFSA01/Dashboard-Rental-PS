# Davin's Gaming Space

**Dashboard operasional rental PlayStation & cafe** — billing unit PS, pesanan F&B, dan laporan pendapatan dalam satu aplikasi.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)
![Vanilla JS](https://img.shields.io/badge/Frontend-Vanilla%20JS-F7DF1E?logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/License-MIT-22C55E)
![Status](https://img.shields.io/badge/Status-Aktif-16A34A)

---

## Deskripsi

Davin's Gaming Space adalah aplikasi web berbasis **PHP Native** untuk mengelola operasional harian sebuah rental PlayStation yang juga menjual makanan dan minuman. Aplikasi ini menggantikan pencatatan manual (buku kas, kalkulator, dan catatan pesanan) dengan satu dashboard terpusat.

Tiga pekerjaan utama yang ditangani aplikasi:

1. **Billing unit PS** — memantau unit mana yang sedang dipakai, menghitung biaya sewa secara otomatis (paket berdurasi tetap maupun *open billing* berbasis waktu berjalan).
2. **Pesanan F&B** — mencatat pesanan cafe dan menggabungkannya ke tagihan sesi PS yang sedang aktif, atau menjualnya langsung sebagai transaksi *walk-in*.
3. **Pelaporan** — merekap omzet rental PS dan cafe per periode, lengkap dengan grafik dan mode cetak.

Aplikasi dijalankan di web server Apache + MySQL (default: Laragon), tidak memerlukan framework, Composer, maupun proses build frontend.

---

## Daftar Isi

- [Deskripsi](#deskripsi)
- [Tentang Proyek](#tentang-proyek)
- [Fitur Utama](#fitur-utama)
- [Aturan Bisnis](#aturan-bisnis)
- [Teknologi & Arsitektur](#teknologi--arsitektur)
- [Kebutuhan Sistem](#kebutuhan-sistem)
- [Instalasi](#instalasi)
- [Struktur Proyek](#struktur-proyek)
- [Skema Database](#skema-database)
- [Keamanan, Troubleshooting, dan Lisensi](#keamanan-troubleshooting-dan-lisensi)

---

## Tentang Proyek

| Masalah Operasional | Solusi di Aplikasi |
|---|---|
| Durasi sewa PS dihitung manual, rawan salah hitung dan selisih tarif | Timer berjalan otomatis per unit; tarif per jam disimpan sebagai *snapshot* saat sesi dimulai sehingga perubahan tarif tidak mengubah tagihan yang sedang berjalan |
| Pesanan makanan/minuman dicatat terpisah dari tagihan sewa | Setiap pesanan dapat ditargetkan ke sesi PS aktif, lalu akumulasi F&B otomatis masuk ke total tagihan sesi |
| Sulit mengetahui unit mana yang kosong, dipakai, atau rusak | Grid kartu unit dengan status `idle`, `playing`, dan `maintenance` yang diperbarui real-time |
| Rekap pendapatan harian harus dijumlah ulang secara manual | Halaman laporan menjumlahkan pendapatan rental PS dan cafe per periode, siap dicetak |
| Stok menu tidak terkontrol saat ramai | Stok berkurang otomatis saat pesanan dibuat dan kembali bertambah saat item pesanan dihapus |

---

## Fitur Utama

### 1. Billing & Dashboard Unit PS

- Grid kartu seluruh unit PS beserta tipe konsol (PS4/PS5), tarif per jam, dan status unit.
- Timer *live* pada setiap kartu: hitungan mundur untuk mode **Paket**, hitungan maju untuk mode **Open Billing**.
- Mulai sesi baru dengan nama pelanggan, pilihan mode billing, dan durasi paket.
- Tambah waktu (**+ Waktu**) pada sesi paket, termasuk perhitungan biaya tambahan.
- Bayar & selesaikan sesi: biaya sewa PS digabung dengan total F&B menjadi satu grand total.
- Ringkasan cepat di bagian atas halaman: **Unit Aktif**, **Unit Tersedia**, dan **Pendapatan Hari Ini**.

### 2. Cafe & F&B Order (POS)

- Katalog menu dengan pencarian nama dan filter kategori **Makanan**, **Snack**, dan **Minuman**.
- Keranjang pesanan dengan pengaturan jumlah (qty) sebelum disimpan.
- Target pesanan dapat diarahkan ke **sesi PS aktif** (masuk tagihan sesi) atau **walk-in** (dibayar langsung).
- Pengurangan stok menu otomatis saat pesanan dibuat.
- Hapus item pesanan dengan pengembalian stok dan penyesuaian total tagihan sesi.
- Penanda menu habis/nonaktif sehingga tidak bisa dipesan.

### 3. Laporan Keuangan

- Filter periode: hari ini, 7 hari terakhir, bulan ini, dan rentang tanggal kustom.
- Empat kartu ringkasan: **Total Transaksi**, **Pendapatan Rental PS**, **Pendapatan Cafe/F&B**, dan **Total Omzet Gabungan**.
- Grafik batang Chart.js: dua seri berdampingan (Rental PS vs Cafe/F&B) untuk omzet per hari.
- Tabel **Riwayat Rental PlayStation** (sesi `completed` dan `paid`) serta **Riwayat Pesanan Cafe (Walk-in)** pada periode terpilih.
- Mode cetak A4 dengan header laporan khusus (nama rental, periode, waktu cetak) dan penyembunyian elemen navigasi.

### 4. Master Data

- CRUD unit PS: nama unit, tipe konsol, tarif per jam, dan status.
- CRUD menu F&B: nama, kategori, harga, stok, dan ketersediaan.
- Form tambah/ubah ditampilkan melalui modal tanpa berpindah halaman.
- Validasi integritas: data yang sudah punya riwayat transaksi tidak dapat dihapus, hanya dinonaktifkan atau diubah statusnya.

### 5. Settings

- Identitas rental (nama yang tampil di aplikasi dan header cetak).
- Kebijakan **Open Billing**: durasi minimal penagihan dan kelipatan pembulatan waktu.
- Profil admin: nama lengkap dapat diubah, sedangkan username bersifat permanen (read-only).
- Ganti password dengan verifikasi password lama (disimpan sebagai hash bcrypt).

### Tangkapan Layar

Screenshot belum disertakan di repositori. Untuk melengkapinya, simpan gambar ke folder `docs/screenshots/` sesuai daftar berikut, lalu ganti baris komentar di bawah tabel dengan sintaks gambar Markdown.

| Berkas Tujuan | Isi yang Disarankan |
|---|---|
| `docs/screenshots/dashboard-billing.png` | Grid kartu unit PS beserta timer |
| `docs/screenshots/cafe-pos.png` | Katalog menu dan keranjang pesanan |
| `docs/screenshots/session-detail.png` | Rincian F&B dan grand total sesi |
| `docs/screenshots/report.png` | Kartu ringkasan dan grafik laporan |
| `docs/screenshots/master-data.png` | Tabel unit PS dan menu F&B |

```markdown
<!-- Setelah gambar tersedia, ganti komentar ini dengan:
![Dashboard Billing](docs/screenshots/dashboard-billing.png)
![Cafe POS](docs/screenshots/cafe-pos.png)
![Detail Sesi](docs/screenshots/session-detail.png)
![Laporan](docs/screenshots/report.png)
![Master Data](docs/screenshots/master-data.png)
-->
```

---

## Aturan Bisnis

Aturan berikut diterapkan langsung di logika aplikasi, bukan sekadar panduan penggunaan.

**Mode Paket (countdown)**

- Pelanggan memilih durasi paket (mis. 60 atau 120 menit); biaya dihitung dari tarif unit × durasi.
- Durasi paket dibatasi minimal 30 menit dan maksimal 24 jam.
- `scheduled_end_time` menjadi target selesai dan timer tampil sebagai hitungan mundur.
- Saat waktu habis, kartu unit menampilkan penanda **BILLING HABIS** agar segera ditindaklanjuti.
- Fitur **+ Waktu** menambah durasi serta biaya sesi yang sedang berjalan.

**Mode Open Billing (count-up)**

- Biaya dihitung dari durasi aktual dengan fungsi `calculate_open_billing_cost()`.
- Durasi di bawah batas minimal (`open_billing_min_minutes`) tetap ditagih sebesar batas minimal.
- Kelebihan durasi dibulatkan ke atas per `open_billing_rounding_minutes` (mis. kelipatan 15 menit).
- Kedua nilai tersebut dapat diubah di halaman **Settings**.

**Penggabungan F&B**

- Pesanan yang ditargetkan ke sesi aktif menambah `fnb_cost`, lalu `total_cost` dihitung ulang sebagai `ps_cost + fnb_cost`.
- Pesanan *walk-in* disimpan tanpa `session_id` dan dibayar terpisah dari sesi PS.
- Harga satuan item disimpan sebagai *snapshot* saat dipesan, sehingga perubahan harga menu tidak mengubah riwayat.

**Integritas data**

- Unit PS tidak dapat dihapus bila sedang bermain atau memiliki riwayat sesi — gunakan status `maintenance`.
- Menu F&B tidak dapat dihapus bila sudah muncul di riwayat pesanan — gunakan status nonaktif.
- Semua operasi yang menyentuh lebih dari satu tabel dijalankan dalam transaksi database dengan rollback otomatis bila ada langkah gagal.
- Stok menu tidak boleh menjadi negatif; permintaan melebihi stok ditolak.

---

## Teknologi & Arsitektur

| Lapisan | Teknologi |
|---|---|
| Bahasa & runtime | PHP 8.1+ (prosedural, tanpa framework) |
| Database | MySQL 8 (InnoDB, `utf8mb4_unicode_ci`) |
| Akses data | Ekstensi `mysqli` dengan *prepared statement* |
| Tampilan | HTML5 + CSS vanilla (custom properties, tema hijau–hitam) |
| Interaksi | JavaScript vanilla (`assets/js/app.js`, `assets/js/timer.js`) |
| Aset eksternal | Google Fonts, Lucide Icons, SweetAlert2, Chart.js |

**Pola arsitektur**

- **Front controller sederhana** — `index.php` mengarahkan pengguna ke dashboard atau halaman login.
- **Pemisahan halaman dan aksi** — file di `pages/` hanya menampilkan data; seluruh perubahan data ditangani endpoint di `actions/`.
- **Post/Redirect/Get (PRG)** — setiap aksi POST memproses data, menyimpan pesan flash, lalu melakukan redirect agar refresh tidak mengirim ulang form.
- **Helper terpusat** — `config/helpers.php` menyediakan fungsi lintas halaman:

| Fungsi | Kegunaan |
|---|---|
| `base_url()`, `redirect()` | Pembentukan URL dan pengalihan halaman |
| `e()` | Escaping output HTML |
| `format_rupiah()`, `format_duration_label()` | Format mata uang dan durasi |
| `csrf_token()`, `csrf_field()`, `verify_csrf_token()` | Proteksi CSRF |
| `is_admin_logged_in()`, `require_admin_auth()` | Guard autentikasi |
| `set_flash()`, `get_flash()` | Pesan notifikasi sekali tampil |
| `get_setting()` | Pembacaan pengaturan dari tabel `settings` |
| `calculate_open_billing_cost()` | Perhitungan biaya open billing |
| `is_active_page()` | Penanda menu aktif di sidebar |

---

## Kebutuhan Sistem

| Komponen | Kebutuhan |
|---|---|
| Web server | Apache dengan modul PHP (Laragon/XAMPP) dan `AllowOverride All` |
| PHP | Minimal 8.1, disarankan 8.3+; ekstensi `mysqli`, `mbstring`, `json` |
| Database | MySQL 5.7+ atau 8.x |
| Browser | Chrome, Edge, Firefox, atau Safari versi terbaru |
| Koneksi internet | Diperlukan untuk memuat aset CDN (font, ikon, SweetAlert2, Chart.js) |

> Lingkungan pengembangan yang digunakan: Laragon di Windows, PHP 8.4, dan MySQL 8.4. Aplikasi berjalan tanpa `composer install` dan tanpa langkah build.

---

## Instalasi

### 1. Siapkan web server

Jalankan **Apache** dan **MySQL** dari panel Laragon (atau XAMPP). Pastikan tidak ada layanan lain yang memakai port 80 dan 3306.

### 2. Ambil kode proyek

```bash
git clone https://github.com/MFSA01/Dashboard-Rental-PS.git davins-gaming-space
```

Letakkan folder hasil clone di dalam web root, misalnya:

```text
C:\laragon\www\davins-gaming-space
```

Bila tidak memakai Git, unduh repositori sebagai ZIP lalu ekstrak ke lokasi yang sama.

### 3. Buat database dan impor skema

Buka terminal Laragon (CMD) di dalam folder proyek, lalu jalankan:

```bash
mysql -u root < database/schema.sql
mysql -u root rental_3551 < database/seed.sql
```

`database/schema.sql` sudah membuat database `rental_3551` beserta tujuh tabelnya, sehingga perintah pertama tidak perlu menyebutkan nama database.

> **Perhatian:** `database/schema.sql` menjalankan `DROP TABLE IF EXISTS` pada setiap tabel. Impor ulang hanya aman pada database baru atau setelah data lama di-*backup*, karena seluruh isi tabel akan dihapus sebelum dibuat kembali.

Jika MySQL menggunakan password, tambahkan `-p` pada kedua perintah.

**Alternatif satu langkah** — bila ingin memakai dump `rental_3551.sql` yang sudah berisi struktur sekaligus data, buat databasenya lebih dulu karena file tersebut tidak menyertakan `CREATE DATABASE`:

```bash
mysql -u root -e "CREATE DATABASE rental_3551 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root rental_3551 < rental_3551.sql
```

> Gunakan **CMD**, bukan PowerShell, karena PowerShell tidak mendukung operator redirection `<`. Bila perintah `mysql` tidak dikenali, jalankan dari Terminal Laragon atau gunakan path lengkap ke `mysql.exe`.

### 4. Sesuaikan koneksi database

Buka `config/database.php` dan sesuaikan bila konfigurasi server berbeda dari default:

```php
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'rental_3551';
$dbPort = 3306;
```

Berkas ini juga menetapkan timezone `Asia/Jakarta` dan `SET time_zone = '+07:00'` agar waktu sesi serta laporan konsisten dalam WIB.

### 5. Pastikan `.htaccess` aktif

Proyek menyertakan `.htaccess` di root, `config/`, `includes/`, dan `database/` untuk memblokir directory listing serta akses langsung ke file `.md`, `.sql`, dan partial PHP.

Agar aturan tersebut berjalan, konfigurasi Apache harus mengizinkan:

```apache
<Directory "C:/laragon/www">
    AllowOverride All
</Directory>
```

Lalu restart Apache. Tanpa `AllowOverride All`, aplikasi tetap dapat dipakai, tetapi proteksi direktori dan file sensitif tidak aktif.

### 6. Buka aplikasi dan login

Akses:

```text
http://localhost/davins-gaming-space/
```

Gunakan akun admin default:

| Username | Password |
|---|---|
| `admin` | `password` |

> **Wajib dilakukan:** ganti password default segera setelah login pertama melalui **Settings → Profil & Keamanan Admin**. Password disimpan sebagai hash `password_hash()` (bcrypt), bukan teks biasa.

`database/seed.sql` memuat **data demo**: 10 unit PS, 10 menu F&B, beberapa sesi berjalan, dan contoh pesanan. Hapus atau sesuaikan data tersebut sebelum dipakai untuk operasional nyata.

---

## Struktur Proyek

```text
davins-gaming-space/
├── index.php                      # Entry point: arahkan ke dashboard atau login
├── login.php                      # Halaman login admin
├── rental_3551.sql                # Dump phpMyAdmin (struktur + data), opsional
├── .htaccess                      # Nonaktifkan directory listing, blokir file sensitif
│
├── config/
│   ├── database.php               # Koneksi mysqli, charset utf8mb4, timezone WIB
│   ├── helpers.php                # Auth, CSRF, escaping, format, flash, setting
│   └── .htaccess                  # Blokir akses HTTP langsung
│
├── includes/
│   ├── header.php                 # Head HTML, CSS, dan aset CDN
│   ├── sidebar.php                # Navigasi utama + form logout (POST)
│   ├── topbar.php                 # Judul halaman dan identitas admin
│   ├── footer.php                 # Flash handler dan script global
│   └── .htaccess                  # Blokir akses HTTP langsung
│
├── pages/
│   ├── billing.php                # Dashboard grid unit PS dan timer
│   ├── session_detail.php         # Rincian sesi, F&B, dan pembayaran
│   ├── cafe.php                   # POS katalog menu dan keranjang
│   ├── report.php                 # Laporan omzet, grafik, dan cetak A4
│   ├── master.php                 # Master unit PS dan menu F&B
│   └── settings.php               # Pengaturan rental dan akun admin
│
├── actions/
│   ├── auth_login.php             # Proses login (throttle percobaan gagal)
│   ├── auth_logout.php            # Proses logout
│   ├── billing_start.php          # Mulai sesi baru
│   ├── billing_extend.php         # Tambah waktu sesi paket
│   ├── billing_pay.php            # Bayar dan selesaikan sesi
│   ├── cafe_order_create.php      # Simpan pesanan F&B
│   ├── cafe_order_delete_item.php # Hapus item + kembalikan stok
│   ├── master_station_save.php    # Simpan data unit PS
│   ├── master_menu_save.php       # Simpan data menu F&B
│   └── settings_save.php          # Simpan pengaturan, profil, password
│
├── assets/
│   ├── css/
│   │   ├── tokens.css             # Variabel warna, tipografi, spacing
│   │   ├── layout.css             # Kerangka halaman dan sidebar
│   │   └── components.css         # Kartu, tombol, tabel, modal, form
│   └── js/
│       ├── app.js                 # Modal, filter, keranjang, konfirmasi
│       └── timer.js               # Timer countdown dan count-up
│
└── database/
    ├── schema.sql                 # Struktur 7 tabel (InnoDB, utf8mb4)
    ├── seed.sql                   # Data demo awal
    └── .htaccess                  # Blokir akses HTTP langsung
```

---

## Skema Database

Database `rental_3551` terdiri dari tujuh tabel:

| Tabel | Fungsi |
|---|---|
| `admins` | Akun admin (username, hash password, nama lengkap) |
| `stations` | Unit PS: nama, tipe konsol, tarif per jam, status |
| `billing_sessions` | Sesi sewa: pelanggan, mode billing, waktu, biaya PS, biaya F&B, total, status bayar |
| `fnb_menus` | Katalog F&B: kategori, harga, stok, ketersediaan |
| `fnb_orders` | Header pesanan: unit, sesi (opsional), pelanggan, total, status bayar |
| `fnb_order_items` | Detail item pesanan: menu, jumlah, harga satuan, subtotal |
| `settings` | Pengaturan aplikasi berbentuk key–value |

**Relasi utama**

```text
stations ────────< billing_sessions
    │                     │
    │                     └──< fnb_orders ──< fnb_order_items >── fnb_menus
    └──< fnb_orders
```

- `stations → billing_sessions` memakai `ON DELETE RESTRICT`: unit yang punya riwayat sesi tidak bisa dihapus.
- `stations → fnb_orders` memakai `ON DELETE SET NULL`, sehingga riwayat pesanan tetap ada bila unit dihapus.
- `billing_sessions → fnb_orders` memakai `ON DELETE CASCADE`; `fnb_orders.session_id` bernilai `NULL` untuk transaksi **walk-in**.
- `fnb_orders → fnb_order_items` memakai `ON DELETE CASCADE`.
- `fnb_menus → fnb_order_items` memakai `ON DELETE RESTRICT`: menu yang sudah pernah dipesan tidak bisa dihapus.
- `admins` dan `settings` berdiri sendiri (tanpa relasi).
- Kolom `hourly_rate_snapshot`, `unit_price`, dan `duration_minutes` menyimpan nilai historis agar laporan lama tidak berubah saat master data diperbarui.

---

## Keamanan, Troubleshooting, dan Lisensi

### Keamanan

- Autentikasi admin berbasis session; seluruh `pages/*` dan `actions/*` wajib login, kecuali `login.php` dan `actions/auth_login.php`.
- Cookie session memakai `HttpOnly`, `SameSite=Lax`, dan `Secure` otomatis saat HTTPS, plus `session.use_strict_mode` (anti session fixation).
- Setiap form POST membawa token CSRF dan diverifikasi dengan `hash_equals()`.
- Seluruh output dinamis dilewatkan `e()` (`htmlspecialchars`) sebelum dirender.
- Query database memakai *prepared statement*; tidak ada input pengguna yang disisipkan langsung ke SQL.
- Data yang dikirim ke JavaScript di-encode dengan `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`.
- Endpoint aksi hanya menerima `POST`; akses `GET` dialihkan dengan pesan peringatan.
- Login dibatasi 5 percobaan gagal per 60 detik (throttle berbasis session).
- Folder `config/`, `includes/`, dan `database/` diblokir dari akses HTTP langsung melalui `.htaccess`.

### Troubleshooting

| Gejala | Kemungkinan Penyebab | Solusi |
|---|---|---|
| Halaman "Koneksi Database Gagal" | MySQL belum berjalan, database belum diimpor, atau kredensial salah | Jalankan MySQL, impor `database/schema.sql`, lalu periksa `config/database.php` |
| Error 403 saat membuka halaman | Permission folder/file web root tidak dapat dibaca Apache | Periksa izin baca folder proyek dan konfigurasi `<Directory>` pada Apache |
| File `.sql`/`.md` atau folder `config/` masih bisa diakses lewat browser | `AllowOverride` dimatikan sehingga `.htaccess` diabaikan | Set `AllowOverride All` pada direktori web root, lalu restart Apache |
| Tampilan tanpa ikon atau grafik | Aset CDN gagal dimuat (offline/diblokir) | Hubungkan ke internet atau sediakan aset tersebut secara lokal |
| Jam sesi tidak sesuai WIB | Timezone PHP/MySQL berbeda | Pastikan `date_default_timezone_set('Asia/Jakarta')` dan `SET time_zone = '+07:00'` aktif |
| Login ditolak berulang | Terkena throttle 5 percobaan per 60 detik | Tunggu satu menit, lalu coba lagi |
| Perintah `mysql` tidak dikenali | Folder `bin` MySQL belum ada di `PATH` | Gunakan Terminal Laragon atau path lengkap ke `mysql.exe` |
| Timer tidak berjalan | `assets/js/timer.js` gagal dimuat | Periksa Console browser dan pastikan file aset tidak terblokir |

### Lisensi

Proyek ini dirilis di bawah **MIT License**.

Copyright (c) 2026 Davin's Gaming Space

Izin diberikan secara gratis kepada siapa pun yang memperoleh salinan perangkat lunak ini dan berkas dokumentasi terkait, untuk menggunakan, menyalin, memodifikasi, menggabungkan, menerbitkan, mendistribusikan, mensublisensikan, dan/atau menjual salinan perangkat lunak, dengan syarat pemberitahuan hak cipta dan izin di atas disertakan pada semua salinan atau bagian substansial perangkat lunak.

Perangkat lunak ini disediakan **"sebagaimana adanya"**, tanpa jaminan dalam bentuk apa pun, baik tersurat maupun tersirat, termasuk namun tidak terbatas pada jaminan kelayakan jual, kesesuaian untuk tujuan tertentu, dan tidak melanggar hak pihak lain. Dalam keadaan apa pun penulis atau pemegang hak cipta tidak bertanggung jawab atas klaim, kerugian, atau tanggung jawab lain yang timbul dari penggunaan perangkat lunak ini.
