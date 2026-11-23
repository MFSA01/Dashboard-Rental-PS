-- ============================================================
-- Database Schema: Davin's Gaming Space
-- Database Name  : rental_3551
-- Engine         : InnoDB | Charset: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS `rental_3551`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `rental_3551`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `fnb_order_items`;
DROP TABLE IF EXISTS `fnb_orders`;
DROP TABLE IF EXISTS `fnb_menus`;
DROP TABLE IF EXISTS `billing_sessions`;
DROP TABLE IF EXISTS `stations`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `admins`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Tabel Admin (Single Role)
CREATE TABLE `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel Unit / Kursi PlayStation
CREATE TABLE `stations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL COMMENT 'Contoh: PS 1, PS 2',
  `console_type` ENUM('PS4', 'PS5') NOT NULL DEFAULT 'PS4',
  `hourly_rate` INT UNSIGNED NOT NULL COMMENT 'Tarif per jam dalam Rupiah',
  `status` ENUM('idle', 'playing', 'maintenance') NOT NULL DEFAULT 'idle',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stations_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel Sesi Billing (Mendukung Mode Paket & Open Billing)
CREATE TABLE `billing_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `station_id` INT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `billing_mode` ENUM('package', 'open') NOT NULL DEFAULT 'package',
  `hourly_rate_snapshot` INT UNSIGNED NOT NULL COMMENT 'Tarif per jam saat sesi dimulai',
  `duration_minutes` INT UNSIGNED DEFAULT NULL COMMENT 'Durasi paket (menit) atau total durasi final saat selesai',
  `start_time` DATETIME NOT NULL,
  `scheduled_end_time` DATETIME DEFAULT NULL COMMENT 'Target selesai untuk mode package (countdown)',
  `actual_end_time` DATETIME DEFAULT NULL COMMENT 'Waktu aktual saat sesi dihentikan/dibayar',
  `ps_cost` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Biaya sewa PS',
  `fnb_cost` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Akumulasi total pesanan F&B pada sesi ini',
  `total_cost` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ps_cost + fnb_cost',
  `payment_status` ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid',
  `session_status` ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_billing_station_status` (`station_id`, `session_status`),
  KEY `idx_billing_status` (`session_status`),
  KEY `idx_billing_start_time` (`start_time`),
  CONSTRAINT `fk_billing_station`
    FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel Master Menu Cafe (Makanan & Minuman)
CREATE TABLE `fnb_menus` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `category` ENUM('makanan', 'minuman', 'snack') NOT NULL DEFAULT 'makanan',
  `price` INT UNSIGNED NOT NULL,
  `stock` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_menus_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel Header Pesanan F&B (Berelasi ke Unit PS & Sesi Billing)
CREATE TABLE `fnb_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `station_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL jika pesanan walk-in tanpa main PS',
  `session_id` INT UNSIGNED DEFAULT NULL COMMENT 'Mengikat tagihan F&B ke sesi PS yang sedang berjalan',
  `customer_name` VARCHAR(100) DEFAULT NULL,
  `total_amount` INT UNSIGNED NOT NULL DEFAULT 0,
  `payment_status` ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid',
  `ordered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fnb_orders_station` (`station_id`),
  KEY `idx_fnb_orders_session` (`session_id`),
  KEY `idx_fnb_orders_date` (`ordered_at`),
  CONSTRAINT `fk_fnb_orders_station`
    FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_fnb_orders_session`
    FOREIGN KEY (`session_id`) REFERENCES `billing_sessions` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabel Detail Item Pesanan F&B
CREATE TABLE `fnb_order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `menu_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` INT UNSIGNED NOT NULL COMMENT 'Harga satuan saat dipesan',
  `subtotal` INT UNSIGNED NOT NULL COMMENT 'quantity * unit_price',
  PRIMARY KEY (`id`),
  KEY `idx_fnb_items_order` (`order_id`),
  KEY `idx_fnb_items_menu` (`menu_id`),
  CONSTRAINT `fk_fnb_items_order`
    FOREIGN KEY (`order_id`) REFERENCES `fnb_orders` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fnb_items_menu`
    FOREIGN KEY (`menu_id`) REFERENCES `fnb_menus` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabel Pengaturan Aplikasi
CREATE TABLE `settings` (
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;