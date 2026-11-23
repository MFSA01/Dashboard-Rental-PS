USE `rental_3551`;

-- 1. Admin Default (Username: admin | Password: password)
INSERT INTO `admins` (`id`, `username`, `password_hash`, `full_name`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin Davin');

-- 2. Pengaturan Default
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('rental_name', 'Davin''s Gaming Space'),
('open_billing_min_minutes', '30'),
('open_billing_rounding_minutes', '15'),
('currency_symbol', 'Rp');

-- 3. 10 Unit PlayStation (6 PS4 & 4 PS5)
INSERT INTO `stations` (`id`, `name`, `console_type`, `hourly_rate`, `status`) VALUES
(1,  'PS 1',  'PS4', 8000,  'playing'),
(2,  'PS 2',  'PS4', 8000,  'playing'),
(3,  'PS 3',  'PS4', 8000,  'idle'),
(4,  'PS 4',  'PS4', 8000,  'idle'),
(5,  'PS 5',  'PS4', 8000,  'idle'),
(6,  'PS 6',  'PS4', 8000,  'idle'),
(7,  'PS 7',  'PS5', 15000, 'playing'),
(8,  'PS 8',  'PS5', 15000, 'idle'),
(9,  'PS 9',  'PS5', 15000, 'idle'),
(10, 'PS 10', 'PS5', 15000, 'idle');

-- 4. Master Menu Cafe (Makanan, Snack, Minuman)
INSERT INTO `fnb_menus` (`id`, `name`, `category`, `price`, `stock`, `is_available`) VALUES
(1,  'Indomie Goreng Telur',    'makanan', 10000, 30, 1),
(2,  'Indomie Kuah Soto Telur', 'makanan', 10000, 25, 1),
(3,  'Nasi Goreng Spesial',     'makanan', 15000, 15, 1),
(4,  'Kentang Goreng',          'snack',   12000, 20, 1),
(5,  'Cireng Bumbu Rujak',      'snack',   10000, 20, 1),
(6,  'Es Teh Manis',            'minuman', 5000,  50, 1),
(7,  'Es Jeruk Segar',          'minuman', 7000,  30, 1),
(8,  'Kopi Hitam Panas/Es',     'minuman', 6000,  40, 1),
(9,  'Good Day Cappuccino Es',  'minuman', 8000,  35, 1),
(10, 'Air Mineral Botol',       'minuman', 4000,  50, 1);

-- 5. Contoh Sesi Billing (Menggunakan waktu dinamis NOW())
INSERT INTO `billing_sessions`
  (`id`, `station_id`, `customer_name`, `billing_mode`, `hourly_rate_snapshot`, `duration_minutes`, `start_time`, `scheduled_end_time`, `actual_end_time`, `ps_cost`, `fnb_cost`, `total_cost`, `payment_status`, `session_status`, `notes`)
VALUES
-- Sesi 1: PS 1 sedang main Paket 2 Jam (sisa ~80 menit) + ada pesanan F&B Rp 15.000
(1, 1, 'Affan', 'package', 8000, 120,
 DATE_SUB(NOW(), INTERVAL 40 MINUTE),
 DATE_ADD(NOW(), INTERVAL 80 MINUTE),
 NULL, 16000, 15000, 31000, 'unpaid', 'active', 'Paket 2 Jam'),

-- Sesi 2: PS 2 Paket 1 Jam yang waktunya sudah habis 15 menit lalu (Trigger kartu merah BILLING HABIS)
(2, 2, 'Kevan', 'package', 8000, 60,
 DATE_SUB(NOW(), INTERVAL 75 MINUTE),
 DATE_SUB(NOW(), INTERVAL 15 MINUTE),
 NULL, 8000, 0, 8000, 'unpaid', 'active', 'Paket 1 Jam'),

-- Sesi 3: PS 7 sedang main Mode Open Billing (sudah jalan 50 menit) + pesan Kentang Goreng Rp 12.000
(3, 7, 'Akil', 'open', 15000, NULL,
 DATE_SUB(NOW(), INTERVAL 50 MINUTE),
 NULL,
 NULL, 0, 12000, 12000, 'unpaid', 'active', 'Main bebas'),

-- Sesi 4: Riwayat transaksi selesai kemarin (untuk data awal halaman Report)
(4, 8, 'Raka', 'package', 15000, 120,
 DATE_SUB(NOW(), INTERVAL 1 DAY),
 DATE_ADD(DATE_SUB(NOW(), INTERVAL 1 DAY), INTERVAL 120 MINUTE),
 DATE_ADD(DATE_SUB(NOW(), INTERVAL 1 DAY), INTERVAL 120 MINUTE),
 30000, 15000, 45000, 'paid', 'completed', 'Lunas tunai');

-- 6. Contoh Pesanan F&B yang Berelasi ke Unit PS & Sesi
INSERT INTO `fnb_orders` (`id`, `station_id`, `session_id`, `customer_name`, `total_amount`, `payment_status`, `ordered_at`) VALUES
(1, 1, 1, 'Affan', 15000, 'unpaid', DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
(2, 7, 3, 'Akil',  12000, 'unpaid', DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
(3, 8, 4, 'Raka',  15000, 'paid',   DATE_SUB(NOW(), INTERVAL 1 DAY));

-- 7. Detail Item Pesanan F&B
INSERT INTO `fnb_order_items` (`order_id`, `menu_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 1, 10000, 10000), -- Affan (PS 1): 1x Indomie Goreng Telur
(1, 6, 1, 5000,  5000),  -- Affan (PS 1): 1x Es Teh Manis
(2, 4, 1, 12000, 12000), -- Akil  (PS 7): 1x Kentang Goreng
(3, 3, 1, 15000, 15000); -- Raka  (PS 8): 1x Nasi Goreng Spesial