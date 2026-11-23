# Technical Rules & Constraints for AI Agent

Patuhi seluruh aturan berikut secara ketat pada setiap file yang dibuat atau diubah untuk proyek **Davin's Gaming Space**:

## 1. Tech Stack Wajib
- **Backend:** PHP 8.3 **Native Prosedural** menggunakan ekstensi **`mysqli`** (gaya prosedural: `mysqli_connect`, `mysqli_prepare`, `mysqli_stmt_bind_param`, `mysqli_stmt_execute`, `mysqli_stmt_get_result`).
  - **DILARANG** menggunakan PDO, framework PHP (Laravel/CI), atau gaya OOP/Class yang tidak perlu.
- **Database:** MySQL (Laragon) dengan nama database tepat: **`rental_3551`**.
- **Frontend Styling:** **Vanilla CSS** murni dengan CSS Custom Properties (`:root` variables) di dalam `assets/css/`.
  - **DILARANG** menggunakan Tailwind CSS, Bootstrap, atau framework CSS lainnya.
- **Frontend Interactivity:** Vanilla JavaScript (`assets/js/`) untuk live timer, filter/pencarian cepat, modal, dan interaksi DOM.
- **CDN Diizinkan Hanya Untuk:** Google Fonts (`Chakra Petch`, `Plus Jakarta Sans`, `JetBrains Mono`), Lucide Icons, SweetAlert2, dan Chart.js.

## 2. Keamanan & Kualitas Kode PHP 8.3
- **Prepared Statements Wajib:** Setiap query yang menerima input user (GET/POST) WAJIB menggunakan `mysqli_prepare` dan `mysqli_stmt_bind_param`. Dilarang menyambungkan variabel langsung ke string SQL.
- **Anti-XSS:** Selalu bungkus output data dari database ke HTML menggunakan fungsi helper `e($string)` (`htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8')`).
- **Proteksi Autentikasi:** Setiap halaman selain `login.php` dan `actions/auth_login.php` wajib memanggil `require_admin_auth()`.
- **Password:** Gunakan `password_hash()` dan `password_verify()`.
- **Transaksi Database:** Operasi yang mengubah lebih dari 1 tabel sekaligus (mulai billing, simpan pesanan F&B + potong stok + update total sesi, selesaikan pembayaran) WAJIB menggunakan `mysqli_begin_transaction`, `mysqli_commit`, dan `mysqli_rollback`.
- **Kompatibilitas PHP 8.3:** Hindari *implicit null to string deprecation* (selalu gunakan null coalescing `?? ''` atau casting eksplisit).

## 3. Struktur Folder Baku
Gunakan struktur ini secara konsisten dan jangan membuat file acak di root:
```text
davins-gaming-space/
├── PRD.md
├── AGENT_RULES.md
├── database/
│   ├── schema.sql
│   └── seed.sql
├── config/
│   ├── database.php       # Koneksi mysqli ke rental_3551 & timezone Asia/Jakarta
│   └── helpers.php        # Auth guard, fungsi e(), format_rupiah(), flash message
├── includes/
│   ├── header.php         # Head HTML, Google Fonts, CSS, Lucide CDN
│   ├── sidebar.php        # Navigasi kiri ala dashboard rental PS
│   ├── topbar.php         # Judul halaman & badge Admin
│   └── footer.php         # Script JS global, SweetAlert flash handler
├── assets/
│   ├── css/
│   │   ├── tokens.css     # Variabel warna hijau-hitam & font
│   │   ├── layout.css     # Sidebar, topbar, grid responsif
│   │   └── components.css # Kartu PS, badge, timer, modal, tabel, form
│   └── js/
│       ├── timer.js       # Live countdown & count-up timer kartu PS
│       └── app.js         # Modal, filter pencarian, interaksi Cafe cart
├── pages/
│   ├── billing.php        # Dashboard utama kartu PS
│   ├── session_detail.php # Detail billing + pesanan F&B per unit PS
│   ├── cafe.php           # POS pemesanan makan & minum
│   ├── report.php         # Laporan & riwayat transaksi
│   ├── master.php         # CRUD Unit PS & Menu F&B
│   └── settings.php       # Pengaturan rental & ganti password admin
├── actions/               # Endpoint pemrosesan POST (PRG pattern: Post-Redirect-Get)
│   ├── auth_login.php
│   ├── auth_logout.php
│   ├── billing_start.php
│   ├── billing_extend.php
│   ├── billing_pay.php
│   ├── cafe_order_create.php
│   ├── cafe_order_delete_item.php
│   ├── master_station_save.php
│   ├── master_menu_save.php
│   └── settings_save.php
├── index.php              # Redirect ke pages/billing.php (atau login.php)
└── login.php              # Halaman Login Admin