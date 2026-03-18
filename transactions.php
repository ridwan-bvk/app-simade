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
$receiptTemplate = [
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
$rows = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'receipt_%'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
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
    <title>Aplikasi SiMade - Transaksi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/products.css">
    <script src="assets/js/feather-local.js"></script>
    <style>
        .tx-toolbar {
            display: grid;
            grid-template-columns: 170px 170px 180px 1fr auto;
            gap: 10px;
            margin: 14px 0 16px;
        }

        .tx-input,
        .tx-select {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 10px;
            font-size: 13px;
            background: #fff;
        }

        .tx-table-wrap {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .tx-table-scroll {
            max-height: calc(100vh - 270px);
            overflow: auto;
        }

        .tx-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tx-table th,
        .tx-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            text-align: left;
        }

        .tx-col-no {
            width: 56px;
            text-align: center !important;
        }

        .tx-table th {
            background: #f9fafb;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.04em;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 999px;
            display: inline-block;
        }

        .badge.pending {
            background: #ffedd5;
            color: #9a3412;
            border: 1px solid #fdba74;
        }

        .badge.paid {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .btn-link {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 6px 9px;
            background: #fff;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #1f2937;
        }

        .btn-link.btn-detail {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        .btn-link.btn-nota {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #047857;
        }

        .btn-link.btn-kwitansi {
            background: #fff7ed;
            border-color: #fed7aa;
            color: #c2410c;
        }

        .btn-link.btn-continue {
            background: #f5f3ff;
            border-color: #ddd6fe;
            color: #6d28d9;
        }

        .tx-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            background: #fff;
            border-top: 1px solid var(--border-color);
        }

        .btn-danger-lite {
            border-color: #fecaca !important;
            color: #b91c1c !important;
            background: #fff1f2 !important;
        }

        .btn-danger-lite:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .tx-pager-btn {
            border: 1px solid var(--border-color);
            background: #fff;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 12px;
            cursor: pointer;
        }

        .tx-modal {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.6);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 14px;
        }

        .tx-modal.active {
            display: flex;
        }

        .tx-modal-card {
            background: #fff;
            border-radius: 16px;
            width: min(760px, 100%);
            max-height: 92vh;
            overflow: auto;
            border: 1px solid var(--border-color);
        }

        .tx-modal-head {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tx-modal-body {
            padding: 14px 16px;
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

        .cancel-modal {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.55);
            z-index: 1200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .cancel-modal.active {
            display: flex;
        }

        .cancel-modal-card {
            width: min(420px, 100%);
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.16);
            overflow: hidden;
        }

        .cancel-modal-head {
            padding: 16px 18px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cancel-modal-body {
            padding: 18px;
        }

        .cancel-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 14px;
        }

        .cancel-input {
            width: 100%;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 11px 12px;
            font-size: 14px;
            outline: none;
        }

        .cancel-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }

        .cancel-validation {
            display: none;
            margin-top: 10px;
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
            border-radius: 10px;
            padding: 9px 10px;
            font-size: 12px;
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

        @media (max-width: 900px) {
            .tx-toolbar {
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
                <li class="active"><a href="transactions.php" title="Transaksi"><i data-feather="list"></i></a></li>
                <li><a href="products.php" title="Master Produk"><i data-feather="package"></i></a></li>
                <li><a href="price_variants.php" title="Varian Harga"><i data-feather="tag"></i></a></li>
                <li><a href="laporan.php" title="Laporan"><i data-feather="bar-chart-2"></i></a></li>
                <li><a href="settings.php" title="Pengaturan"><i data-feather="settings"></i></a></li>
                <li><a href="logout.php" title="Logout"><i data-feather="log-out"></i></a></li>
            </ul>
        </nav>

        <main class="products-section" style="flex:1; padding: 24px 30px; max-width: 100vw;">
            <header class="section-header" style="margin-bottom: 8px;">
                <div>
                    <h1 style="font-size: 26px; margin-bottom: 4px;">Daftar Transaksi</h1>
                    <p class="text-muted">Menu terpisah untuk mengelola transaksi agar kasir tetap rapi walau data pelanggan banyak.</p>
                </div>
            </header>

            <div class="tx-toolbar">
                <input type="date" id="txStartDate" class="tx-input" title="Tanggal Awal">
                <input type="date" id="txEndDate" class="tx-input" title="Tanggal Akhir">
                <select id="txStatus" class="tx-select">
                    <option value="all">Semua Status</option>
                    <option value="pending">Belum Bayar</option>
                    <option value="paid">Sudah Bayar</option>
                </select>
                <input type="text" id="txSearch" class="tx-input" placeholder="Cari invoice / nama pelanggan">
                <button id="btnSearch" class="btn-primary">Cari</button>
            </div>

            <div class="tx-table-wrap">
                <div class="tx-table-scroll">
                    <table class="tx-table">
                        <thead>
                            <tr>
                                <th class="tx-col-no">No</th>
                                <th>Invoice</th>
                                <th>Pelanggan</th>
                                <th>Waktu</th>
                                <th>Total</th>
                                <th>Diskon</th>
                                <th>Uang Muka</th>
                                <th>Sisa Bayar</th>
                                <th>Metode</th>
                                <th>Status</th>
                                <th>Flag Cetak</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="txTbody">
                            <tr>
                                <td colspan="12" style="text-align:center;color:var(--text-muted);">Memuat data...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="tx-pagination">
                    <div id="txPageInfo" style="font-size:12px;color:var(--text-muted);"></div>
                    <div style="display:flex;gap:8px;">
                        <button class="tx-pager-btn" id="txPrev">Sebelumnya</button>
                        <button class="tx-pager-btn" id="txNext">Berikutnya</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="tx-modal" id="txModal">
        <div class="tx-modal-card">
            <div class="tx-modal-head">
                <strong>Detail Transaksi</strong>
                <button class="btn-link" onclick="closeTxModal()">Tutup</button>
            </div>
            <div class="tx-modal-body" id="txModalBody"></div>
        </div>
    </div>
    <div class="cancel-modal" id="cancelPaidModal">
        <div class="cancel-modal-card">
            <div class="cancel-modal-head">
                <strong>Batal Bayar</strong>
                <button class="btn-link" type="button" onclick="closeCancelPaidModal()">Tutup</button>
            </div>
            <div class="cancel-modal-body">
                <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px;">Masukkan password login untuk mengubah transaksi sudah bayar menjadi belum bayar.</p>
                <input type="password" id="cancelPaidPassword" class="cancel-input" placeholder="Masukkan password login">
                <div id="cancelPaidValidation" class="cancel-validation"></div>
                <div class="cancel-modal-actions">
                    <button class="btn-link" type="button" onclick="closeCancelPaidModal()">Batal</button>
                    <button class="btn-link btn-danger-lite" type="button" id="confirmCancelPaidBtn">Konfirmasi</button>
                </div>
            </div>
        </div>
    </div>
    <div id="printArea" class="hidden"></div>
    <div id="toastWrap" class="toast-wrap"></div>

    <script>
        feather.replace();
        window.__RECEIPT_TEMPLATE__ = <?= json_encode($receiptTemplate, JSON_UNESCAPED_UNICODE) ?>;
        window.__PRINT_TEMPLATES__ = <?= json_encode($printTemplates, JSON_UNESCAPED_UNICODE) ?>;
        const txStartDate = document.getElementById('txStartDate');
        const txEndDate = document.getElementById('txEndDate');
        const txStatus = document.getElementById('txStatus');
        const txSearch = document.getElementById('txSearch');
        const btnSearch = document.getElementById('btnSearch');
        const txTbody = document.getElementById('txTbody');
        const txPageInfo = document.getElementById('txPageInfo');
        const txPrev = document.getElementById('txPrev');
        const txNext = document.getElementById('txNext');
        const txModal = document.getElementById('txModal');
        const txModalBody = document.getElementById('txModalBody');
        const cancelPaidModal = document.getElementById('cancelPaidModal');
        const cancelPaidPassword = document.getElementById('cancelPaidPassword');
        const cancelPaidValidation = document.getElementById('cancelPaidValidation');
        const confirmCancelPaidBtn = document.getElementById('confirmCancelPaidBtn');
        const printArea = document.getElementById('printArea');
        const toastWrap = document.getElementById('toastWrap');
        const receiptTemplate = (window.__RECEIPT_TEMPLATE__ && typeof window.__RECEIPT_TEMPLATE__ === 'object') ? window.__RECEIPT_TEMPLATE__ : {};
        const printTemplates = (window.__PRINT_TEMPLATES__ && typeof window.__PRINT_TEMPLATES__ === 'object') ? window.__PRINT_TEMPLATES__ : {};

        let page = 1;
        let totalPages = 1;
        let searchDebounceTimer = null;
        let activeCancelPaidId = 0;

        function formatRupiah(value) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(Number(value || 0));
        }

        function showToast(message, type = 'info') {
            const div = document.createElement('div');
            div.className = `toast ${type}`;
            div.textContent = message;
            toastWrap.appendChild(div);
            setTimeout(() => {
                div.style.opacity = '0';
                div.style.transform = 'translateY(-4px)';
            }, 2600);
            setTimeout(() => div.remove(), 3200);
        }

        function setCancelValidation(message = '') {
            cancelPaidValidation.textContent = message;
            cancelPaidValidation.style.display = message ? 'block' : 'none';
        }

        function escHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function applyPrintTemplate(templateHtml, placeholders) {
            let output = String(templateHtml || '');
            Object.entries(placeholders).forEach(([key, val]) => {
                output = output.split(`{{${key}}}`).join(String(val ?? ''));
            });
            return output;
        }
        /*<div>${escHtml(it.product_name || '-')} ${it.variant_name ? `(${escHtml(it.variant_name)})` : ''}</div>*/
        function buildItemsRows(items) {
            return items.map((it) => `
        <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:6px;">
            <div>

                <div>${escHtml(it.product_name || '-')} </div>
                <div style="font-size:11px;color:#6b7280;">${Number(it.qty || 0)} x ${formatRupiah(it.price || 0)}</div>
            </div>
            <div style="text-align:right;">${formatRupiah(it.subtotal || 0)}</div>
        </div>
    `).join('');
        }

        function setToday() {
            const now = new Date();
            const yyyy = now.getFullYear();
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            const dd = String(now.getDate()).padStart(2, '0');
            const firstDayOfMonth = `${yyyy}-${mm}-01`;
            const today = `${yyyy}-${mm}-${dd}`;
            txStartDate.value = firstDayOfMonth;
            txEndDate.value = today;
        }
        async function loadTransactions() {
            txTbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:var(--text-muted);">Memuat data...</td></tr>';
            const params = new URLSearchParams({
                action: 'list_today',
                page: String(page),
                page_size: '20',
                start_date: (txStartDate.value || '').trim(),
                end_date: (txEndDate.value || '').trim(),
                status: txStatus.value || 'all',
                search: (txSearch.value || '').trim(),
            });
            const res = await fetch(`checkout_actions.php?${params.toString()}`);
            const data = await res.json();
            if (!data.success) {
                txTbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:#b91c1c;">Gagal memuat data transaksi</td></tr>';
                return;
            }
            const rows = data.transactions || [];
            const pg = data.pagination || {};
            page = Number(pg.page || 1);
            totalPages = Number(pg.total_pages || 1);
            const pageSize = Number(pg.page_size || 20);
            const rowStart = ((page - 1) * pageSize) + 1;
            txPageInfo.textContent = `Halaman ${pg.page || 1} / ${pg.total_pages || 1} • Total ${pg.total_rows || 0} transaksi`;
            txPrev.disabled = page <= 1;
            txNext.disabled = page >= totalPages;

            if (!rows.length) {
                txTbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:var(--text-muted);">Tidak ada data.</td></tr>';
                return;
            }
            txTbody.innerHTML = rows.map((row, idx) => `
        <tr>
            <td class="tx-col-no">${rowStart + idx}</td>
            <td>${row.invoice_no}</td>
            <td>${row.customer_name || 'Pelanggan Umum'}</td>
            <td>${row.transaction_at}</td>
            <td>${formatRupiah(row.subtotal)}</td>
            <td>${formatRupiah(row.discount || 0)}</td>
            <td>${formatRupiah(row.downpayment || 0)}</td>
            <td>${formatRupiah((row.subtotal || 0) - (row.discount || 0) - (row.downpayment || 0))}</td>
            <td>
                <div>${row.payment_method === 'transfer' ? 'Transfer Rek' : 'Tunai'}</div>
                <div style="font-size:11px;color:var(--text-muted);">${row.payment_note ? escHtml(row.payment_note) : (Number(row.is_non_cash) === 1 ? 'Non tunai' : 'Tunai')}</div>
            </td>
            <td><span class="badge ${row.status}">${row.status === 'paid' ? 'Sudah Bayar' : 'Belum Bayar'}</span></td>
            <td><span class="badge ${Number(row.is_printed) === 1 ? 'paid' : 'pending'}">${Number(row.is_printed) === 1 ? 'Sudah Cetak' : 'Belum Cetak'}</span></td>
            <td style="display:flex;gap:6px;flex-wrap:wrap;">
                <button class="btn-link btn-detail" onclick="openTxDetail(${row.id})">Detail</button>
                <button class="btn-link btn-nota" onclick="printTxNota(${row.id})">Cetak Nota</button>
                <button class="btn-link btn-kwitansi" onclick="printTxKwitansi(${row.id})">Cetak Kwitansi</button>
                ${row.status === 'paid' ? `<button class="btn-link btn-danger-lite" onclick="openCancelPaidModal(${row.id})">Batal Bayar</button>` : ''}
                ${row.status === 'pending' ? `<a class="btn-link btn-continue" href="index.php?draft_id=${row.id}">Lanjutkan</a>` : ''}
                <button class="btn-link btn-danger-lite" onclick="deleteTx(${row.id})" ${(row.status === 'pending' && Number(row.is_printed) === 0) ? '' : 'disabled'}>Delete</button>
            </td>
        </tr>
    `).join('');
        }
        window.openTxDetail = async function(id) {
            const res = await fetch(`checkout_actions.php?action=detail&id=${id}`);
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Gagal memuat detail', 'error');
                return;
            }
            const tx = data.transaction || {};
            const items = data.items || [];
            const totalQty = items.reduce((sum, it) => sum + Number(it.qty || 0), 0);
            const totalSubtotal = items.reduce((sum, it) => sum + Number(it.subtotal || 0), 0);
            txModalBody.innerHTML = `
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:14px;">
            <div style="border:1px solid var(--border-color);border-radius:10px;padding:10px;background:#f8fafc;"><div style="font-size:11px;color:var(--text-muted);">Invoice</div><div style="font-weight:700;">${escHtml(tx.invoice_no || '-')}</div></div>
            <div style="border:1px solid var(--border-color);border-radius:10px;padding:10px;background:#f8fafc;"><div style="font-size:11px;color:var(--text-muted);">Status</div><div style="font-weight:700;">${tx.status === 'paid' ? 'Sudah Bayar' : 'Belum Bayar'}</div></div>
            <div style="border:1px solid var(--border-color);border-radius:10px;padding:10px;background:#f8fafc;"><div style="font-size:11px;color:var(--text-muted);">Pelanggan</div><div style="font-weight:700;">${escHtml(tx.customer_name || 'Pelanggan Umum')}</div></div>
            <div style="border:1px solid var(--border-color);border-radius:10px;padding:10px;background:#f8fafc;"><div style="font-size:11px;color:var(--text-muted);">Waktu</div><div style="font-weight:700;">${escHtml(tx.transaction_at || '-')}</div></div>
            <div style="border:1px solid var(--border-color);border-radius:10px;padding:10px;background:#f8fafc;"><div style="font-size:11px;color:var(--text-muted);">Diskon</div><div style="font-weight:700;">${formatRupiah(tx.discount || 0)}</div></div>
            <div style="border:1px solid var(--border-color);border-radius:10px;padding:10px;background:#f8fafc;"><div style="font-size:11px;color:var(--text-muted);">Uang Muka</div><div style="font-weight:700;">${formatRupiah(tx.downpayment || 0)}</div></div>
            <div style="border:1px solid var(--border-color);border-radius:10px;padding:10px;background:#f8fafc;"><div style="font-size:11px;color:var(--text-muted);">Metode Bayar</div><div style="font-weight:700;">${tx.payment_method === 'transfer' ? 'Transfer Rek' : 'Tunai'}</div></div>
            <div style="border:1px solid var(--border-color);border-radius:10px;padding:10px;background:#f8fafc;"><div style="font-size:11px;color:var(--text-muted);">Keterangan</div><div style="font-weight:700;">${escHtml(tx.payment_note || '-')}</div></div>
        </div>
        <table class="tx-table">
            <thead><tr><th>Produk</th><th>Varian</th><th>Harga</th><th>Qty</th><th>Subtotal</th></tr></thead>
            <tbody>
                ${items.map(it => `
                    <tr>
                        <td>${escHtml(it.product_name || '-')}</td>
                        <td>${escHtml(it.variant_name || '-')}</td>
                        <td>${formatRupiah(it.price)}</td>
                        <td style="text-align:center;">${Number(it.qty || 0)}</td>
                        <td>${formatRupiah(it.subtotal)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
        <div style="display:flex;justify-content:flex-end;gap:20px;flex-wrap:wrap;margin-top:12px;padding-top:10px;border-top:1px solid var(--border-color);font-weight:700;">
            <div>Total Qty: ${totalQty}</div>
            <div>Total Subtotal: ${formatRupiah(totalSubtotal)}</div>
        </div>
    `;
            txModal.classList.add('active');
        };
        window.closeTxModal = function() {
            txModal.classList.remove('active');
        };

        window.openCancelPaidModal = function(id) {
            activeCancelPaidId = Number(id || 0);
            if (!activeCancelPaidId) return;
            cancelPaidPassword.value = '';
            setCancelValidation('');
            cancelPaidModal.classList.add('active');
            setTimeout(() => cancelPaidPassword.focus(), 60);
        };

        window.closeCancelPaidModal = function() {
            activeCancelPaidId = 0;
            cancelPaidPassword.value = '';
            setCancelValidation('');
            cancelPaidModal.classList.remove('active');
        };

        function buildPrintableNote(tx, items) {
            const itemRows = buildItemsRows(items);
            const storeName = receiptTemplate.store_name || 'Toko POS Kita';
            const storeAddress = receiptTemplate.store_address || '';
            const storePhone = receiptTemplate.store_phone || '';
            const bankAccount = receiptTemplate.bank_account || '';
            const footerText = receiptTemplate.footer_text || 'Terima kasih sudah berbelanja';
            const statusText = tx.status === 'paid' ? 'Sudah Bayar' : 'Belum Bayar';
            if (printTemplates.nota && String(printTemplates.nota).trim() !== '') {
                const logoUrl = receiptTemplate.logo_url || '';
                const logoImg = logoUrl ? `<img src="${escHtml(logoUrl)}" alt="Logo" style="max-width:120px;max-height:60px;object-fit:contain;">` : '';
                const subtotal = Number(tx.subtotal || 0);
                const discountVal = Number(tx.discount || 0);
                const dpVal = Number(tx.downpayment || 0);
                const sisaBayar = Math.max(0, subtotal - discountVal - dpVal);
                const paymentMethodLabel = tx.payment_method === 'transfer' ? 'Transfer Rek' : 'Tunai';
                return applyPrintTemplate(printTemplates.nota, {
                    store_name: escHtml(storeName),
                    store_address: escHtml(storeAddress),
                    store_phone: escHtml(storePhone),
                    bank_account: escHtml(bankAccount),
                    logo_url: escHtml(logoUrl),
                    logo_img: logoImg,
                    invoice_no: escHtml(tx.invoice_no || '-'),
                    transaction_at: escHtml(tx.transaction_at || '-'),
                    customer_name: escHtml(tx.customer_name || 'Pelanggan Umum'),
                    items_rows: itemRows,
                    // total should be the subtotal (sum of item subtotals) before discount and downpayment
                    total: escHtml(formatRupiah(subtotal)),
                    discount: escHtml(formatRupiah(tx.discount || 0)),
                    downpayment: escHtml(formatRupiah(tx.downpayment || 0)),
                    sisa_bayar: escHtml(formatRupiah(sisaBayar)),
                    paid_amount: escHtml(formatRupiah(tx.paid_amount || 0)),
                    change_amount: escHtml(formatRupiah(tx.change_amount || 0)),
                    payment_method: escHtml(paymentMethodLabel),
                    payment_note: escHtml(tx.payment_note || ''),
                    status: escHtml(statusText),
                    footer_text: escHtml(footerText),
                });
            }

            const headerAlign = receiptTemplate.header_align || 'center';
            const showLogo = String(receiptTemplate.show_logo || '0') === '1';
            const showStoreInfo = String(receiptTemplate.show_store_info || '1') === '1';
            const showItemCode = String(receiptTemplate.show_item_code || '0') === '1';
            const thermalWidth = Number(receiptTemplate.paper_width_mm || 58) === 80 ? '76mm' : '58mm';
            const fontSize = Number(receiptTemplate.font_size_px || 12);
            const logoUrl = receiptTemplate.logo_url || '';
            const headerText = receiptTemplate.header_text || '';
            /* <span>${escHtml(it.product_name)}${it.variant_name ? ` (${escHtml(it.variant_name)})` : ''}${showItemCode ? `<div style="font-size:10px;color:#666;">${escHtml(it.product_code || '')}</div>` : ''}</span> */
            const thermalItemRows = items.map((it) => `
        <div style="display:flex;justify-content:space-between;margin-bottom:5px;font-size:12px;">
             <span>${escHtml(it.product_name)}${showItemCode ? `<div style="font-size:10px;color:#666;">${escHtml(it.product_code || '')}</div>` : ''}</span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:11px;color:#555;">
            <span>${Number(it.qty)} x ${formatRupiah(it.price)}</span>
            <span>${formatRupiah(it.subtotal)}</span>
        </div>
    `).join('');
            return `
        <div style="width:${thermalWidth};margin:0 auto;font-family:'Courier New',monospace;color:#111;padding:10px;font-size:${fontSize}px;">
            <div style="text-align:${headerAlign};margin-bottom:8px;">
                ${showLogo && logoUrl ? `<img src="${logoUrl}" alt="logo" style="max-width:110px;max-height:50px;object-fit:contain;margin-bottom:4px;">` : ''}
                ${showStoreInfo ? `<h3 style="margin:0;font-size:15px;">${escHtml(storeName)}</h3>` : ''}
                ${showStoreInfo && storeAddress ? `<div style="font-size:11px;color:#555;">${escHtml(storeAddress)}</div>` : ''}
                ${showStoreInfo && storePhone ? `<div style="font-size:11px;color:#555;">${escHtml(storePhone)}</div>` : ''}
                ${showStoreInfo && bankAccount ? `<div style="font-size:11px;color:#555;">Rek: ${escHtml(bankAccount)}</div>` : ''}
                ${headerText ? `<div style="font-size:11px;color:#555;">${escHtml(headerText)}</div>` : ''}
                <div style="font-size:11px;color:#555;">${escHtml(tx.invoice_no || '-')}</div>
                <div style="font-size:11px;color:#555;">${escHtml(tx.transaction_at || '-')}</div>
            </div>
            <div style="font-size:11px;margin-bottom:8px;">Pelanggan: ${escHtml(tx.customer_name || 'Pelanggan Umum')}</div>
            <div style="border-bottom:1px dashed #000;margin:8px 0;"></div>
            ${thermalItemRows}
            <div style="border-bottom:1px dashed #000;margin:8px 0;"></div>
            <div style="display:flex;justify-content:space-between;font-size:12px;">
                <span>Diskon</span><span>${formatRupiah(tx.discount || 0)}</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;">
                <span>Uang Muka</span><span>${formatRupiah(tx.downpayment || 0)}</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;">
                <span>Total</span><span>${formatRupiah(Number(tx.subtotal || 0))}</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;">
                <span>Sisa Bayar</span><span>${formatRupiah(Math.max(0, Number(tx.subtotal || 0) - Number(tx.discount || 0) - Number(tx.downpayment || 0)))}</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;">
                <span>${tx.payment_method === 'transfer' ? 'Transfer Rek' : 'Tunai'}</span><span>${formatRupiah(tx.paid_amount)}</span>
            </div>
            ${tx.payment_note ? `<div style="font-size:11px;color:#555;">Ket: ${escHtml(tx.payment_note)}</div>` : ''}
            <div style="display:flex;justify-content:space-between;font-size:12px;">
                <span>Kembalian</span><span>${formatRupiah(tx.change_amount)}</span>
            </div>
            <div style="margin-top:8px;font-size:11px;">Status: ${tx.status === 'paid' ? 'Sudah Bayar' : 'Belum Bayar'}</div>
            <div style="text-align:center;margin-top:8px;font-size:11px;color:#4b5563;">${escHtml(footerText)}</div>
        </div>
    `;
        }

        function buildPrintableKwitansi(tx, items) {
            const itemRows = buildItemsRows(items);
            const storeName = receiptTemplate.store_name || 'Toko POS Kita';
            const storeAddress = receiptTemplate.store_address || '';
            const storePhone = receiptTemplate.store_phone || '';
            const bankAccount = receiptTemplate.bank_account || '';
            const footerText = receiptTemplate.footer_text || 'Terima kasih sudah berbelanja';
            const statusText = tx.status === 'paid' ? 'Sudah Bayar' : 'Belum Bayar';

            if (printTemplates.kwitansi && String(printTemplates.kwitansi).trim() !== '') {
                const logoUrl = receiptTemplate.logo_url || '';
                const logoImg = logoUrl ? `<img src="${escHtml(logoUrl)}" alt="Logo" style="max-width:120px;max-height:60px;object-fit:contain;">` : '';
                return applyPrintTemplate(printTemplates.kwitansi, {
                    store_name: escHtml(storeName),
                    store_address: escHtml(storeAddress),
                    store_phone: escHtml(storePhone),
                    bank_account: escHtml(bankAccount),
                    logo_url: escHtml(logoUrl),
                    logo_img: logoImg,
                    invoice_no: escHtml(tx.invoice_no || '-'),
                    transaction_at: escHtml(tx.transaction_at || '-'),
                    customer_name: escHtml(tx.customer_name || 'Pelanggan Umum'),
                    items_rows: itemRows,
                    total: escHtml(formatRupiah(tx.total || 0)),
                    discount: escHtml(formatRupiah(tx.discount || 0)),
                    downpayment: escHtml(formatRupiah(tx.downpayment || 0)),
                    paid_amount: escHtml(formatRupiah(tx.paid_amount || 0)),
                    change_amount: escHtml(formatRupiah(tx.change_amount || 0)),
                    payment_method: escHtml(tx.payment_method === 'transfer' ? 'Transfer Rek' : 'Tunai'),
                    payment_note: escHtml(tx.payment_note || ''),
                    status: escHtml(statusText),
                    footer_text: escHtml(footerText),
                });
            }

            return `
        <div style="font-family:Arial,sans-serif;font-size:13px;color:#222;max-width:700px;margin:0 auto;border:1px solid #ddd;padding:16px;">
            <h2 style="text-align:center;margin:0 0 14px;">KWITANSI</h2>
            <table style="width:100%;margin-bottom:12px;">
                <tr><td style="width:140px;">No Kwitansi</td><td>: ${escHtml(tx.invoice_no || '-')}</td></tr>
                <tr><td>Tanggal</td><td>: ${escHtml(tx.transaction_at || '-')}</td></tr>
                <tr><td>Sudah terima dari</td><td>: ${escHtml(tx.customer_name || 'Pelanggan Umum')}</td></tr>
            </table>
            <div style="border:1px dashed #aaa;padding:10px;margin-bottom:12px;">${itemRows}</div>
            <table style="width:100%;">
                <tr><td style="width:140px;">Total</td><td>: ${formatRupiah(tx.total || 0)}</td></tr>
                <tr><td>Diskon</td><td>: ${formatRupiah(tx.discount || 0)}</td></tr>
                <tr><td>Uang Muka</td><td>: ${formatRupiah(tx.downpayment || 0)}</td></tr>
                <tr><td>Metode Bayar</td><td>: ${escHtml(tx.payment_method === 'transfer' ? 'Transfer Rek' : 'Tunai')}</td></tr>
                <tr><td>Keterangan</td><td>: ${escHtml(tx.payment_note || '-')}</td></tr>
                <tr><td>Status</td><td>: ${statusText}</td></tr>
                <tr><td>Rekening</td><td>: ${escHtml(bankAccount || '-')}</td></tr>
            </table>
            <div style="text-align:center;margin-top:12px;color:#6b7280;">${escHtml(footerText)}</div>
        </div>
    `;
        }

        async function waitForPrintAssets(container) {
            if (!container) return;
            const imgs = Array.from(container.querySelectorAll('img'));
            if (!imgs.length) return;

            await Promise.all(
                imgs.map((img) => new Promise((resolve) => {
                    if (img.complete) {
                        resolve();
                        return;
                    }
                    img.addEventListener('load', () => resolve(), { once: true });
                    img.addEventListener('error', () => resolve(), { once: true });
                }))
            );

            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        }

        window.printTxNota = async function(id) {
            try {
                const res = await fetch(`checkout_actions.php?action=detail&id=${id}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Gagal ambil detail transaksi');
                const tx = data.transaction || {};
                const items = data.items || [];
                printArea.innerHTML = buildPrintableNote(tx, items);
                await waitForPrintAssets(printArea);
                window.print();
                await fetch('checkout_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'mark_printed',
                        transaction_id: id
                    }),
                });
                await loadTransactions();
            } catch (err) {
                showToast(err.message || 'Gagal mencetak nota', 'error');
            }
        };
        window.printTxKwitansi = async function(id) {
            try {
                const res = await fetch(`checkout_actions.php?action=detail&id=${id}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Gagal ambil detail transaksi');
                const tx = data.transaction || {};
                const items = data.items || [];
                printArea.innerHTML = buildPrintableKwitansi(tx, items);
                await waitForPrintAssets(printArea);
                window.print();
                await fetch('checkout_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'mark_printed',
                        transaction_id: id
                    }),
                });
                await loadTransactions();
            } catch (err) {
                showToast(err.message || 'Gagal mencetak kwitansi', 'error');
            }
        };
        window.deleteTx = async function(id) {
            const proceed = window.confirm('Hapus transaksi ini? Hanya boleh untuk transaksi belum bayar dan belum cetak.');
            if (!proceed) return;
            try {
                const res = await fetch('checkout_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete_pending',
                        transaction_id: id
                    }),
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Gagal menghapus transaksi');
                await loadTransactions();
                showToast('Transaksi berhasil dihapus.', 'success');
            } catch (err) {
                showToast(err.message, 'error');
            }
        };
        async function submitCancelPaid() {
            if (!activeCancelPaidId) return;
            const password = (cancelPaidPassword.value || '').trim();
            if (!password) {
                setCancelValidation('Password login wajib diisi.');
                return;
            }
            const original = confirmCancelPaidBtn.textContent;
            confirmCancelPaidBtn.disabled = true;
            confirmCancelPaidBtn.textContent = 'Memproses...';
            try {
                const res = await fetch('checkout_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'cancel_paid',
                        transaction_id: activeCancelPaidId,
                        password
                    }),
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Gagal membatalkan status bayar');
                closeCancelPaidModal();
                await loadTransactions();
                showToast('Status transaksi berhasil diubah menjadi belum bayar.', 'success');
            } catch (err) {
                setCancelValidation(err.message || 'Gagal membatalkan pembayaran.');
            } finally {
                confirmCancelPaidBtn.disabled = false;
                confirmCancelPaidBtn.textContent = original;
            }
        }
        function reloadFromFirstPage() {
            page = 1;
            loadTransactions();
        }
        btnSearch.addEventListener('click', () => {
            reloadFromFirstPage();
        });
        txPrev.addEventListener('click', () => {
            if (page > 1) {
                page -= 1;
                loadTransactions();
            }
        });
        txNext.addEventListener('click', () => {
            if (page < totalPages) {
                page += 1;
                loadTransactions();
            }
        });
        txStatus.addEventListener('change', () => {
            reloadFromFirstPage();
        });
        txStartDate.addEventListener('change', () => {
            reloadFromFirstPage();
        });
        txEndDate.addEventListener('change', () => {
            reloadFromFirstPage();
        });
        txSearch.addEventListener('input', () => {
            if (searchDebounceTimer) {
                clearTimeout(searchDebounceTimer);
            }
            searchDebounceTimer = setTimeout(() => {
                reloadFromFirstPage();
            }, 300);
        });
        txSearch.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                if (searchDebounceTimer) {
                    clearTimeout(searchDebounceTimer);
                }
                reloadFromFirstPage();
            }
        });
        if (confirmCancelPaidBtn) {
            confirmCancelPaidBtn.addEventListener('click', submitCancelPaid);
        }
        if (cancelPaidPassword) {
            cancelPaidPassword.addEventListener('input', () => {
                setCancelValidation('');
            });
            cancelPaidPassword.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitCancelPaid();
                }
            });
        }
        if (cancelPaidModal) {
            cancelPaidModal.addEventListener('click', (e) => {
                if (e.target === cancelPaidModal) {
                    closeCancelPaidModal();
                }
            });
        }
        setToday();
        loadTransactions();
    </script>
</body>

</html>
