# Davin's Gaming Space

Dashboard operasional **rental PlayStation & cafe** berbasis PHP Native. Satu aplikasi untuk mengelola billing unit PS secara real-time, mencatat pesanan makanan/minuman (Cafe/F&B) yang otomatis digabung ke tagihan sesi, serta merekap laporan pendapatan harian.

---

## Fitur Utama

| Modul | Fitur |
|---|---|
| **Billing** | Grid kartu unit PS, timer live (countdown paket & count-up open billing), mulai sesi, tambah waktu, bayar & selesaikan sesi |
| **Cafe / POS** | Katalog menu + pencarian & filter kategori, keranjang pesanan, target pesanan ke unit PS aktif atau walk-in lunas, pengurangan stok otomatis, hapus item dengan pengembalian stok |
| **Report** | Filter periode (hari ini / 7 hari / bulan ini / custom), 4 kartu ringkasan omzet, grafik stacked bar Chart.js, riwayat sesi PS & walk-in cafe, mode cetak A4 |
| **Master Data** | CRUD unit PS (nama, tipe konsol, tarif/jam, status) dan CRUD menu F&B (kategori, harga, stok, ketersediaan) via modal |
| **Settings** | Nama rental, kebijakan Open Billing (durasi minimal & kelipatan pembulatan), profil admin, ganti password |
| **Keamanan** | Login admin, session hardening, CSRF token di semua form POST, prepared statement, escaping output, guard integritas data relasi |

### Aturan bisnis penting

- **Paket (countdown)** — tarif dihitung dari durasi paket yang dipilih; `+ Waktu` menambah durasi & biaya.
- **Open Billing (count-up)** — biaya dihitung dari durasi berjalan dengan rumus `calculate_open_billing_cost()`: minimal `open_billing_min_minutes`, lalu dibulatkan ke atas per `open_billing_rounding_minutes`.
- Unit PS **tidak dapat dihapus** bila sedang bermain atau punya riwayat sesi (arahkan ke status Maintenance).
- Menu F&B **tidak dapat dihapus** bila sudah ada di riwayat pesanan (arahkan ke status Nonaktif).
- Semua operasi multi-tabel dijalankan dalam transaksi dengan rollback otomatis.

---

## Tech Stack

- **Backend:** PHP 8.3 (native prosedural, ekstensi `mysqli` + prepared statements)
- **Database:** MySQL 8 (`rental_3551`, InnoDB, utf8mb4)
- **Frontend:** Vanilla CSS (custom properties, tema hijau–hitam) + Vanilla JavaScript
- **CDN:** Google Fonts (Chakra Petch, Plus Jakarta Sans, JetBrains Mono), Lucide Icons, SweetAlert2, Chart.js (hanya di halaman Report)

---

## Instalasi Cepat (Laragon)

1. **Siapkan Laragon** — jalankan Apache & MySQL, lalu salin folder proyek ke:
   ```
   C:\laragon\www\davins-gaming-space
   ```

2. **Impor database** — buka terminal Laragon, lalu jalankan:
   ```bash
   mysql -u root rental_3551 < database/schema.sql
   mysql -u root rental_3551 < database/seed.sql
   ```
   Atau lewat phpMyAdmin/HeidiSQL: impor `database/schema.sql` lebih dulu, kemudian `database/seed.sql`.

3. **Cek konfigurasi koneksi** di `config/database.php` (default Laragon):
   ```php
   $dbHost = '127.0.0.1';
   $dbUser = 'root';
   $dbPass = '';
   $dbName = 'rental_3551';
   $dbPort = 3306;
   ```

4. **Buka aplikasi** di browser:
   ```
   http://localhost/davins-gaming-space/
   ```

---

## Akun Login Default

| Username | Password |
|---|---|
| `admin` | `password` |

> **Penting:** segera ganti password default setelah login pertama melalui **Settings → Profil & Keamanan Admin**. Password disimpan sebagai hash `password_hash()` (bcrypt).

---

## Struktur Folder

```text
davins-gaming-space/
├── index.php                  # Entry point (redirect ke dashboard / login)
├── login.php                  # Halaman login admin
├── .htaccess                  # Matikan directory listing, blokir .md/.sql/.log
├── config/
│   ├── .htaccess              # Blokir akses HTTP langsung ke folder config
│   ├── database.php           # Koneksi mysqli + timezone Asia/Jakarta
│   └── helpers.php            # Session, CSRF, e(), format_rupiah(), flash, auth guard
├── includes/
│   ├── .htaccess              # Blokir akses HTTP langsung ke partial PHP
│   ├── header.php             # Head HTML, CSS, CDN
│   ├── sidebar.php            # Navigasi + form logout (POST)
│   ├── topbar.php             # Judul halaman & badge admin
│   └── footer.php             # Flash handler + script global
├── pages/
│   ├── billing.php            # Dashboard kartu unit PS
│   ├── session_detail.php     # Detail sesi + rincian F&B + tagihan
│   ├── cafe.php               # POS katalog & keranjang
│   ├── report.php             # Laporan & grafik
│   ├── master.php             # Master unit PS & menu F&B
│   └── settings.php           # Pengaturan rental & akun admin
├── actions/                   # Endpoint POST (PRG pattern)
├── assets/css/                # tokens.css, layout.css, components.css
├── assets/js/                 # timer.js, app.js
└── database/                  # schema.sql, seed.sql (+ .htaccess blokir akses SQL)
```

---

## Catatan Keamanan

- Cookie session memakai `HttpOnly`, `SameSite=Lax`, dan `Secure` otomatis saat HTTPS, plus `session.use_strict_mode` (anti session fixation).
- Setiap form POST membawa token CSRF; endpoint action memvalidasi dengan `hash_equals()`.
- Semua output dinamis ke HTML/atribut melewati `e()` (`htmlspecialchars`).
- Data yang dikirim ke JavaScript di-encode dengan flag `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`.
- Semua file `pages/*` dan `actions/*` wajib login, kecuali `actions/auth_login.php` dan `login.php`.
- Semua action hanya menerima `POST`; akses `GET` dialihkan dengan flash warning.
- Login dibatasi 5 percobaan gagal per 60 detik (throttle berbasis session).
- Folder `config/`, `includes/`, dan `database/` diblokir dari akses HTTP langsung lewat `.htaccess`.
