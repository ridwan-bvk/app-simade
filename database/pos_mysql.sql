-- =============================================================
--  POS System - Script SQL untuk MySQL (Laragon / WAMP / etc.)
--  Jalankan file ini di phpMyAdmin atau MySQL CLI sebelum
--  membuka aplikasi di browser.
-- =============================================================

-- 1. Buat database (kalau belum ada)
CREATE DATABASE IF NOT EXISTS pos_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE pos_system;

-- 2. Buat tabel produk
CREATE TABLE IF NOT EXISTS products (
    id           INT          UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_barang  VARCHAR(50)  NOT NULL UNIQUE,
    nama_barang  VARCHAR(200) NOT NULL,
    kategori     VARCHAR(100) NOT NULL DEFAULT 'Lainnya',
    harga_beli   DECIMAL(15,2) NOT NULL DEFAULT 0,
    harga_jual   DECIMAL(15,2) NOT NULL DEFAULT 0,
    stok         INT          NOT NULL DEFAULT 0,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Data contoh (opsional - bisa dihapus)
INSERT INTO products (kode_barang, nama_barang, kategori, harga_beli, harga_jual, stok)
VALUES
    ('BRG-001', 'Indomie Goreng Spesial', 'Makanan',  2500,  4000, 50),
    ('BRG-002', 'Aqua Botol 600ml',       'Minuman',  2000,  3500, 100),
    ('BRG-003', 'Chiki Popcorn',          'Snack',    3000,  5000, 75),
    ('BRG-004', 'Teh Botol Sosro 350ml',  'Minuman',  4000,  6000, 60),
    ('BRG-005', 'Roti Tawar Sari Roti',   'Makanan',  8000, 12000, 30)
ON DUPLICATE KEY UPDATE id = id; -- Hindari error jika dijalankan ulang
