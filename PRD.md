# Product Requirements Document (PRD)
## Project: Davin's Gaming Space — PlayStation Rental & Cafe Dashboard

### 1. Ringkasan Proyek
**Davin's Gaming Space** adalah aplikasi web dashboard berbasis PHP Native untuk operasional kasir/admin rental PlayStation. Sistem ini memungkinkan admin memantau ketersediaan setiap unit/kursi PS secara real-time, menghitung durasi dan biaya main (billing), mencatat pesanan makanan & minuman (Cafe/F&B) yang terhubung langsung ke unit PS yang sedang aktif, serta merekap laporan pendapatan.

- **Nama Aplikasi:** Davin's Gaming Space
- **Nama Database:** `rental_3551`
- **Aktor / Role:** Single Role — **Admin** (wajib login untuk mengakses seluruh dashboard).

---

### 2. Spesifikasi Desain UI & Tema Visual
Mengadaptasi layout dashboard berbasis kartu (card grid) dengan sidebar kiri ramping, topbar judul halaman & profil admin, search bar, dan grid 5 kolom kartu unit PS (responsif menyesuaikan layar).

#### 2.1 Palet Warna (Dark Comfortable + Deep & Bright Green)
| Peran Warna | Hex | Penggunaan |
|---|---|---|
| Background Utama | `#0B0F0D` | Canvas halaman (hitam arang lembut, nyaman di mata) |
| Surface / Sidebar / Kartu IDLE | `#121815` | Sidebar, topbar, kartu PS status IDLE, panel form |
| Surface Elevated | `#18221D` | Input search, hover row tabel, dropdown |
| Border Halus | `#23332B` | Garis pemisah sidebar, border kartu IDLE & tabel |
| Hijau Tua (Primary) | `#14532D` | Latar kartu PS yang sedang AKTIF / bermain |
| Hijau Sedang (Accent) | `#16A34A` | Tombol utama, active state menu sidebar, header logo |
| Hijau Muda (Highlight) | `#4ADE80` | Teks highlight, badge IDLE, indikator aktif, hover aksen |
| Merah Status (Time Up) | `#991B1B` (aksen `#EF4444`) | Latar kartu PS saat durasi paket habis (BILLING HABIS) |
| Kuning Status (Unpaid) | `#FACC15` (teks `#1C1917`) | Badge "BELUM DIBAYAR" |
| Teks Utama | `#F0FDF4` | Judul, angka utama, nama customer |
| Teks Sekunder | `#94A39B` | Label kecil, metadata, ikon non-aktif |

#### 2.2 Tipografi (Google Fonts)
- **Heading / Brand / Nomor PS:** `Chakra Petch` (Weight: 600, 700)
- **Body / UI / Tabel:** `Plus Jakarta Sans` (Weight: 400, 500, 600, 700)
- **Timer & Nominal Rupiah:** `JetBrains Mono` (Weight: 500, 700) dengan CSS `font-variant-numeric: tabular-nums;`

#### 2.3 Library Eksternal yang Diizinkan (via CDN)
- **Icons:** Lucide Icons (`lucide`)
- **Notifikasi & Modal Konfirmasi:** SweetAlert2 (tema dark kustom sesuai palet hijau-hitam)
- **Grafik Laporan (opsional di halaman Report):** Chart.js

---

### 3. Struktur Navigasi & Halaman

1. **Login (`/login.php`)**
   - Halaman autentikasi tunggal untuk Admin (username & password terenkripsi `password_hash`).
   - Redirect otomatis ke dashboard billing setelah berhasil login.

2. **Billing / Dashboard Utama (`/index.php` atau `/pages/billing.php`)**
   - **Top Controls:** Search bar (`Cari unit PS atau nama customer...`) + filter pill status (`Semua`, `Aktif`, `Waktu Habis`, `Idle`).
   - **Summary Strip Ringkas:** Total Unit Aktif, Unit Tersedia (Idle), Pendapatan Hari Ini.
   - **Grid Kartu Unit PS (4–5 kolom di desktop):**
     - **Header Kartu:** Nama Unit (`PS 1`, `PS 2`, dst.), tipe konsol (`PS4` / `PS5`), dan link `Details`.
     - **Status Badge:**
       - Jika `IDLE`: Badge hijau muda `IDLE`.
       - Jika `PLAYING`: Badge kuning `BELUM DIBAYAR` + info mode (`Paket 2 Jam` atau `Open Billing`).
       - Jika `TIME UP` (paket habis): Kartu berubah warna merah lembut + badge `BELUM DIBAYAR` & `BILLING HABIS`.
     - **Info Tarif:** Menampilkan tarif per jam & nama paket.
     - **Timer Live (3 Kotak `JJ` `MM` `DD`):**
       - Mode **Paket (Countdown):** Menghitung mundur dari durasi paket hingga `00:00:00`, otomatis menandai `BILLING HABIS` via JS & sinkron server.
       - Mode **Open / Bebas (Count-up):** Menghitung naik berapa jam/menit user sudah bermain, dan menghitung estimasi biaya secara real-time.
     - **Baris Kontrol Aksi Kartu:**
       - Saat `IDLE`: Tombol **Mulai Main** (membuka modal input nama customer, pilih mode Paket/Open, dan durasi jika Paket).
       - Saat `PLAYING / TIME UP`: Tombol **`+ F&B`** (pesan makan/minum langsung ke sesi PS ini), **`+ Waktu`** (tambah durasi paket), dan tombol **Power / Bayar** (selesaikan sesi & konfirmasi pembayaran).
     - **Footer Kartu:** Nama `Customer` dan `Total` (otomatis menjumlahkan **Biaya Billing PS + Total Pesanan F&B**).
   - **Halaman / Modal Detail Sesi (`Details`):**
     - Rincian waktu mulai, durasi berjalan, tarif PS, daftar item makanan/minuman yang dipesan di kursi tersebut (bisa tambah/hapus item), subtotal PS, subtotal F&B, Grand Total, dan tombol **Bayar & Selesaikan Sesi**.

3. **Cafe / Pemesanan F&B (`/pages/cafe.php`)**
   - Menampilkan katalog menu Makanan & Minuman (filter kategori: Semua, Makanan, Minuman) lengkap dengan harga dan stok.
   - Panel **Keranjang Pesanan** di sebelah kanan:
     - Dropdown wajib memilih **Target Unit PS yang Sedang Aktif** (misal: `PS 1 — Affan`, mengambil `session_id` dan `station_id` secara relasional) atau opsi `Walk-in / Tanpa PS`.
     - Saat pesanan dikonfirmasi, pesanan masuk ke tabel `fnb_orders` & `fnb_order_items`, stok menu berkurang otomatis, dan **Total tagihan di kartu PS bersangkutan otomatis bertambah**.

4. **Report / Laporan (`/pages/report.php`)**
   - Filter rentang tanggal (Hari ini, 7 Hari Terakhir, Bulan Ini, Custom).
   - Kartu ringkasan: Total Transaksi, Pendapatan Rental PS, Pendapatan Cafe/F&B, dan Total Omzet Gabungan.
   - Tabel riwayat sesi selesai (Tanggal, Unit PS, Customer, Durasi, Biaya PS, Biaya F&B, Grand Total, Status Lunas) + detail item F&B per transaksi.

5. **Master Data (`/pages/master.php`)**
   - **Tab Unit PS:** Tambah, edit, hapus, atau ubah status maintenance unit/kursi PS (Nama Unit, Tipe PS4/PS5, Tarif per Jam).
   - **Tab Menu Cafe:** Tambah, edit, hapus menu makanan & minuman (Nama Menu, Kategori, Harga, Stok, Status Tersedia).

6. **Settings (`/pages/settings.php`)**
   - Pengaturan nama tempat rental, tarif default/pembulatan menit untuk mode Open Billing, dan ubah password admin.

---

### 4. Relasi Inti Database (`rental_3551`)
- `admins`: Menyimpan akun login admin.
- `stations`: Master data kursi/unit PS (`id`, `name`, `console_type`, `hourly_rate`, `status`).
- `billing_sessions`: Mencatat setiap sesi bermain, berelasi `FOREIGN KEY (station_id) REFERENCES stations(id)`.
- `fnb_menus`: Master data makanan & minuman.
- `fnb_orders`: Header pesanan F&B, berelasi ke `stations(id)` dan `billing_sessions(id)` (`NULL` hanya jika pesanan walk-in non-PS).
- `fnb_order_items`: Detail item pesanan, berelasi ke `fnb_orders(id)` dan `fnb_menus(id)`.
- `settings`: Key-value konfigurasi aplikasi.

---

### 5. Kriteria Selesai (Definition of Done)
- Admin bisa login & logout dengan aman menggunakan PHP Session.
- Kartu PS menampilkan status akurat dari MySQL dan timer berjalan mulus tanpa perlu refresh halaman.
- Memesan makanan/minuman untuk suatu unit PS langsung tercatat di database secara relasional dan otomatis memperbarui total biaya di kartu PS tersebut.
- Menyelesaikan pembayaran mengubah status sesi menjadi `paid`/`completed`, mengosongkan unit PS kembali ke `idle`, dan datanya langsung muncul di halaman Report.
- Seluruh UI menggunakan Vanilla CSS sesuai palet hitam-hijau tua-hijau muda, responsif, tanpa error PHP 8.3 (deprecation/warning).