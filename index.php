<?php
require_once 'config/database.php';
require_once 'auth.php';

$pdo = get_db();
ensure_auth_tables($pdo);
require_login();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS app_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(120) NOT NULL UNIQUE,
        setting_value LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$productsStmt = $pdo->query('SELECT id, kode_barang, nama_barang, kategori, harga_jual, stok FROM products WHERE is_transaction_product = 1 ORDER BY nama_barang ASC');
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

$variantsStmt = $pdo->query('SELECT id, nama_variansi, warna FROM master_variasi WHERE is_aktif = 1 ORDER BY id ASC');
$variants = $variantsStmt->fetchAll(PDO::FETCH_ASSOC);

$pricesStmt = $pdo->query(
    'SELECT pp.product_id, pp.variant_id, pp.harga, mv.nama_variansi, mv.warna
     FROM product_prices pp
     INNER JOIN master_variasi mv ON mv.id = pp.variant_id
     WHERE mv.is_aktif = 1'
);
$priceRows = $pricesStmt->fetchAll(PDO::FETCH_ASSOC);

$variantMap = [];
foreach ($variants as $variant) {
    $variantMap[(int)$variant['id']] = [
        'id' => (int)$variant['id'],
        'name' => $variant['nama_variansi'],
        'color' => $variant['warna'] ?: '#4F46E5',
    ];
}

$productVariantPrices = [];
foreach ($priceRows as $row) {
    $pid = (int)$row['product_id'];
    $vid = (int)$row['variant_id'];
    if (!isset($productVariantPrices[$pid])) {
        $productVariantPrices[$pid] = [];
    }
    $productVariantPrices[$pid][$vid] = [
        'id' => $vid,
        'price' => (float)$row['harga'],
        'name' => $row['nama_variansi'],
        'color' => $row['warna'] ?: '#4F46E5',
    ];
}

$productsPayload = [];
foreach ($products as $product) {
    $pid = (int)$product['id'];
    $productsPayload[] = [
        'id' => $pid,
        'code' => $product['kode_barang'],
        'name' => $product['nama_barang'],
        'category' => $product['kategori'],
        'price' => (float)$product['harga_jual'],
        'stock' => (int)$product['stok'],
        'image' => '📦',
        'variant_prices' => $productVariantPrices[$pid] ?? [],
    ];
}

$receiptTemplateDefaults = [
    'store_name' => 'Toko POS Kita',
    'store_address' => 'Jl. Kenangan No. 123',
    'store_phone' => '',
    'bank_account' => '',
    'logo_url' => '',
    'header_text' => '',
    'footer_text' => 'Terima kasih sudah berbelanja',
    'show_logo' => '0',
    'show_store_info' => '1',
    'show_item_code' => '0',
    'paper_width_mm' => '58',
    'font_size_px' => '12',
    'header_align' => 'center',
];
$receiptTemplate = $receiptTemplateDefaults;
$settingsRows = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'receipt_%'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($settingsRows as $row) {
    $key = (string)$row['setting_key'];
    $value = (string)$row['setting_value'];
    if ($key === 'receipt_store_name') $receiptTemplate['store_name'] = $value;
    if ($key === 'receipt_store_address') $receiptTemplate['store_address'] = $value;
    if ($key === 'receipt_store_phone') $receiptTemplate['store_phone'] = $value;
    if ($key === 'receipt_bank_account') $receiptTemplate['bank_account'] = $value;
    if ($key === 'receipt_logo_url') $receiptTemplate['logo_url'] = $value;
    if ($key === 'receipt_header_text') $receiptTemplate['header_text'] = $value;
    if ($key === 'receipt_footer_text') $receiptTemplate['footer_text'] = $value;
    if ($key === 'receipt_show_logo') $receiptTemplate['show_logo'] = $value;
    if ($key === 'receipt_show_store_info') $receiptTemplate['show_store_info'] = $value;
    if ($key === 'receipt_show_item_code') $receiptTemplate['show_item_code'] = $value;
    if ($key === 'receipt_paper_width_mm') $receiptTemplate['paper_width_mm'] = $value;
    if ($key === 'receipt_font_size_px') $receiptTemplate['font_size_px'] = $value;
    if ($key === 'receipt_header_align') $receiptTemplate['header_align'] = $value;
}

$printTemplates = [
    'nota' => '',
    'kwitansi' => '',
];
$templateRows = $pdo->query('SELECT template_type, template_content FROM print_templates WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC);
foreach ($templateRows as $templateRow) {
    $type = (string)$templateRow['template_type'];
    if (array_key_exists($type, $printTemplates)) {
        $printTemplates[$type] = (string)$templateRow['template_content'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi SiMade - Kasir</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        /* ── Layout Override: kasir lebih kecil, cart lebih lebar ── */
        .products-section {
            flex: 4 !important;
            order: 2;
        }

        .cart-section {
            flex: 6 !important;
            min-width: 520px !important;
            max-width: 680px !important;
            order: 1;
            border-left: none !important;
            border-right: 1px solid var(--border-color) !important;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.02) !important;
        }

        /* ── Customer Input ── */
        .cart-customer {
            padding: 12px 24px;
            border-bottom: 1px solid var(--border-color);
            background: #fafbff;
        }

        .customer-input-wrap {
            position: relative;
        }

        .customer-input-wrap .ci-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            width: 16px;
            height: 16px;
        }

        .customer-input-wrap input {
            width: 100%;
            padding: 10px 12px 10px 36px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 14px;
            background: white;
            outline: none;
            transition: all 0.2s;
        }

        .customer-input-wrap input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }



        /* ── Cart Header compact ── */
        .cart-header {
            padding: 16px 24px !important;
        }

        /* ── Cart items padding ── */
        .cart-items {
            padding: 16px 24px !important;
        }

        /* ── Summary compact ── */
        .cart-summary {
            padding: 16px 24px !important;
        }

        .summary-row {
            margin-bottom: 8px !important;
        }

        .total-row {
            margin-bottom: 14px !important;
        }

        .mt-3 {
            margin-top: 10px !important;
        }

        /* ── Payment Amount compact ── */
        .payment-input-group {
            margin-bottom: 10px !important;
        }

        .input-lg {
            padding: 11px 14px !important;
            font-size: 15px !important;
        }

        /* ── Checkout Buttons Row ── */
        .checkout-row {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }

        .btn-checkout {
            flex: 1;
            padding: 14px !important;
            margin-top: 0 !important;
        }

        .btn-save-draft {
            padding: 14px 14px;
            border: 2px solid #fb923c;
            border-radius: var(--border-radius-md);
            background: #fff7ed;
            color: #c2410c;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-save-draft:hover:not(:disabled) {
            background: #ffedd5;
        }

        .btn-save-draft:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .btn-print-receipt {
            padding: 14px 16px;
            background: white;
            border: 2px solid var(--emerald);
            color: var(--emerald);
            border-radius: var(--border-radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-print-receipt:hover:not(:disabled) {
            background: var(--emerald);
            color: white;
        }

        .btn-print-receipt:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* ── Receipt Preview Modal ── */
        .receipt-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.65);
            backdrop-filter: blur(6px);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(.4, 0, .2, 1);
        }

        .receipt-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .receipt-modal {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            width: 440px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.92) translateY(20px);
            transition: all 0.3s cubic-bezier(.4, 0, .2, 1);
        }

        .receipt-modal-overlay.active .receipt-modal {
            transform: scale(1) translateY(0);
        }

        .receipt-modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .receipt-modal-header h3 {
            font-size: 18px;
            font-weight: 700;
        }

        .receipt-body {
            padding: 24px;
        }

        .receipt-thermal {
            width: 300px;
            margin: 0 auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            background: #fffef7;
            border: 1px dashed #ccc;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .receipt-modal-actions {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            background: var(--bg-main);
            border-radius: 0 0 20px 20px;
        }

        /* ── Products search compact ── */
        .section-header {
            margin-bottom: 16px !important;
        }

        .search-bar {
            width: 220px !important;
        }

        .search-bar input {
            padding: 10px 10px 10px 36px !important;
            font-size: 13px !important;
        }

        h1 {
            font-size: 20px !important;
            margin-bottom: 2px !important;
        }

        .products-section {
            padding: 20px 24px !important;
        }

        .categories-wrapper {
            margin-bottom: 16px !important;
        }

        .category-btn {
            padding: 6px 14px !important;
            font-size: 13px !important;
        }

        .header-tools {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .variant-filter {
            min-width: 190px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            background: white;
            padding: 9px 10px;
            font-size: 13px;
            color: var(--text-primary);
            outline: none;
        }

        .variant-filter:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .variant-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            margin-top: 4px;
        }

        .variant-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .cart-item-variant {
            margin-top: 6px;
            font-size: 12px;
        }

        .cart-item-variant select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 12px;
            background: white;
        }

        .variant-warning {
            margin-top: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #b91c1c;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 6px 8px;
        }

        .checkout-validation {
            display: none;
            margin-top: 10px;
            font-size: 12px;
            font-weight: 600;
            color: #b91c1c;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 8px 10px;
        }

        .toast-wrap {
            position: fixed;
            top: 14px;
            right: 14px;
            z-index: 3000;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .toast {
            min-width: 280px;
            max-width: 360px;
            border-radius: 12px;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            background: #fff;
            box-shadow: var(--shadow-md);
            font-size: 13px;
            font-weight: 600;
        }

        .toast.success {
            border-color: #6ee7b7;
            background: #ecfdf5;
            color: #065f46;
        }

        .toast.error {
            border-color: #fecaca;
            background: #fef2f2;
            color: #991b1b;
        }

        .toast.info {
            border-color: #bfdbfe;
            background: #eff6ff;
            color: #1e40af;
        }

        @media (max-width: 1200px) {
            .header-tools {
                width: 100%;
                justify-content: flex-start;
            }

            .products-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 860px) {
            .products-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
            }

            .cart-section {
                min-width: 100% !important;
                max-width: none !important;
            }
        }

        /* ── Product card compact ── */
        .product-card {
            padding: 10px !important;
        }

        .products-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 10px !important;
        }

        .product-image-placeholder {
            margin-bottom: 8px !important;
        }

        .product-title {
            font-size: 13px !important;
        }
    </style>
</head>

<body>
    <div class="pos-layout">
        <!-- Sidebar -->
        <nav class="side-nav">
            <div class="logo">
                <div class="logo-icon"><i data-feather="box"></i></div>
            </div>
            <ul class="nav-links">
                <li class="active"><a href="index.php" title="Kasir"><i data-feather="shopping-cart"></i></a></li>
                <li><a href="transactions.php" title="Transaksi"><i data-feather="list"></i></a></li>
                <li><a href="products.php" title="Master Produk"><i data-feather="package"></i></a></li>
                <li><a href="price_variants.php" title="Varian Harga"><i data-feather="tag"></i></a></li>
                <li><a href="laporan.php" title="Laporan"><i data-feather="bar-chart-2"></i></a></li>
                <li><a href="settings.php" title="Pengaturan"><i data-feather="settings"></i></a></li>
                <li><a href="logout.php" title="Logout"><i data-feather="log-out"></i></a></li>
            </ul>
        </nav>

        <!-- Right: Products (diperkecil) -->
        <main class="products-section">
            <header class="section-header">
                <div>
                    <h1>Aplikasi SiMade</h1>
                    <p class="text-muted" style="font-size:12px;">Pilih produk atau cari kode</p>
                </div>
                <div class="header-tools">
                    <select id="cashierVariantSelect" class="variant-filter" title="Varian harga aktif kasir">
                        <option value="">Harga Normal (Utama)</option>
                    </select>
                    <div class="search-bar">
                        <i data-feather="search" class="search-icon"></i>
                        <input type="text" id="searchInput" placeholder="Cari Produk / Kode (F2)" autocomplete="off">
                        <span class="shortcut-hint">F2</span>
                    </div>
                </div>
            </header>

            <!-- Categories Filter -->
            <div class="categories-wrapper" id="categoriesWrapper"></div>

            <!-- Products Grid -->
            <div class="products-grid" id="productsGrid"></div>
        </main>

        <!-- Left: Cart (diperlebar) -->
        <aside class="cart-section">
            <!-- Cart Header -->
            <header class="cart-header">
                <h2>Keranjang <span style="font-size:11px;color:var(--text-muted);font-weight:600;">F9 transaksi baru</span></h2>
                <div style="display:flex;gap:8px;align-items:center;">
                    <button class="btn-secondary" id="newTransactionBtn" title="Mulai Transaksi Baru" style="padding:8px 10px;font-size:12px;">
                        Baru
                    </button>
                    <button class="btn-clear-cart" id="clearCartBtn" title="Kosongkan Keranjang">
                        <i data-feather="trash-2"></i>
                    </button>
                </div>
            </header>

            <!-- Customer Name -->
            <div class="cart-customer">
                <div class="customer-input-wrap">
                    <i data-feather="user" class="ci-icon"></i>
                    <input type="text" id="customerName" placeholder="Nama Pelanggan (opsional)" autocomplete="off">
                </div>
            </div>
            <!-- Cart Items -->
            <div class="cart-items" id="cartItems">
                <div class="empty-cart-state" id="emptyCartState">
                    <i data-feather="shopping-bag"></i>
                    <p>Keranjang masih kosong</p>
                </div>
            </div>

            <!-- Summary & Checkout -->
            <div class="cart-summary">
                <div class="summary-row">
                    <span class="text-muted">Subtotal</span>
                    <span class="font-medium" id="subtotalAmount">Rp 0</span>
                </div>
                <div class="summary-row">
                    <span class="text-muted">Diskon</span>
                    <span class="font-medium text-emerald">
                        <input type="number" id="discountInput" value="0" min="0" style="width:80px;padding:4px 8px;font-size:14px;border-radius:6px;border:1px solid #ddd;">
                        <select id="discountType" style="padding:4px 8px;font-size:14px;border-radius:6px;border:1px solid #ddd;margin-left:6px;">
                            <option value="nominal">Nominal (Rp)</option>
                            <option value="percent">Persen (%)</option>
                        </select>
                        <span id="discountAmount">- Rp 0</span>
                    </span>
                </div>
                <div class="summary-divider"></div>
                <div class="summary-row total-row">
                    <span>Total</span>
                    <span id="totalAmount">Rp 0</span>
                </div>

                <div class="payment-input-group">
                    <label for="paymentAmount" class="text-muted text-sm">Tunai Dibayar (Rp)</label>
                    <input type="number" id="paymentAmount" class="input-lg" placeholder="Masukan nominal bayar">
                </div>

                <div class="summary-row change-row mt-3">
                    <span class="text-muted">Kembalian</span>
                    <span class="font-bold text-rose" id="changeAmount">Rp 0</span>
                </div>
                <div id="variantValidationNotice" class="checkout-validation"></div>

                <div class="checkout-row">
                    <button class="btn-save-draft" id="saveDraftBtn" disabled>
                        <i data-feather="bookmark" style="width:15px;height:15px;"></i>
                        Simpan Belum Bayar
                        <span style="font-size:11px;padding:3px 6px;border-radius:6px;border:1px solid #fdba74;background:#fff;line-height:1;">Ctrl+S</span>
                    </button>
                    <button class="btn-checkout" id="checkoutBtn" disabled>
                        <div class="checkout-btn-content">
                            <span>Bayar Sekarang</span>
                            <span class="shortcut-hint-dark">Enter ↵</span>
                        </div>
                    </button>
                    <button class="btn-print-receipt" id="printReceiptBtn" disabled onclick="openReceiptModal()">
                        <i data-feather="printer" style="width:16px;height:16px;"></i>
                        Resi
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <!-- Receipt Preview Modal -->
    <div class="receipt-modal-overlay" id="receiptModal">
        <div class="receipt-modal">
            <div class="receipt-modal-header">
                <h3>Preview Struk / Resi</h3>
                <button onclick="closeReceiptModal()" style="background:none;border:none;cursor:pointer;padding:6px;border-radius:8px;color:var(--text-muted);">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="receipt-body">
                <div class="receipt-thermal" id="receiptContent"></div>
            </div>
            <div class="receipt-modal-actions">
                <button class="btn-secondary" onclick="closeReceiptModal()">Tutup</button>
                <button class="btn-primary" onclick="doPrint()" style="display:flex;align-items:center;gap:6px;">
                    <i data-feather="printer" style="width:15px;height:15px;"></i>
                    Cetak Sekarang
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden print area -->
    <div id="printArea" class="hidden"></div>
    <div id="toastWrap" class="toast-wrap"></div>

    <script>
        feather.replace();
        window.__POS_PRODUCTS__ = <?= json_encode($productsPayload, JSON_UNESCAPED_UNICODE) ?>;
        window.__POS_VARIANTS__ = <?= json_encode(array_values($variantMap), JSON_UNESCAPED_UNICODE) ?>;
        window.__RECEIPT_TEMPLATE__ = <?= json_encode($receiptTemplate, JSON_UNESCAPED_UNICODE) ?>;
        window.__PRINT_TEMPLATES__ = <?= json_encode($printTemplates, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="assets/js/app.js"></script>
</body>

</html>