<?php
require_once 'config/database.php';
require_once 'auth.php';

$pdo = get_db();
ensure_auth_tables($pdo);
require_login();

// Pastikan tabel master supplier ada
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS master_suppliers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        supplier_code VARCHAR(24) NOT NULL UNIQUE,
        supplier_name VARCHAR(120) NOT NULL,
        contact_name VARCHAR(120) NULL,
        phone VARCHAR(40) NULL,
        address TEXT NULL,
        notes TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

// Pastikan tabel purchase_orders ada
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS purchase_orders (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(50) NOT NULL UNIQUE,
        supplier_id INT UNSIGNED NOT NULL,
        invoice_date DATE NOT NULL,
        due_date DATE NULL,
        subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
        total DECIMAL(15,2) NOT NULL DEFAULT 0,
        paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        status ENUM("unpaid","partial","paid") NOT NULL DEFAULT "unpaid",
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_supplier_id (supplier_id),
        CONSTRAINT fk_purchase_supplier
            FOREIGN KEY (supplier_id) REFERENCES master_suppliers(id)
            ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

// Pastikan tabel purchase_order_items ada
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS purchase_order_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT UNSIGNED NOT NULL,
        product_id INT UNSIGNED NULL,
        product_code VARCHAR(60) NULL,
        product_name VARCHAR(200) NOT NULL,
        qty DECIMAL(15,4) NOT NULL DEFAULT 0,
        unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
        subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_purchase_id (purchase_id),
        CONSTRAINT fk_purchase_items_order
            FOREIGN KEY (purchase_id) REFERENCES purchase_orders(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS supplier_payments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT UNSIGNED NOT NULL,
        supplier_id INT UNSIGNED NOT NULL,
        payment_date DATE NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        payment_method VARCHAR(60) NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_purchase_id (purchase_id),
        INDEX idx_supplier_id (supplier_id),
        CONSTRAINT fk_supplier_payments_order
            FOREIGN KEY (purchase_id) REFERENCES purchase_orders(id)
            ON DELETE CASCADE,
        CONSTRAINT fk_supplier_payments_supplier
            FOREIGN KEY (supplier_id) REFERENCES master_suppliers(id)
            ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$suppliers = $pdo->query('SELECT id, supplier_code, supplier_name, is_active FROM master_suppliers WHERE is_active = 1 ORDER BY supplier_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query('SELECT id, kode_barang, nama_barang, harga_beli, supplier_id FROM products WHERE is_purchase_product = 1 ORDER BY nama_barang ASC')->fetchAll(PDO::FETCH_ASSOC);

// pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(10, min(200, (int)($_GET['per_page'] ?? 25)));
$offset = ($page - 1) * $perPage;

$totalCount = (int)$pdo->query('SELECT COUNT(*) FROM purchase_orders')->fetchColumn();
$totalPages = (int)ceil($totalCount / $perPage);

$stmt = $pdo->prepare(
    'SELECT pi.*, s.supplier_name, s.supplier_code,
            (pi.total - pi.paid_amount) AS outstanding
     FROM purchase_orders pi
     INNER JOIN master_suppliers s ON s.id = pi.supplier_id
     ORDER BY pi.id DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$purchaseRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$summary = $pdo->query(
    'SELECT
        COUNT(*) AS total_invoice,
        COALESCE(SUM(total), 0) AS total_belanja,
        COALESCE(SUM(paid_amount), 0) AS total_bayar,
        COALESCE(SUM(total - paid_amount), 0) AS total_hutang
     FROM purchase_orders'
)->fetch(PDO::FETCH_ASSOC) ?: [];

function rupiah_purchase($value): string
{
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembelian Supplier - POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/products.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .purchase-page {
            flex: 1;
            padding: 24px 30px;
            overflow-y: auto;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 12px;
        }

        .card .label {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .card .value {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .panel {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            overflow: hidden;
        }

        .panel-head {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
            background: #f8fafc;
            font-weight: 700;
            font-size: 14px;
        }

        .table-wrap {
            overflow: auto;
        }

        .table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
            font-size: 12px;
            text-align: left;
        }

        .table th {
            background: #fcfcfd;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: var(--text-muted);
            font-size: 11px;
        }

        .table td.num,
        .table th.num {
            text-align: right;
        }

        .badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
            display: inline-block;
        }

        .badge.unpaid {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .badge.partial {
            background: #ffedd5;
            color: #9a3412;
            border: 1px solid #fdba74;
        }

        .badge.paid {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .btn-mini {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #fff;
            color: var(--text-primary);
            font-size: 12px;
            font-weight: 600;
            padding: 6px 10px;
            cursor: pointer;
        }

        .btn-mini.blue {
            border-color: #bfdbfe;
            color: #1d4ed8;
            background: #eff6ff;
        }

        .btn-mini.green {
            border-color: #a7f3d0;
            color: #047857;
            background: #ecfdf5;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, .6);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 14px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-card {
            background: #fff;
            border-radius: 16px;
            width: min(920px, 100%);
            max-height: 92vh;
            overflow: auto;
            border: 1px solid var(--border-color);
        }

        .modal-head {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 14px;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-secondary);
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 9px 10px;
            font-size: 13px;
            background: #fff;
        }

        .purchase-items {
            margin-top: 10px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
        }

        .purchase-items table {
            width: 100%;
            border-collapse: collapse;
        }

        .purchase-items th,
        .purchase-items td {
            padding: 8px;
            border-bottom: 1px solid var(--border-color);
            font-size: 12px;
        }

        .purchase-items th {
            background: #f8fafc;
            text-transform: uppercase;
            font-size: 11px;
            color: var(--text-muted);
        }

        .purchase-items td input,
        .purchase-items td select {
            width: 100%;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 7px;
            font-size: 12px;
        }

        .totals {
            margin-top: 10px;
            display: flex;
            justify-content: flex-end;
            font-size: 14px;
            font-weight: 700;
        }

        .muted {
            color: var(--text-muted);
            font-size: 12px;
        }

        @media (max-width: 1100px) {
            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            .cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="pos-layout">
        <nav class="side-nav">
            <div class="logo">
                <div class="logo-icon"><i data-feather="box"></i></div>
            </div>
            <ul class="nav-links">
                <li><a href="index.php" title="Kasir"><i data-feather="shopping-cart"></i></a></li>
                <li><a href="transactions.php" title="Transaksi"><i data-feather="list"></i></a></li>
                <li><a href="products.php" title="Master Produk"><i data-feather="package"></i></a></li>
                <li class="active"><a href="purchases.php" title="Pembelian"><i data-feather="truck"></i></a></li>
                <li><a href="price_variants.php" title="Varian Harga"><i data-feather="tag"></i></a></li>
                <li><a href="laporan.php" title="Laporan"><i data-feather="bar-chart-2"></i></a></li>
                <li><a href="settings.php" title="Pengaturan"><i data-feather="settings"></i></a></li>
                <li><a href="logout.php" title="Logout"><i data-feather="log-out"></i></a></li>
            </ul>
        </nav>

        <main class="purchase-page">
            <header class="section-header" style="margin-bottom:10px;">
                <div>
                    <h1 style="font-size:26px;margin-bottom:4px;">Pembelian Supplier</h1>
                    <p class="text-muted">Kelola order pembelian (PO), pembayaran, dan hutang supplier otomatis.</p>
                </div>
            </header>

            <div class="cards">
                <div class="card">
                    <div class="label">Total Invoice</div>
                    <div class="value"><?= number_format((float)($summary['total_invoice'] ?? 0), 0, ',', '.') ?></div>
                </div>
                <div class="card">
                    <div class="label">Total Belanja</div>
                    <div class="value"><?= rupiah_purchase($summary['total_belanja'] ?? 0) ?></div>
                </div>
                <div class="card">
                    <div class="label">Total Dibayar</div>
                    <div class="value"><?= rupiah_purchase($summary['total_bayar'] ?? 0) ?></div>
                </div>
                <div class="card">
                    <div class="label">Total Hutang</div>
                    <div class="value"><?= rupiah_purchase($summary['total_hutang'] ?? 0) ?></div>
                </div>
            </div>

            <div class="toolbar">
                <button class="btn-primary" onclick="openCreatePurchase()"><i data-feather="plus" style="width:14px;height:14px;"></i> Buat Purchase Order</button>
            </div>

            <section class="panel">
                <div class="panel-head">Daftar Purchase Order</div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Invoice / PO</th>
                                <th>Tanggal</th>
                                <th>Jatuh Tempo</th>
                                <th>Supplier</th>
                                <th class="num">Total</th>
                                <th class="num">Dibayar</th>
                                <th class="num">Hutang</th>
                                <th>Status</th>
                                <th>Bukti</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($purchaseRows)): ?>
                                <tr>
                                    <td colspan="10" style="text-align:center;color:var(--text-muted);padding:16px;">Belum ada data pembelian.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($purchaseRows as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$r['invoice_no']) ?></td>
                                        <td><?= htmlspecialchars((string)$r['invoice_date']) ?></td>
                                        <td><?= htmlspecialchars((string)($r['due_date'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)$r['supplier_name']) ?></td>
                                        <td class="num"><?= rupiah_purchase($r['total']) ?></td>
                                        <td class="num"><?= rupiah_purchase($r['paid_amount']) ?></td>
                                        <td class="num"><?= rupiah_purchase($r['outstanding']) ?></td>
                                        <td><span class="badge <?= htmlspecialchars((string)$r['status']) ?>"><?= strtoupper((string)$r['status']) ?></span></td>
                                        <td>
                                            <?php if (!empty($r['proof_file'])): ?>
                                                <a href="<?= htmlspecialchars((string)$r['proof_file']) ?>" target="_blank">Lihat</a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td style="display:flex;gap:6px;flex-wrap:wrap;">
                                            <button class="btn-mini blue" onclick="openDetailPurchase(<?= (int)$r['id'] ?>)">Detail</button>
                                            <button class="btn-mini" onclick="openEditPurchase(<?= (int)$r['id'] ?>)">Edit</button>
                                            <button class="btn-mini red" onclick="confirmDeletePurchase(<?= (int)$r['id'] ?>, '<?= htmlspecialchars((string)$r['invoice_no'], ENT_QUOTES) ?>')">Delete</button>
                                            <button class="btn-mini" onclick="openUploadProof(<?= (int)$r['id'] ?>)">Upload</button>
                                            <?php if ((float)$r['outstanding'] > 0): ?>
                                                <button class="btn-mini green" onclick="openPaymentModal(<?= (int)$r['id'] ?>, '<?= htmlspecialchars((string)$r['invoice_no'], ENT_QUOTES) ?>', <?= (float)$r['outstanding'] ?>)">Bayar</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                    <div style="padding:12px;display:flex;justify-content:center;">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page-1 ?>&per_page=<?= $perPage ?>">&laquo; Prev</a>
                            <?php else: ?>
                                <span style="opacity:.5">&laquo; Prev</span>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 3);
                            $end = min($totalPages, $page + 3);
                            for ($p = $start; $p <= $end; $p++):
                            ?>
                                <?php if ($p === $page): ?>
                                    <span class="active"><?= $p ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $p ?>&per_page=<?= $perPage ?>"><?= $p ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page+1 ?>&per_page=<?= $perPage ?>">Next &raquo;</a>
                            <?php else: ?>
                                <span style="opacity:.5">Next &raquo;</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <div class="modal-overlay" id="purchaseModal">
        <div class="modal-card">
            <div class="modal-head">
                <strong>Buat Purchase Order</strong>
                <button class="btn-mini" onclick="closePurchaseModal()">Tutup</button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="field">
                        <label>Supplier</label>
                        <select id="poSupplier">
                            <option value="">-- Pilih Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars((string)$s['supplier_name']) ?><?= trim((string)$s['supplier_code']) !== '' ? ' (' . htmlspecialchars((string)$s['supplier_code']) . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Tanggal Invoice / PO</label>
                        <input type="date" id="poDate">
                    </div>
                    <div class="field">
                        <label>Jatuh Tempo</label>
                        <input type="date" id="poDueDate">
                    </div>
                </div>
                <div class="field">
                    <label>Catatan</label>
                    <textarea id="poNotes" rows="2"></textarea>
                </div>
                <div class="purchase-items">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:38%;">Produk</th>
                                <th style="width:16%;">Qty</th>
                                <th style="width:20%;">Harga Beli / Unit</th>
                                <th style="width:20%;">Subtotal</th>
                                <th style="width:6%;"></th>
                            </tr>
                        </thead>
                        <tbody id="poItemsBody"></tbody>
                    </table>
                </div>
                <div style="margin-top:10px;">
                    <button class="btn-mini blue" onclick="addPoItem()">+ Tambah Item</button>
                </div>
                <div class="totals">Total: <span id="poTotal" style="margin-left:8px;"><?= rupiah_purchase(0) ?></span></div>
                <div style="margin-top:12px;display:flex;justify-content:flex-end;">
                    <button class="btn-primary" id="btnSavePO" onclick="savePurchase()">Simpan PO</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="paymentModal">
        <div class="modal-card" style="width:min(520px,100%);">
            <div class="modal-head">
                <strong>Tambah Pembayaran Supplier</strong>
                <button class="btn-mini" onclick="closePaymentModal()">Tutup</button>
            </div>
            <div class="modal-body">
                <div class="muted" id="paymentInfo"></div>
                <input type="hidden" id="payPurchaseId">
                <div class="row" style="grid-template-columns:1fr 1fr;">
                    <div class="field">
                        <label>Tanggal Bayar</label>
                        <input type="date" id="payDate">
                    </div>
                    <div class="field">
                        <label>Metode</label>
                        <input type="text" id="payMethod" placeholder="Cash / Transfer / dll">
                    </div>
                </div>
                <div class="field">
                    <label>Nominal</label>
                    <input type="number" id="payAmount" min="0" step="0.01">
                </div>
                <div class="field">
                    <label>Catatan</label>
                    <textarea id="payNotes" rows="2"></textarea>
                </div>
                <div style="margin-top:10px;display:flex;justify-content:flex-end;">
                    <button class="btn-primary" onclick="savePayment()">Simpan Pembayaran</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="detailModal">
        <div class="modal-card">
            <div class="modal-head">
                <strong>Detail Purchase Order</strong>
                <button class="btn-mini" onclick="closeDetailModal()">Tutup</button>
            </div>
            <div class="modal-body" id="detailBody"></div>
        </div>
    </div>

    <script>
    const products = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;

    // currently editing purchase id (0 when creating new)
    let editingPurchaseId = 0;

    const purchaseModal = document.getElementById('purchaseModal');
        const paymentModal = document.getElementById('paymentModal');
        const detailModal = document.getElementById('detailModal');
        const poItemsBody = document.getElementById('poItemsBody');
        const poTotal = document.getElementById('poTotal');

        function formatRupiah(n) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(Number(n || 0));
        }

        function todayStr() {
            const d = new Date();
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }

        function openCreatePurchase() {
            // ensure create (not editing)
            editingPurchaseId = 0;
            document.getElementById('poSupplier').value = '';
            document.getElementById('poDate').value = todayStr();
            document.getElementById('poDueDate').value = '';
            document.getElementById('poNotes').value = '';
            poItemsBody.innerHTML = '';
            addPoItem();
            recalcPoTotal();
            purchaseModal.classList.add('active');
        }

        function closePurchaseModal() {
            purchaseModal.classList.remove('active');
            // reset editing state
            clearEditingState();
        }

        function productOptionsHtml() {
            const opts = ['<option value="">-- Pilih Produk --</option>'];
            products.forEach((p) => {
                opts.push(`<option value="${p.id}" data-code="${String(p.kode_barang || '').replace(/"/g, '&quot;')}" data-name="${String(p.nama_barang || '').replace(/"/g, '&quot;')}" data-cost="${Number(p.harga_beli || 0)}">${p.nama_barang} (${p.kode_barang})</option>`);
            });
            return opts.join('');
        }

        function addPoItem() {
            const tr = document.createElement('tr');
            tr.innerHTML = `
        <td><select class="po-product">${productOptionsHtml()}</select></td>
        <td><input type="number" class="po-qty" min="0.0001" step="0.0001" value="1"></td>
        <td><input type="number" class="po-cost" min="0" step="0.01" value="0"></td>
        <td class="po-subtotal">${formatRupiah(0)}</td>
        <td><button type="button" class="btn-mini" onclick="removePoItem(this)">x</button></td>
    `;
            poItemsBody.appendChild(tr);
            const productSelect = tr.querySelector('.po-product');
            const qtyInput = tr.querySelector('.po-qty');
            const costInput = tr.querySelector('.po-cost');
            productSelect.addEventListener('change', () => {
                const opt = productSelect.selectedOptions[0];
                const cost = Number(opt?.dataset?.cost || 0);
                costInput.value = String(cost);
                recalcPoRow(tr);
            });
            qtyInput.addEventListener('input', () => recalcPoRow(tr));
            costInput.addEventListener('input', () => recalcPoRow(tr));
            recalcPoRow(tr);
        }

        function removePoItem(btn) {
            const tr = btn.closest('tr');
            if (tr) tr.remove();
            recalcPoTotal();
        }

        function recalcPoRow(tr) {
            const qty = Number(tr.querySelector('.po-qty').value || 0);
            const cost = Number(tr.querySelector('.po-cost').value || 0);
            const subtotal = qty * cost;
            tr.querySelector('.po-subtotal').innerText = formatRupiah(subtotal);
            recalcPoTotal();
        }

        function recalcPoTotal() {
            let total = 0;
            poItemsBody.querySelectorAll('tr').forEach((tr) => {
                const qty = Number(tr.querySelector('.po-qty').value || 0);
                const cost = Number(tr.querySelector('.po-cost').value || 0);
                total += qty * cost;
            });
            poTotal.innerText = formatRupiah(total);
        }

        async function savePurchase() {
            const supplierId = Number(document.getElementById('poSupplier').value || 0);
            if (supplierId <= 0) {
                alert('Pilih supplier terlebih dahulu.');
                return;
            }
            const items = [];
            poItemsBody.querySelectorAll('tr').forEach((tr) => {
                const select = tr.querySelector('.po-product');
                const opt = select.selectedOptions[0];
                const pid = Number(select.value || 0);
                const qty = Number(tr.querySelector('.po-qty').value || 0);
                const cost = Number(tr.querySelector('.po-cost').value || 0);
                if (pid > 0 && qty > 0) {
                    items.push({
                        product_id: pid,
                        product_code: opt?.dataset?.code || '',
                        product_name: opt?.dataset?.name || '',
                        qty: qty,
                        unit_cost: cost,
                    });
                }
            });
            if (!items.length) {
                alert('Tambahkan minimal 1 item produk valid.');
                return;
            }
            const btn = document.getElementById('btnSavePO');
            btn.disabled = true;
            btn.innerText = 'Menyimpan...';
            try {
                const payload = {
                    action: editingPurchaseId > 0 ? 'update_purchase' : 'create_purchase',
                    supplier_id: supplierId,
                    invoice_date: document.getElementById('poDate').value || todayStr(),
                    due_date: document.getElementById('poDueDate').value || '',
                    notes: document.getElementById('poNotes').value || '',
                    items: items,
                };
                if (editingPurchaseId > 0) payload.purchase_id = editingPurchaseId;

                const res = await fetch('purchase_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || (editingPurchaseId > 0 ? 'Gagal memperbarui PO' : 'Gagal membuat PO'));
                window.location.reload();
            } catch (err) {
                alert(err.message || 'Gagal memproses');
            } finally {
                btn.disabled = false;
                btn.innerText = 'Simpan PO';
            }
        }

        // helper: populate po items into modal
        function setPoItems(items) {
            poItemsBody.innerHTML = '';
            if (!Array.isArray(items) || items.length === 0) {
                addPoItem();
                return;
            }
            items.forEach((it) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
        <td><select class="po-product">${productOptionsHtml()}</select></td>
        <td><input type="number" class="po-qty" min="0.0001" step="0.0001" value="${Number(it.qty||0)}"></td>
        <td><input type="number" class="po-cost" min="0" step="0.01" value="${Number(it.unit_cost||0)}"></td>
        <td class="po-subtotal">${formatRupiah((Number(it.qty||0) * Number(it.unit_cost||0)))}</td>
        <td><button type="button" class="btn-mini" onclick="removePoItem(this)">x</button></td>
    `;
                poItemsBody.appendChild(tr);
                const productSelect = tr.querySelector('.po-product');
                const qtyInput = tr.querySelector('.po-qty');
                const costInput = tr.querySelector('.po-cost');

                // set selected product if product_id present
                if (it.product_id) {
                    productSelect.value = String(it.product_id);
                } else if (it.product_code) {
                    // try match by code
                    const opt = Array.from(productSelect.options).find(o => o.dataset && o.dataset.code === String(it.product_code));
                    if (opt) productSelect.value = opt.value;
                }

                productSelect.addEventListener('change', () => {
                    const opt = productSelect.selectedOptions[0];
                    const cost = Number(opt?.dataset?.cost || 0);
                    if (!isFinite(Number(cost)) || Number(tr.querySelector('.po-cost').value) === 0) {
                        costInput.value = String(cost);
                    }
                    recalcPoRow(tr);
                });
                qtyInput.addEventListener('input', () => recalcPoRow(tr));
                costInput.addEventListener('input', () => recalcPoRow(tr));
                recalcPoRow(tr);
            });
        }

        function clearEditingState() {
            editingPurchaseId = 0;
            document.getElementById('poSupplier').value = '';
            document.getElementById('poDate').value = '';
            document.getElementById('poDueDate').value = '';
            document.getElementById('poNotes').value = '';
            poItemsBody.innerHTML = '';
            recalcPoTotal();
        }

        function openPaymentModal(purchaseId, invoiceNo, outstanding) {
            document.getElementById('payPurchaseId').value = String(purchaseId);
            document.getElementById('payDate').value = todayStr();
            document.getElementById('payMethod').value = '';
            document.getElementById('payNotes').value = '';
            document.getElementById('payAmount').value = String(Number(outstanding || 0));
            document.getElementById('paymentInfo').innerText = `PO ${invoiceNo} | Sisa hutang: ${formatRupiah(outstanding)}`;
            paymentModal.classList.add('active');
        }

        function closePaymentModal() {
            paymentModal.classList.remove('active');
        }

        async function savePayment() {
            const purchaseId = Number(document.getElementById('payPurchaseId').value || 0);
            const amount = Number(document.getElementById('payAmount').value || 0);
            if (purchaseId <= 0 || amount <= 0) {
                alert('Nominal pembayaran tidak valid.');
                return;
            }

            try {
                const res = await fetch('purchase_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'add_payment',
                        purchase_id: purchaseId,
                        amount: amount,
                        payment_date: document.getElementById('payDate').value || todayStr(),
                        payment_method: document.getElementById('payMethod').value || '',
                        notes: document.getElementById('payNotes').value || '',
                    }),
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Gagal menyimpan pembayaran');
                window.location.reload();
            } catch (err) {
                alert(err.message || 'Gagal memproses');
            }
        }

        async function openDetailPurchase(id) {
            try {
                const res = await fetch(`purchase_actions.php?action=detail&id=${id}`);
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Gagal memuat detail');
                const p = data.purchase || {};
                const items = data.items || [];
                const pays = data.payments || [];
                const itemsHtml = items.map((it) => `
            <tr>
                <td>${it.product_name || '-'}</td>
                <td style="text-align:right;">${Number(it.qty || 0).toLocaleString('id-ID')}</td>
                <td style="text-align:right;">${formatRupiah(it.unit_cost || 0)}</td>
                <td style="text-align:right;">${formatRupiah(it.subtotal || 0)}</td>
            </tr>
        `).join('');
                const payHtml = pays.length ? pays.map((py) => `
            <tr>
                <td>${py.payment_date || '-'}</td>
                <td>${py.payment_method || '-'}</td>
                <td style="text-align:right;">${formatRupiah(py.amount || 0)}</td>
                <td>${py.notes || '-'}</td>
            </tr>
        `).join('') : '<tr><td colspan="4" style="text-align:center;color:#6b7280;">Belum ada pembayaran.</td></tr>';

                document.getElementById('detailBody').innerHTML = `
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;margin-bottom:10px;">
                <div><strong>Invoice / PO</strong><div>${p.invoice_no || '-'}</div></div>
                <div><strong>Supplier</strong><div>${p.supplier_name || '-'}</div></div>
                <div><strong>Tanggal</strong><div>${p.invoice_date || '-'}</div></div>
                <div><strong>Jatuh Tempo</strong><div>${p.due_date || '-'}</div></div>
                <div><strong>Status</strong><div>${String(p.status || '').toUpperCase()}</div></div>
                <div><strong>Sisa Hutang</strong><div>${formatRupiah((Number(p.total||0)-Number(p.paid_amount||0)))}</div></div>
            </div>
            <div class="panel" style="margin-bottom:10px;">
                <div class="panel-head">Item Purchase Order</div>
                <div class="table-wrap">
                    <table class="table" style="min-width:700px;">
                        <thead><tr><th>Produk</th><th class="num">Qty</th><th class="num">Harga Unit</th><th class="num">Subtotal</th></tr></thead>
                        <tbody>${itemsHtml}</tbody>
                    </table>
                </div>
            </div>
            <div class="panel">
                <div class="panel-head">Riwayat Pembayaran</div>
                <div class="table-wrap">
                    <table class="table" style="min-width:700px;">
                        <thead><tr><th>Tanggal</th><th>Metode</th><th class="num">Nominal</th><th>Catatan</th></tr></thead>
                        <tbody>${payHtml}</tbody>
                    </table>
                </div>
            </div>
        `;
                detailModal.classList.add('active');
            } catch (err) {
                alert(err.message || 'Gagal memuat detail');
            }
        }

        function closeDetailModal() {
            detailModal.classList.remove('active');
        }

        // Open edit modal and prefill with purchase data
        async function openEditPurchase(id) {
            try {
                const res = await fetch(`purchase_actions.php?action=detail&id=${id}`);
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Gagal memuat data PO');
                const p = data.purchase || {};
                const items = data.items || [];

                editingPurchaseId = Number(p.id || 0);
                document.getElementById('poSupplier').value = String(p.supplier_id || '');
                document.getElementById('poDate').value = p.invoice_date || todayStr();
                document.getElementById('poDueDate').value = p.due_date || '';
                document.getElementById('poNotes').value = p.notes || '';
                setPoItems(items);
                recalcPoTotal();
                purchaseModal.classList.add('active');
            } catch (err) {
                alert(err.message || 'Gagal memuat data untuk edit');
            }
        }

        // Confirm and delete purchase
        function confirmDeletePurchase(id, invoiceNo) {
            if (!confirm(`Hapus PO ${String(invoiceNo || id)}? Tindakan ini akan mengurangi stok produk yang ditambahkan oleh PO ini.`)) return;
            deletePurchase(id);
        }

        async function deletePurchase(id) {
            try {
                const res = await fetch('purchase_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_purchase', purchase_id: Number(id) }),
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Gagal menghapus PO');
                window.location.reload();
            } catch (err) {
                alert(err.message || 'Gagal memproses penghapusan');
            }
        }

        document.addEventListener('click', (e) => {
            if (e.target === purchaseModal) closePurchaseModal();
            if (e.target === paymentModal) closePaymentModal();
            if (e.target === detailModal) closeDetailModal();
        });

        // Upload proof of purchase handler: create hidden file input and post to server
        function openUploadProof(purchaseId) {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.png,.jpg,.jpeg,.pdf';
            input.addEventListener('change', async () => {
                if (!input.files || !input.files.length) return;
                const file = input.files[0];
                const fd = new FormData();
                fd.append('action', 'upload_proof');
                fd.append('purchase_id', String(purchaseId));
                fd.append('proof', file);
                try {
                    const res = await fetch('purchase_actions.php', {
                        method: 'POST',
                        body: fd,
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) throw new Error(data.message || 'Gagal mengunggah file');
                    window.location.reload();
                } catch (err) {
                    alert(err.message || 'Gagal mengunggah bukti');
                }
            });
            // trigger file chooser
            input.click();
        }

        feather.replace();
    </script>
    <style>
        .pagination { display:flex; gap:6px; align-items:center; margin-top:12px; }
        .pagination a, .pagination span { padding:6px 10px; border:1px solid var(--border-color); border-radius:6px; background:#fff; text-decoration:none; color:inherit; }
        .pagination .active { background:#111827; color:#fff; }
    </style>
</body>

</html>