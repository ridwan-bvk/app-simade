-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.30 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table pos_system_test.product_prices
CREATE TABLE IF NOT EXISTS `product_prices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `variant_id` int unsigned NOT NULL,
  `harga` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_variant` (`product_id`,`variant_id`),
  KEY `fk_variant` (`variant_id`),
  CONSTRAINT `fk_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_variant` FOREIGN KEY (`variant_id`) REFERENCES `master_variasi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table pos_system_test.product_prices: ~7 rows (approximately)
INSERT INTO `product_prices` (`id`, `product_id`, `variant_id`, `harga`, `created_at`, `updated_at`) VALUES
	(49, 11, 2, 30000.00, '2026-03-07 22:49:35', '2026-03-07 22:49:35'),
	(50, 11, 3, 35000.00, '2026-03-07 22:49:35', '2026-03-07 22:49:35'),
	(53, 10, 2, 110000.00, '2026-03-07 22:49:52', '2026-03-07 22:49:52'),
	(54, 10, 3, 120000.00, '2026-03-07 22:49:52', '2026-03-07 22:49:52'),
	(55, 9, 2, 60000.00, '2026-03-07 22:49:59', '2026-03-07 22:49:59'),
	(56, 9, 3, 60000.00, '2026-03-07 22:49:59', '2026-03-07 22:49:59'),
	(57, 9, 4, 55000.00, '2026-03-07 22:49:59', '2026-03-07 22:49:59');

-- Dumping structure for table pos_system_test.purchase_orders
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_id` int unsigned NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('unpaid','partial','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `idx_supplier_id` (`supplier_id`),
  CONSTRAINT `fk_purchase_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `master_suppliers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table pos_system_test.purchase_orders: ~1 rows (approximately)
INSERT INTO `purchase_orders` (`id`, `invoice_no`, `supplier_id`, `invoice_date`, `due_date`, `subtotal`, `total`, `paid_amount`, `status`, `notes`, `created_at`, `updated_at`) VALUES
	(3, 'PO-20260307230718-731', 1, '2026-03-08', NULL, 410000.00, 410000.00, 410000.00, 'paid', NULL, '2026-03-07 23:07:18', '2026-03-07 23:07:45');

-- Dumping structure for table pos_system_test.purchase_order_items
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` int unsigned NOT NULL,
  `product_id` int unsigned DEFAULT NULL,
  `product_code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_id` (`purchase_id`),
  CONSTRAINT `fk_purchase_items_order` FOREIGN KEY (`purchase_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table pos_system_test.purchase_order_items: ~1 rows (approximately)
INSERT INTO `purchase_order_items` (`id`, `purchase_id`, `product_id`, `product_code`, `product_name`, `qty`, `unit_cost`, `subtotal`, `created_at`) VALUES
	(2, 3, 12, 'SP-001', 'KACANG LOSAN (5kg)', 5.0000, 82000.00, 410000.00, '2026-03-07 23:07:18');

-- Dumping structure for table pos_system_test.sales_transactions
CREATE TABLE IF NOT EXISTS `sales_transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_printed` tinyint(1) NOT NULL DEFAULT '0',
  `printed_at` datetime DEFAULT NULL,
  `transaction_at` datetime NOT NULL,
  `customer_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `change_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table pos_system_test.sales_transactions: ~19 rows (approximately)
INSERT INTO `sales_transactions` (`id`, `invoice_no`, `status`, `is_printed`, `printed_at`, `transaction_at`, `customer_name`, `subtotal`, `discount`, `total`, `paid_amount`, `change_amount`, `created_at`) VALUES
	(7, 'DRF-20260306104004-752', 'pending', 1, '2026-03-09 07:09:36', '2026-03-07 05:43:44', 'MAMA MUQRI', 440000.00, 0.00, 440000.00, 0.00, 0.00, '2026-03-06 10:40:04'),
	(8, 'DRF-20260306104846-884', 'pending', 1, '2026-03-09 07:07:16', '2026-03-07 05:44:52', 'GINA', 120000.00, 0.00, 120000.00, 0.00, 0.00, '2026-03-06 10:48:46'),
	(9, 'DRF-20260306105039-603', 'pending', 1, '2026-03-09 07:06:01', '2026-03-09 07:05:20', 'TING ICUL', 390000.00, 0.00, 390000.00, 0.00, 0.00, '2026-03-06 10:50:39'),
	(10, 'DRF-20260306105108-756', 'pending', 1, '2026-03-09 07:06:42', '2026-03-07 05:45:11', 'KA INES', 280000.00, 0.00, 280000.00, 0.00, 0.00, '2026-03-06 10:51:08'),
	(11, 'DRF-20260306105137-384', 'pending', 1, '2026-03-09 07:07:47', '2026-03-07 05:45:39', 'BU DEWI', 170000.00, 0.00, 170000.00, 0.00, 0.00, '2026-03-06 10:51:37'),
	(12, 'DRF-20260306224628-339', 'pending', 1, '2026-03-09 07:08:53', '2026-03-07 05:46:28', 'MAMA AFIQAH', 440000.00, 0.00, 440000.00, 0.00, 0.00, '2026-03-06 22:46:28'),
	(13, 'DRF-20260306224641-465', 'pending', 1, '2026-03-09 07:11:37', '2026-03-07 05:46:41', 'TH BAHJAH', 60000.00, 0.00, 60000.00, 0.00, 0.00, '2026-03-06 22:46:41'),
	(14, 'DRF-20260306225034-163', 'pending', 1, '2026-03-07 17:08:14', '2026-03-07 17:07:42', 'TH BIBAH', 170000.00, 0.00, 170000.00, 0.00, 0.00, '2026-03-06 22:50:34'),
	(15, 'DRF-20260306225120-570', 'pending', 1, '2026-03-09 07:10:15', '2026-03-07 05:51:20', 'K SYIFA + MAMA K SYFA', 220000.00, 0.00, 220000.00, 0.00, 0.00, '2026-03-06 22:51:20'),
	(16, 'DRF-20260306225131-925', 'pending', 1, '2026-03-09 07:11:02', '2026-03-07 05:51:31', 'UST JAMAL', 120000.00, 0.00, 120000.00, 0.00, 0.00, '2026-03-06 22:51:31'),
	(17, 'DRF-20260306225145-967', 'pending', 1, '2026-03-09 07:08:06', '2026-03-07 05:51:45', 'K NAJWA', 120000.00, 0.00, 120000.00, 0.00, 0.00, '2026-03-06 22:51:45'),
	(18, 'DRF-20260306225619-871', 'pending', 1, '2026-03-09 07:04:46', '2026-03-07 17:19:52', 'TH TUTI', 440000.00, 0.00, 440000.00, 0.00, 0.00, '2026-03-06 22:56:19'),
	(19, 'TRX-20260306225653-770', 'paid', 0, NULL, '2026-03-07 05:56:53', 'KG ASEP NUR', 60000.00, 0.00, 60000.00, 60000.00, 0.00, '2026-03-06 22:56:53'),
	(20, 'DRF-20260306225744-156', 'pending', 1, '2026-03-07 17:02:17', '2026-03-07 05:57:44', 'ABI (TESTER BUANA)', 70000.00, 0.00, 70000.00, 0.00, 0.00, '2026-03-06 22:57:44'),
	(21, 'TRX-20260306225822-340', 'paid', 1, '2026-03-07 08:28:38', '2026-03-07 05:58:22', 'MAMA NUFAIL', 180000.00, 0.00, 180000.00, 180000.00, 0.00, '2026-03-06 22:58:22'),
	(22, 'TRX-20260306225940-773', 'paid', 1, '2026-03-09 07:03:56', '2026-03-07 05:59:40', 'MU', 120000.00, 0.00, 120000.00, 120000.00, 0.00, '2026-03-06 22:59:40'),
	(23, 'DRF-20260306230008-327', 'pending', 1, '2026-03-09 07:10:41', '2026-03-07 06:00:08', 'FITRI', 105000.00, 0.00, 105000.00, 0.00, 0.00, '2026-03-06 23:00:08'),
	(24, 'DRF-20260306230032-224', 'pending', 1, '2026-03-09 07:11:17', '2026-03-07 06:00:32', 'MPOK WAWAH', 70000.00, 0.00, 70000.00, 0.00, 0.00, '2026-03-06 23:00:32'),
	(25, 'DRF-20260307011641-266', 'pending', 1, '2026-03-07 08:24:59', '2026-03-07 08:16:57', 'TANTE LILIS', 1160000.00, 0.00, 1160000.00, 0.00, 0.00, '2026-03-07 01:16:41');

-- Dumping structure for table pos_system_test.sales_transaction_items
CREATE TABLE IF NOT EXISTS `sales_transaction_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` int unsigned NOT NULL,
  `product_id` int unsigned DEFAULT NULL,
  `product_code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_id` int unsigned DEFAULT NULL,
  `variant_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `qty` int NOT NULL DEFAULT '1',
  `unit_id_snapshot` int unsigned DEFAULT NULL,
  `unit_symbol_snapshot` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_base_qty_snapshot` decimal(15,4) NOT NULL DEFAULT '1.0000',
  `base_qty_total` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_transaction_id` (`transaction_id`),
  CONSTRAINT `fk_sales_items_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table pos_system_test.sales_transaction_items: ~26 rows (approximately)
INSERT INTO `sales_transaction_items` (`id`, `transaction_id`, `product_id`, `product_code`, `product_name`, `variant_id`, `variant_name`, `price`, `qty`, `unit_id_snapshot`, `unit_symbol_snapshot`, `unit_base_qty_snapshot`, `base_qty_total`, `subtotal`, `created_at`) VALUES
	(22, 7, 10, 'KCG-002', 'KACANG UKURAN 1 KG', 2, 'Reseler', 110000.00, 4, 1, 'g', 1000.0000, 4000.0000, 440000.00, '2026-03-06 22:43:45'),
	(25, 8, 10, 'KCG-002', 'KACANG UKURAN 1 KG', 3, 'Normal', 120000.00, 1, 1, 'g', 1000.0000, 1000.0000, 120000.00, '2026-03-06 22:44:52'),
	(26, 10, 10, 'KCG-002', 'KACANG UKURAN 1 KG', 2, 'Reseler', 110000.00, 2, 1, 'g', 1000.0000, 2000.0000, 220000.00, '2026-03-06 22:45:11'),
	(27, 10, 9, 'KCG-001', 'KACANG UKURAN 1/2 KG', 2, 'Reseler', 60000.00, 1, 1, 'g', 500.0000, 500.0000, 60000.00, '2026-03-06 22:45:11'),
	(28, 11, 10, 'KCG-002', 'KACANG UKURAN 1 KG', 2, 'Reseler', 110000.00, 1, 1, 'g', 1000.0000, 1000.0000, 110000.00, '2026-03-06 22:45:39'),
	(29, 11, 9, 'KCG-001', 'KACANG UKURAN 1/2 KG', 3, 'Normal', 60000.00, 1, 1, 'g', 500.0000, 500.0000, 60000.00, '2026-03-06 22:45:39'),
	(30, 12, 10, 'KCG-002', 'KACANG UKURAN 1 KG', 2, 'Reseler', 110000.00, 4, 1, 'g', 1000.0000, 4000.0000, 440000.00, '2026-03-06 22:46:28'),
	(31, 13, 9, 'KCG-001', 'KACANG UKURAN 1/2 KG', 3, 'Normal', 60000.00, 1, 1, 'g', 500.0000, 500.0000, 60000.00, '2026-03-06 22:46:41'),
	(34, 15, 10, 'KCG-002', 'KACANG UKURAN 1 KG', 2, 'Reseler', 110000.00, 2, 1, 'g', 1000.0000, 2000.0000, 220000.00, '2026-03-06 22:51:20'),
	(35, 16, 10, 'KCG-002', 'KACANG UKURAN 1 KG', 3, 'Normal', 120000.00, 1, 1, 'g', 1000.0000, 1000.0000, 120000.00, '2026-03-06 22:51:31'),
	(36, 17, 10, 'KCG-002', 'KACANG UKURAN 1 KG', 3, 'Normal', 120000.00, 1, 1, 'g', 1000.0000, 1000.0000, 120000.00, '2026-03-06 22:51:45'),
	(39, 19, 9, 'KCG-001', 'KACANG UKURAN 1/2 KG', 3, 'Normal', 60000.00, 1, 1, 'g', 500.0000, 500.0000, 60000.00, '2026-03-06 22:56:53'),
	(40, 20, 11, 'KCG-003', 'KACANG UKURAN 1/4 KG', 3, 'Normal', 35000.00, 2, 1, 'g', 250.0000, 500.0000, 70000.00, '2026-03-06 22:57:44'),
	(41, 21, 10, 'KCG-002', 'KACANG UKURAN 1 KG', 3, 'Normal', 120000.00, 1, 1, 'g', 1000.0000, 1000.0000, 120000.00, '2026-03-06 22:58:22'),
	(42, 21, 9, 'KCG-001', 'KACANG UKURAN 1/2 KG', 3, 'Normal', 60000.00, 1, 1, 'g', 500.0000, 500.0000, 60000.00, '2026-03-06 22:58:22'),
	(43, 22, 11, 'KCG-003', 'KACANG UKURAN 1/4 KG', 2, 'Reseler', 30000.00, 4, 1, 'g', 250.0000, 1000.0000, 120000.00, '2026-03-06 22:59:40'),
	(44, 23, 11, 'KCG-003', 'KACANG UKURAN 1/4 KG', 3, 'Normal', 35000.00, 3, 1, 'g', 250.0000, 750.0000, 105000.00, '2026-03-06 23:00:08'),
	(45, 24, 11, 'KCG-003', 'KACANG UKURAN 1/4 KG', 3, 'Normal', 35000.00, 2, 1, 'g', 250.0000, 500.0000, 70000.00, '2026-03-06 23:00:32'),
	(48, 25, 10, 'KCG-002', 'KACANG UKURAN 1 KG', 2, 'Reseler', 110000.00, 10, 1, 'g', 1000.0000, 10000.0000, 1100000.00, '2026-03-07 01:16:57'),
	(49, 25, 9, 'KCG-001', 'KACANG UKURAN 1/2 KG', 2, 'Reseler', 60000.00, 1, 1, 'g', 500.0000, 500.0000, 60000.00, '2026-03-07 01:16:57'),
	(50, 14, 9, 'KCG-001', 'KACANG UKURAN 1/2 KG', 4, 'Reseler Spesial', 55000.00, 2, 1, 'g', 500.0000, 1000.0000, 110000.00, '2026-03-07 10:07:42'),
	(51, 14, 11, 'KCG-003', 'KACANG UKURAN 1/4 KG', 2, 'Reseler', 30000.00, 2, 1, 'g', 250.0000, 500.0000, 60000.00, '2026-03-07 10:07:42'),
	(52, 18, 9, 'KCG-001', 'KACANG UKURAN 1/2 KG', 3, 'Normal', 60000.00, 5, 1, 'g', 500.0000, 2500.0000, 300000.00, '2026-03-07 10:19:52'),
	(53, 18, 11, 'KCG-003', 'KACANG UKURAN 1/4 KG', 3, 'Normal', 35000.00, 4, 1, 'g', 250.0000, 1000.0000, 140000.00, '2026-03-07 10:19:52'),
	(59, 9, 10, 'KCG-002', 'KACANG UKURAN 1 KG', 2, 'Reseler', 110000.00, 3, 1, 'g', 1000.0000, 3000.0000, 330000.00, '2026-03-09 00:05:20'),
	(60, 9, 9, 'KCG-001', 'KACANG UKURAN 1/2 KG', 2, 'Reseler', 60000.00, 1, 1, 'g', 500.0000, 500.0000, 60000.00, '2026-03-09 00:05:20');

-- Dumping structure for table pos_system_test.supplier_payments
CREATE TABLE IF NOT EXISTS `supplier_payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` int unsigned NOT NULL,
  `supplier_id` int unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_id` (`purchase_id`),
  KEY `idx_supplier_id` (`supplier_id`),
  CONSTRAINT `fk_supplier_payments_order` FOREIGN KEY (`purchase_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_payments_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `master_suppliers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table pos_system_test.supplier_payments: ~0 rows (approximately)
INSERT INTO `supplier_payments` (`id`, `purchase_id`, `supplier_id`, `payment_date`, `amount`, `payment_method`, `notes`, `created_at`) VALUES
	(1, 3, 1, '2026-03-08', 410000.00, NULL, 'tf bca', '2026-03-07 23:07:45');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
