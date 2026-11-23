-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 23, 2026 at 07:23 AM
-- Server version: 8.4.3
-- PHP Version: 8.4.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rental_3551`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password_hash`, `full_name`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', '2026-11-23 02:40:47');

-- --------------------------------------------------------

--
-- Table structure for table `billing_sessions`
--

CREATE TABLE `billing_sessions` (
  `id` int UNSIGNED NOT NULL,
  `station_id` int UNSIGNED NOT NULL,
  `customer_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_mode` enum('package','open') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'package',
  `hourly_rate_snapshot` int UNSIGNED NOT NULL COMMENT 'Tarif per jam saat sesi dimulai',
  `duration_minutes` int UNSIGNED DEFAULT NULL COMMENT 'Durasi paket (menit) atau total durasi final saat selesai',
  `start_time` datetime NOT NULL,
  `scheduled_end_time` datetime DEFAULT NULL COMMENT 'Target selesai untuk mode package (countdown)',
  `actual_end_time` datetime DEFAULT NULL COMMENT 'Waktu aktual saat sesi dihentikan/dibayar',
  `ps_cost` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Biaya sewa PS',
  `fnb_cost` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Akumulasi total pesanan F&B pada sesi ini',
  `total_cost` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'ps_cost + fnb_cost',
  `payment_status` enum('unpaid','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `session_status` enum('active','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_sessions`
--

INSERT INTO `billing_sessions` (`id`, `station_id`, `customer_name`, `billing_mode`, `hourly_rate_snapshot`, `duration_minutes`, `start_time`, `scheduled_end_time`, `actual_end_time`, `ps_cost`, `fnb_cost`, `total_cost`, `payment_status`, `session_status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'Affan', 'package', 8000, 120, '2026-11-23 09:00:47', '2026-11-23 11:00:47', '2026-11-23 11:43:43', 16000, 15000, 31000, 'paid', 'completed', 'Paket 2 Jam', '2026-11-23 02:40:47', '2026-11-23 04:43:43'),
(2, 2, 'Kevan', 'package', 8000, 240, '2026-11-23 08:25:47', '2026-11-23 16:00:36', NULL, 32000, 0, 32000, 'unpaid', 'active', 'Paket 1 Jam', '2026-11-23 02:40:47', '2026-11-23 07:00:36'),
(3, 7, 'Akil', 'open', 15000, NULL, '2026-11-23 08:50:47', NULL, NULL, 0, 12000, 12000, 'unpaid', 'active', 'Main bebas', '2026-11-23 02:40:47', '2026-11-23 04:58:02'),
(4, 8, 'Raka', 'package', 15000, 120, '2026-11-22 09:40:47', '2026-11-22 11:40:47', '2026-11-22 11:40:47', 30000, 15000, 45000, 'paid', 'completed', 'Lunas tunai', '2026-11-23 02:40:47', '2026-11-23 02:40:47'),
(7, 3, 'Davin', 'open', 8000, NULL, '2026-11-23 10:51:48', NULL, NULL, 0, 26000, 26000, 'unpaid', 'active', 'Mau namatin gta 6, bakal lama bos', '2026-11-23 03:51:48', '2026-11-23 05:07:58'),
(18, 1, 'jeki', 'package', 8000, 120, '2026-11-23 14:03:46', '2026-11-23 16:03:46', NULL, 16000, 14000, 30000, 'unpaid', 'active', '', '2026-11-23 07:03:46', '2026-11-23 07:04:24');

-- --------------------------------------------------------

--
-- Table structure for table `fnb_menus`
--

CREATE TABLE `fnb_menus` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('makanan','minuman','snack') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'makanan',
  `price` int UNSIGNED NOT NULL,
  `stock` int UNSIGNED NOT NULL DEFAULT '0',
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fnb_menus`
--

INSERT INTO `fnb_menus` (`id`, `name`, `category`, `price`, `stock`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 'Indomie Goreng Telur', 'makanan', 10000, 28, 1, '2026-11-23 02:40:47', '2026-11-23 07:04:24'),
(2, 'Indomie Kuah Soto Telur', 'makanan', 10000, 25, 1, '2026-11-23 02:40:47', '2026-11-23 05:03:22'),
(3, 'Nasi Goreng Spesial', 'makanan', 15000, 15, 1, '2026-11-23 02:40:47', '2026-11-23 04:55:48'),
(4, 'Kentang Goreng', 'snack', 12000, 19, 1, '2026-11-23 02:40:47', '2026-11-23 05:07:58'),
(5, 'Cireng Bumbu Rujak', 'snack', 10000, 20, 1, '2026-11-23 02:40:47', '2026-11-23 05:02:49'),
(6, 'Es Teh Manis', 'minuman', 5000, 50, 1, '2026-11-23 02:40:47', '2026-11-23 06:53:03'),
(7, 'Es Jeruk Segar', 'minuman', 7000, 30, 1, '2026-11-23 02:40:47', '2026-11-23 02:40:47'),
(8, 'Kopi Hitam Panas/Es', 'minuman', 6000, 40, 1, '2026-11-23 02:40:47', '2026-11-23 02:40:47'),
(9, 'Good Day Cappuccino Es', 'minuman', 8000, 35, 1, '2026-11-23 02:40:47', '2026-11-23 02:40:47'),
(10, 'Air Mineral Botol', 'minuman', 4000, 48, 1, '2026-11-23 02:40:47', '2026-11-23 07:04:24');

-- --------------------------------------------------------

--
-- Table structure for table `fnb_orders`
--

CREATE TABLE `fnb_orders` (
  `id` int UNSIGNED NOT NULL,
  `station_id` int UNSIGNED DEFAULT NULL COMMENT 'NULL jika pesanan walk-in tanpa main PS',
  `session_id` int UNSIGNED DEFAULT NULL COMMENT 'Mengikat tagihan F&B ke sesi PS yang sedang berjalan',
  `customer_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_amount` int UNSIGNED NOT NULL DEFAULT '0',
  `payment_status` enum('unpaid','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `ordered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fnb_orders`
--

INSERT INTO `fnb_orders` (`id`, `station_id`, `session_id`, `customer_name`, `total_amount`, `payment_status`, `ordered_at`) VALUES
(1, 1, 1, 'Affan', 15000, 'paid', '2026-11-23 09:15:47'),
(2, 7, 3, 'Akil', 12000, 'unpaid', '2026-11-23 09:20:47'),
(3, 8, 4, 'Raka', 15000, 'paid', '2026-11-22 09:40:47'),
(4, 3, 7, 'Davin', 26000, 'unpaid', '2026-11-23 12:07:58'),
(11, 1, 18, 'jeki', 14000, 'unpaid', '2026-11-23 14:04:24');

-- --------------------------------------------------------

--
-- Table structure for table `fnb_order_items`
--

CREATE TABLE `fnb_order_items` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `menu_id` int UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1',
  `unit_price` int UNSIGNED NOT NULL COMMENT 'Harga satuan saat dipesan',
  `subtotal` int UNSIGNED NOT NULL COMMENT 'quantity * unit_price'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fnb_order_items`
--

INSERT INTO `fnb_order_items` (`id`, `order_id`, `menu_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 1, 1, 10000, 10000),
(2, 1, 6, 1, 5000, 5000),
(3, 2, 4, 1, 12000, 12000),
(4, 3, 3, 1, 15000, 15000),
(5, 4, 1, 1, 10000, 10000),
(6, 4, 4, 1, 12000, 12000),
(7, 4, 10, 1, 4000, 4000),
(14, 11, 1, 1, 10000, 10000),
(15, 11, 10, 1, 4000, 4000);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('currency_symbol', 'Rp', '2026-11-23 02:40:47'),
('open_billing_min_minutes', '30', '2026-11-23 02:40:47'),
('open_billing_rounding_minutes', '15', '2026-11-23 02:40:47'),
('rental_name', 'Davin\'s Gaming Space', '2026-11-23 02:40:47');

-- --------------------------------------------------------

--
-- Table structure for table `stations`
--

CREATE TABLE `stations` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Contoh: PS 1, PS 2',
  `console_type` enum('PS4','PS5') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PS4',
  `hourly_rate` int UNSIGNED NOT NULL COMMENT 'Tarif per jam dalam Rupiah',
  `status` enum('idle','playing','maintenance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'idle',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stations`
--

INSERT INTO `stations` (`id`, `name`, `console_type`, `hourly_rate`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PS 1', 'PS4', 8000, 'playing', '2026-11-23 02:40:47', '2026-11-23 07:03:46'),
(2, 'PS 2', 'PS4', 8000, 'playing', '2026-11-23 02:40:47', '2026-11-23 02:40:47'),
(3, 'PS 3', 'PS4', 8000, 'playing', '2026-11-23 02:40:47', '2026-11-23 03:52:37'),
(4, 'PS 4', 'PS4', 8000, 'idle', '2026-11-23 02:40:47', '2026-11-23 03:51:47'),
(5, 'PS 5', 'PS4', 8000, 'idle', '2026-11-23 02:40:47', '2026-11-23 04:03:59'),
(6, 'PS 6', 'PS4', 8000, 'idle', '2026-11-23 02:40:47', '2026-11-23 04:01:17'),
(7, 'PS 7', 'PS5', 15000, 'playing', '2026-11-23 02:40:47', '2026-11-23 02:40:47'),
(8, 'PS 8', 'PS5', 15000, 'idle', '2026-11-23 02:40:47', '2026-11-23 02:40:47'),
(9, 'PS 9', 'PS5', 15000, 'idle', '2026-11-23 02:40:47', '2026-11-23 02:40:47'),
(10, 'PS 10', 'PS5', 15000, 'idle', '2026-11-23 02:40:47', '2026-11-23 02:40:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_admins_username` (`username`);

--
-- Indexes for table `billing_sessions`
--
ALTER TABLE `billing_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_billing_station_status` (`station_id`,`session_status`),
  ADD KEY `idx_billing_status` (`session_status`),
  ADD KEY `idx_billing_start_time` (`start_time`);

--
-- Indexes for table `fnb_menus`
--
ALTER TABLE `fnb_menus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_menus_category` (`category`);

--
-- Indexes for table `fnb_orders`
--
ALTER TABLE `fnb_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fnb_orders_station` (`station_id`),
  ADD KEY `idx_fnb_orders_session` (`session_id`),
  ADD KEY `idx_fnb_orders_date` (`ordered_at`);

--
-- Indexes for table `fnb_order_items`
--
ALTER TABLE `fnb_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fnb_items_order` (`order_id`),
  ADD KEY `idx_fnb_items_menu` (`menu_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `stations`
--
ALTER TABLE `stations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stations_name` (`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `billing_sessions`
--
ALTER TABLE `billing_sessions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `fnb_menus`
--
ALTER TABLE `fnb_menus`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `fnb_orders`
--
ALTER TABLE `fnb_orders`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `fnb_order_items`
--
ALTER TABLE `fnb_order_items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `stations`
--
ALTER TABLE `stations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `billing_sessions`
--
ALTER TABLE `billing_sessions`
  ADD CONSTRAINT `fk_billing_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `fnb_orders`
--
ALTER TABLE `fnb_orders`
  ADD CONSTRAINT `fk_fnb_orders_session` FOREIGN KEY (`session_id`) REFERENCES `billing_sessions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fnb_orders_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `fnb_order_items`
--
ALTER TABLE `fnb_order_items`
  ADD CONSTRAINT `fk_fnb_items_menu` FOREIGN KEY (`menu_id`) REFERENCES `fnb_menus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fnb_items_order` FOREIGN KEY (`order_id`) REFERENCES `fnb_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
