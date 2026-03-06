-- POS Backup SQL
-- Generated at: 2026-03-06 10:09:00

SET FOREIGN_KEY_CHECKS=0;

-- Table: `app_settings`
DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `app_settings` (`id`,`setting_key`,`setting_value`,`created_at`,`updated_at`) VALUES
(1,'receipt_store_name','Haawaa.Store','2026-03-06 14:32:56','2026-03-06 16:59:21'),
(2,'receipt_store_address','Kacang Si Made - Kacang Asli Bali','2026-03-06 14:32:56','2026-03-06 16:59:21'),
(3,'receipt_store_phone','','2026-03-06 14:32:56','2026-03-06 16:59:21'),
(4,'receipt_bank_account','BCA 123456/ADILAH','2026-03-06 14:32:56','2026-03-06 16:59:21'),
(5,'receipt_logo_url','assets/uploads/logo_20260306_095823_5064.png','2026-03-06 14:32:56','2026-03-06 16:59:21'),
(6,'receipt_header_text','','2026-03-06 14:32:56','2026-03-06 16:59:21'),
(7,'receipt_footer_text','Terimakasih Sudah Berbelanja, Ditunggu Orderan Selanjutnya','2026-03-06 14:32:56','2026-03-06 16:59:21'),
(8,'receipt_show_logo','1','2026-03-06 14:32:56','2026-03-06 16:59:21'),
(9,'receipt_show_store_info','1','2026-03-06 14:32:56','2026-03-06 16:59:21'),
(10,'receipt_show_item_code','0','2026-03-06 14:32:56','2026-03-06 16:59:21'),
(11,'receipt_paper_width_mm','80','2026-03-06 14:32:56','2026-03-06 16:59:21'),
(12,'receipt_font_size_px','12','2026-03-06 14:32:56','2026-03-06 16:59:21'),
(13,'receipt_header_align','center','2026-03-06 14:32:56','2026-03-06 16:59:21');

-- Table: `master_variasi`
DROP TABLE IF EXISTS `master_variasi`;
CREATE TABLE `master_variasi` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nama_variansi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `warna` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '#4F46E5',
  `is_aktif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `master_variasi` (`id`,`nama_variansi`,`deskripsi`,`warna`,`is_aktif`,`created_at`) VALUES
(2,'Reseler','','#EF4444',1,'2026-03-06 10:50:26'),
(3,'Normal','','#10B981',1,'2026-03-06 10:51:12'),
(4,'Reseler Spesial','Untuk daerah Sukabumi','#4F46E5',1,'2026-03-06 10:51:52');

-- Table: `price_variants`
DROP TABLE IF EXISTS `price_variants`;
CREATE TABLE `price_variants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nama_varian` varchar(100) NOT NULL,
  `deskripsi` varchar(255) DEFAULT '',
  `warna` varchar(20) DEFAULT '#4F46E5',
  `is_aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `price_variants` (`id`,`nama_varian`,`deskripsi`,`warna`,`is_aktif`,`created_at`) VALUES
(1,'Normal','Harga jual standar','#10B981',1,'2026-03-06 09:17:12'),
(2,'Reseller','Harga khusus reseller (qty banyak)','#4F46E5',1,'2026-03-06 09:17:12'),
(3,'Promo','Harga diskon promo terbatas','#EF4444',1,'2026-03-06 09:17:12'),
(4,'Grosir','Harga untuk pembelian grosir','#F59E0B',1,'2026-03-06 09:17:12');

-- Table: `print_templates`
DROP TABLE IF EXISTS `print_templates`;
CREATE TABLE `print_templates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `template_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `format_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'html',
  `template_content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_type` (`template_type`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `print_templates` (`id`,`template_type`,`template_name`,`format_type`,`template_content`,`is_active`,`created_at`,`updated_at`) VALUES
(1,'nota','Template Nota','html','<div style=\"font-family:Arial,sans-serif;font-size:12px;color:#222;max-width:360px;margin:0 auto;\">\r\n  <div style=\"text-align:center;border-bottom:1px dashed #999;padding-bottom:8px;margin-bottom:8px;\">\r\n{{logo_img}}\r\n    <h3 style=\"margin:0;\">{{store_name}}</h3>\r\n    <div>{{store_address}}</div>\r\n    <div>Telp: {{store_phone}}</div>\r\n    <div>Rek: {{bank_account}}</div>\r\n  </div>\r\n\r\n  <table style=\"width:100%;font-size:12px;margin-bottom:8px;\">\r\n    <tr><td>No Nota</td><td style=\"text-align:right;\">{{invoice_no}}</td></tr>\r\n    <tr><td>Tanggal</td><td style=\"text-align:right;\">{{transaction_at}}</td></tr>\r\n    <tr><td>Pelanggan</td><td style=\"text-align:right;\">{{customer_name}}</td></tr>\r\n  </table>\r\n\r\n  <div style=\"border-top:1px dashed #999;border-bottom:1px dashed #999;padding:6px 0;margin-bottom:8px;\">\r\n    {{items_rows}}\r\n  </div>\r\n\r\n  <table style=\"width:100%;font-size:12px;\">\r\n    <tr><td>Total</td><td style=\"text-align:right;\">{{total}}</td></tr>\r\n    <tr><td>Bayar</td><td style=\"text-align:right;\">{{paid_amount}}</td></tr>\r\n    <tr><td>Kembali</td><td style=\"text-align:right;\">{{change_amount}}</td></tr>\r\n    <tr><td>Status</td><td style=\"text-align:right;\">{{status}}</td></tr>\r\n  </table>\r\n\r\n  <div style=\"text-align:center;margin-top:10px;font-size:11px;color:#666;\">\r\n    {{footer_text}}\r\n  </div>\r\n</div>',1,'2026-03-06 15:44:27','2026-03-06 16:55:30'),
(2,'kwitansi','Template Kwitansi','html','<div style=\"font-family:Arial,sans-serif;font-size:13px;color:#222;max-width:700px;margin:0 auto;border:1px solid #ddd;padding:16px;\">\r\n  <h2 style=\"text-align:center;margin:0 0 14px;\">KWITANSI</h2>\r\n\r\n  <table style=\"width:100%;margin-bottom:12px;\">\r\n    <tr><td style=\"width:140px;\">No Kwitansi</td><td>: {{invoice_no}}</td></tr>\r\n    <tr><td>Tanggal</td><td>: {{transaction_at}}</td></tr>\r\n    <tr><td>Sudah terima dari</td><td>: {{customer_name}}</td></tr>\r\n    <tr><td>Untuk pembayaran</td><td>: Pembelian produk di {{store_name}}</td></tr>\r\n  </table>\r\n\r\n  <div style=\"border:1px dashed #aaa;padding:10px;margin-bottom:12px;\">\r\n    {{items_rows}}\r\n  </div>\r\n\r\n  <table style=\"width:100%;\">\r\n    <tr><td style=\"width:140px;\">Total</td><td>: {{total}}</td></tr>\r\n    <tr><td>Terbilang</td><td>: .......................................................</td></tr>\r\n    <tr><td>Status</td><td>: {{status}}</td></tr>\r\n  </table>\r\n\r\n  <div style=\"display:flex;justify-content:space-between;margin-top:30px;\">\r\n    <div>\r\n      <div>{{store_name}}</div>\r\n      <div>{{store_address}}</div>\r\n      <div>Telp: {{store_phone}}</div>\r\n    </div>\r\n    <div style=\"text-align:center;min-width:180px;\">\r\n      <div>Penerima,</div>\r\n      <div style=\"height:60px;\"></div>\r\n      <div>(________________)</div>\r\n    </div>\r\n  </div>\r\n</div>',1,'2026-03-06 15:44:27','2026-03-06 16:55:30');

-- Table: `product_prices`
DROP TABLE IF EXISTS `product_prices`;
CREATE TABLE `product_prices` (
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `product_prices` (`id`,`product_id`,`variant_id`,`harga`,`created_at`,`updated_at`) VALUES
(16,10,2,'110000.00','2026-03-06 13:22:58','2026-03-06 13:22:58'),
(17,10,3,'120000.00','2026-03-06 13:22:58','2026-03-06 13:22:58'),
(20,9,2,'60000.00','2026-03-06 14:38:11','2026-03-06 14:38:11'),
(21,9,3,'60000.00','2026-03-06 14:38:11','2026-03-06 14:38:11'),
(22,9,4,'50000.00','2026-03-06 14:38:11','2026-03-06 14:38:11'),
(23,11,2,'30000.00','2026-03-06 14:38:36','2026-03-06 14:38:36'),
(24,11,3,'35000.00','2026-03-06 14:38:36','2026-03-06 14:38:36');

-- Table: `products`
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `kode_barang` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_barang` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Lainnya',
  `harga_beli` decimal(15,2) NOT NULL DEFAULT '0.00',
  `harga_jual` decimal(15,2) NOT NULL DEFAULT '0.00',
  `stok` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_barang` (`kode_barang`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `products` (`id`,`kode_barang`,`nama_barang`,`kategori`,`harga_beli`,`harga_jual`,`stok`,`created_at`,`updated_at`) VALUES
(9,'KCG-001','KACANG UKURAN 1/2 KG','Snack','45000.00','50000.00',100,'2026-03-06 13:21:24','2026-03-06 14:38:11'),
(10,'KCG-002','KACANG UKURAN 1 KG','Snack','85000.00','110000.00',100,'2026-03-06 13:22:58','2026-03-06 13:22:58'),
(11,'KCG-003','KACANG UKURAN 1/4 KG','Snack','25000.00','30000.00',100,'2026-03-06 13:23:54','2026-03-06 13:23:54');

-- Table: `sales_transaction_items`
DROP TABLE IF EXISTS `sales_transaction_items`;
CREATE TABLE `sales_transaction_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` int unsigned NOT NULL,
  `product_id` int unsigned DEFAULT NULL,
  `product_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_id` int unsigned DEFAULT NULL,
  `variant_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `qty` int NOT NULL DEFAULT '1',
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_transaction_id` (`transaction_id`),
  CONSTRAINT `fk_sales_items_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sales_transaction_items` (`id`,`transaction_id`,`product_id`,`product_code`,`product_name`,`variant_id`,`variant_name`,`price`,`qty`,`subtotal`,`created_at`) VALUES
(7,4,10,'KCG-002','KACANG UKURAN 1 KG',NULL,NULL,'110000.00',2,'220000.00','2026-03-06 14:42:45'),
(9,6,10,'KCG-002','KACANG UKURAN 1 KG',NULL,NULL,'110000.00',1,'110000.00','2026-03-06 15:45:48');

-- Table: `sales_transactions`
DROP TABLE IF EXISTS `sales_transactions`;
CREATE TABLE `sales_transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_printed` tinyint(1) NOT NULL DEFAULT '0',
  `printed_at` datetime DEFAULT NULL,
  `transaction_at` datetime NOT NULL,
  `customer_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `change_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sales_transactions` (`id`,`invoice_no`,`status`,`is_printed`,`printed_at`,`transaction_at`,`customer_name`,`subtotal`,`total`,`paid_amount`,`change_amount`,`created_at`) VALUES
(4,'DRF-20260306074245-566','pending',1,'2026-03-06 16:55:45','2026-03-06 14:42:45','tes','220000.00','220000.00','0.00','0.00','2026-03-06 14:42:45'),
(6,'DRF-20260306084548-163','pending',1,'2026-03-06 16:48:05','2026-03-06 15:45:48','tes','110000.00','110000.00','0.00','0.00','2026-03-06 15:45:48');

-- Table: `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userid` (`userid`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`,`userid`,`full_name`,`password_hash`,`is_active`,`created_at`,`updated_at`) VALUES
(1,'admin','Administrator','$2y$10$BDLTpNG5/XzVZ0dRptyVQ.oC8REgkDhHCL0d7jDKd40EpGlnOvHIm',1,'2026-03-06 17:06:31','2026-03-06 17:06:31');

SET FOREIGN_KEY_CHECKS=1;
