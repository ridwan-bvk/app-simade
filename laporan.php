<?php
require_once 'config/database.php';
require_once 'auth.php';

$pdo = get_db();
ensure_auth_tables($pdo);
require_login();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS master_units (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        unit_code VARCHAR(24) NOT NULL UNIQUE,
        unit_name VARCHAR(80) NOT NULL,
        unit_symbol VARCHAR(24) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
$hasUnitIdOnProducts = $pdo->query("SHOW COLUMNS FROM products LIKE 'unit_id'")->fetch(PDO::FETCH_ASSOC);
if (!$hasUnitIdOnProducts) {
    $pdo->exec('ALTER TABLE products ADD COLUMN unit_id INT UNSIGNED NULL AFTER kategori');
}
$hasUnitBaseQtyOnProducts = $pdo->query("SHOW COLUMNS FROM products LIKE 'unit_base_qty'")->fetch(PDO::FETCH_ASSOC);
if (!$hasUnitBaseQtyOnProducts) {
    $pdo->exec('ALTER TABLE products ADD COLUMN unit_base_qty DECIMAL(15,4) NOT NULL DEFAULT 1 AFTER unit_id');
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS sales_transactions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(40) NOT NULL UNIQUE,
        status ENUM("pending","paid") NOT NULL DEFAULT "pending",
        is_printed TINYINT(1) NOT NULL DEFAULT 0,
        printed_at DATETIME NULL,
        transaction_at DATETIME NOT NULL,
        customer_name VARCHAR(150) NULL,
        subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
        total DECIMAL(15,2) NOT NULL DEFAULT 0,
        paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        change_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS sales_transaction_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT UNSIGNED NOT NULL,
        product_id INT UNSIGNED NULL,
        product_code VARCHAR(60) NULL,
        product_name VARCHAR(200) NOT NULL,
        variant_id INT UNSIGNED NULL,
        variant_name VARCHAR(120) NULL,
        price DECIMAL(15,2) NOT NULL DEFAULT 0,
        qty INT NOT NULL DEFAULT 1,
        unit_id_snapshot INT UNSIGNED NULL,
        unit_symbol_snapshot VARCHAR(24) NULL,
        unit_base_qty_snapshot DECIMAL(15,4) NOT NULL DEFAULT 1,
        base_qty_total DECIMAL(15,4) NOT NULL DEFAULT 0,
        subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_transaction_id (transaction_id),
        CONSTRAINT fk_sales_items_transaction
            FOREIGN KEY (transaction_id) REFERENCES sales_transactions(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
$hasUnitIdSnapshot = $pdo->query("SHOW COLUMNS FROM sales_transaction_items LIKE 'unit_id_snapshot'")->fetch(PDO::FETCH_ASSOC);
if (!$hasUnitIdSnapshot) {
    $pdo->exec("ALTER TABLE sales_transaction_items ADD COLUMN unit_id_snapshot INT UNSIGNED NULL AFTER qty");
}
$hasUnitSymbolSnapshot = $pdo->query("SHOW COLUMNS FROM sales_transaction_items LIKE 'unit_symbol_snapshot'")->fetch(PDO::FETCH_ASSOC);
if (!$hasUnitSymbolSnapshot) {
    $pdo->exec("ALTER TABLE sales_transaction_items ADD COLUMN unit_symbol_snapshot VARCHAR(24) NULL AFTER unit_id_snapshot");
}
$hasUnitBaseQtySnapshot = $pdo->query("SHOW COLUMNS FROM sales_transaction_items LIKE 'unit_base_qty_snapshot'")->fetch(PDO::FETCH_ASSOC);
if (!$hasUnitBaseQtySnapshot) {
    $pdo->exec("ALTER TABLE sales_transaction_items ADD COLUMN unit_base_qty_snapshot DECIMAL(15,4) NOT NULL DEFAULT 1 AFTER unit_symbol_snapshot");
}
$hasBaseQtyTotal = $pdo->query("SHOW COLUMNS FROM sales_transaction_items LIKE 'base_qty_total'")->fetch(PDO::FETCH_ASSOC);
if (!$hasBaseQtyTotal) {
    $pdo->exec("ALTER TABLE sales_transaction_items ADD COLUMN base_qty_total DECIMAL(15,4) NOT NULL DEFAULT 0 AFTER unit_base_qty_snapshot");
}

function rupiah($v): string
{
    return 'Rp ' . number_format((float)$v, 0, ',', '.');
}

function fetch_report_data(PDO $pdo, string $startDate, string $endDate, string $status): array
{
    $rangeWhere = 'st.transaction_at >= :start_dt AND st.transaction_at < :end_dt';
    $baseQtyExpr = 'COALESCE(NULLIF(sti.base_qty_total,0), (sti.qty * COALESCE(NULLIF(sti.unit_base_qty_snapshot,0),1)))';
    $baseParams = [
        ':start_dt' => $startDate . ' 00:00:00',
        ':end_dt' => date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00',
    ];

    $statusWhere = '';
    $statusParams = [];
    if ($status !== 'all') {
        $statusWhere = ' AND st.status = :status';
        $statusParams[':status'] = $status;
    }

    $summaryStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_trx,
            SUM(st.total) AS omzet_total,
            SUM(CASE WHEN st.status = 'paid' THEN st.total ELSE 0 END) AS omzet_paid,
            SUM(CASE WHEN st.status = 'pending' THEN st.total ELSE 0 END) AS omzet_pending
         FROM sales_transactions st
         WHERE {$rangeWhere} {$statusWhere}"
    );
    $summaryStmt->execute($baseParams + $statusParams);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $baseQtyWhere = $rangeWhere;
    $baseQtyParams = $baseParams;
    if ($status !== 'all') {
        $baseQtyWhere .= ' AND st.status = :status_base';
        $baseQtyParams[':status_base'] = $status;
    }
    $baseQtyStmt = $pdo->prepare(
        "SELECT COALESCE(SUM({$baseQtyExpr}), 0) AS total_base_qty
         FROM sales_transaction_items sti
         INNER JOIN sales_transactions st ON st.id = sti.transaction_id
         WHERE {$baseQtyWhere}"
    );
    $baseQtyStmt->execute($baseQtyParams);
    $summary['total_base_qty'] = (float)$baseQtyStmt->fetchColumn();

    $labaSummaryStmt = $pdo->prepare(
        "SELECT
            SUM(sti.subtotal) AS omzet_barang,
            SUM(sti.qty * COALESCE(p.harga_beli, 0)) AS modal_barang,
            SUM(sti.subtotal - (sti.qty * COALESCE(p.harga_beli, 0))) AS laba_kotor
         FROM sales_transaction_items sti
         INNER JOIN sales_transactions st ON st.id = sti.transaction_id
         LEFT JOIN products p ON p.id = sti.product_id
         WHERE {$rangeWhere} AND st.status = 'paid'"
    );
    $labaSummaryStmt->execute($baseParams);
    $labaSummary = $labaSummaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $detailStmt = $pdo->prepare(
        "SELECT
            st.id AS transaction_id,
            st.invoice_no,
            st.transaction_at,
            st.status,
            COALESCE(st.customer_name, 'Pelanggan Umum') AS customer_name,
            sti.product_code,
            sti.product_name,
            COALESCE(sti.variant_name, '-') AS variant_name,
            sti.qty,
            sti.price,
            sti.subtotal,
            sti.unit_symbol_snapshot,
            sti.unit_base_qty_snapshot,
            {$baseQtyExpr} AS base_qty_total,
            COALESCE(p.harga_beli, 0) AS harga_beli,
            (sti.qty * COALESCE(p.harga_beli, 0)) AS modal_total,
            (sti.subtotal - (sti.qty * COALESCE(p.harga_beli, 0))) AS laba
         FROM sales_transaction_items sti
         INNER JOIN sales_transactions st ON st.id = sti.transaction_id
         LEFT JOIN products p ON p.id = sti.product_id
         WHERE {$rangeWhere} {$statusWhere}
         ORDER BY st.transaction_at DESC, st.id DESC, sti.id ASC"
    );
    $detailStmt->execute($baseParams + $statusParams);
    $detailRows = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

    $rekapStmt = $pdo->prepare(
        "SELECT
            DATE(st.transaction_at) AS tx_date,
            COUNT(*) AS trx_count,
            SUM(st.total) AS omzet_total,
            SUM(CASE WHEN st.status = 'paid' THEN st.total ELSE 0 END) AS omzet_paid,
            SUM(CASE WHEN st.status = 'pending' THEN st.total ELSE 0 END) AS omzet_pending
         FROM sales_transactions st
         WHERE {$rangeWhere}
         GROUP BY DATE(st.transaction_at)
         ORDER BY tx_date DESC"
    );
    $rekapStmt->execute($baseParams);
    $rekapRows = $rekapStmt->fetchAll(PDO::FETCH_ASSOC);

    $labaStmt = $pdo->prepare(
        "SELECT
            sti.product_name,
            COALESCE(sti.variant_name, '-') AS variant_name,
            SUM(sti.qty) AS qty_total,
            SUM({$baseQtyExpr}) AS base_qty_total,
            MIN(COALESCE(sti.unit_symbol_snapshot, '')) AS unit_symbol_snapshot,
            AVG(sti.price) AS harga_jual_avg,
            COALESCE(p.harga_beli, 0) AS harga_beli,
            SUM(sti.subtotal) AS omzet_total,
            SUM(sti.qty * COALESCE(p.harga_beli, 0)) AS modal_total,
            SUM(sti.subtotal - (sti.qty * COALESCE(p.harga_beli, 0))) AS laba_total
         FROM sales_transaction_items sti
         INNER JOIN sales_transactions st ON st.id = sti.transaction_id
         LEFT JOIN products p ON p.id = sti.product_id
         WHERE {$rangeWhere} AND st.status = 'paid'
         GROUP BY sti.product_name, COALESCE(sti.variant_name, '-'), COALESCE(p.harga_beli, 0)
         ORDER BY laba_total DESC, omzet_total DESC"
    );
    $labaStmt->execute($baseParams);
    $labaRows = $labaStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'summary' => $summary,
        'laba_summary' => $labaSummary,
        'detail_rows' => $detailRows,
        'rekap_rows' => $rekapRows,
        'laba_rows' => $labaRows,
    ];
}

function sync_missing_unit_snapshots(PDO $pdo, string $startDate, string $endDate, string $status): int
{
    $where = [
        'st.transaction_at >= :start_dt',
        'st.transaction_at < :end_dt',
    ];
    $params = [
        ':start_dt' => $startDate . ' 00:00:00',
        ':end_dt' => date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00',
    ];
    if ($status !== 'all') {
        $where[] = 'st.status = :status';
        $params[':status'] = $status;
    }

    $sql = "UPDATE sales_transaction_items sti
            INNER JOIN sales_transactions st ON st.id = sti.transaction_id
            LEFT JOIN products p ON p.id = sti.product_id
            LEFT JOIN master_units mu ON mu.id = p.unit_id
            SET
                sti.unit_id_snapshot = p.unit_id,
                sti.unit_symbol_snapshot = COALESCE(mu.unit_symbol, sti.unit_symbol_snapshot),
                sti.unit_base_qty_snapshot = CASE
                    WHEN p.unit_base_qty IS NULL OR p.unit_base_qty <= 0 THEN 1
                    ELSE p.unit_base_qty
                END,
                sti.base_qty_total = sti.qty * CASE
                    WHEN p.unit_base_qty IS NULL OR p.unit_base_qty <= 0 THEN 1
                    ELSE p.unit_base_qty
                END
            WHERE " . implode(' AND ', $where) . "
              AND (
                    sti.unit_id_snapshot IS NULL
                    OR COALESCE(sti.unit_symbol_snapshot, '') = ''
                    OR COALESCE(sti.unit_base_qty_snapshot, 0) <= 0
                    OR COALESCE(sti.base_qty_total, 0) <= 0
              )";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->rowCount();
}

function output_csv_export(array $data, string $startDate, string $endDate, string $status): void
{
    $filename = "laporan_transaksi_{$startDate}_{$endDate}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Laporan Transaksi', $startDate . ' s/d ' . $endDate, 'Status', strtoupper($status)]);
    fputcsv($out, []);
    fputcsv($out, ['RINGKASAN']);
    fputcsv($out, ['Jumlah Transaksi', (string)($data['summary']['total_trx'] ?? 0)]);
    fputcsv($out, ['Omzet Total', (string)($data['summary']['omzet_total'] ?? 0)]);
    fputcsv($out, ['Omzet Paid', (string)($data['summary']['omzet_paid'] ?? 0)]);
    fputcsv($out, ['Omzet Pending', (string)($data['summary']['omzet_pending'] ?? 0)]);
    fputcsv($out, ['Total Base Qty', (string)round((float)($data['summary']['total_base_qty'] ?? 0), 2)]);
    fputcsv($out, ['Laba Kotor', (string)($data['laba_summary']['laba_kotor'] ?? 0)]);
    fputcsv($out, []);

    fputcsv($out, ['DETAIL TRANSAKSI']);
    fputcsv($out, ['Waktu', 'Invoice', 'Pelanggan', 'Status', 'Produk', 'Varian', 'Qty', 'Satuan', 'Konversi Base', 'Total Base Qty', 'Harga Jual', 'Subtotal', 'Harga Beli', 'Modal', 'Laba']);
    foreach ($data['detail_rows'] as $row) {
        fputcsv($out, [
            $row['transaction_at'] ?? '',
            $row['invoice_no'] ?? '',
            $row['customer_name'] ?? '',
            $row['status'] ?? '',
            $row['product_name'] ?? '',
            $row['variant_name'] ?? '',
            $row['qty'] ?? 0,
            $row['unit_symbol_snapshot'] ?? '',
            round((float)($row['unit_base_qty_snapshot'] ?? 0), 2),
            round((float)($row['base_qty_total'] ?? 0), 2),
            $row['price'] ?? 0,
            $row['subtotal'] ?? 0,
            $row['harga_beli'] ?? 0,
            $row['modal_total'] ?? 0,
            $row['laba'] ?? 0,
        ]);
    }
    fputcsv($out, []);

    fputcsv($out, ['REKAP HARIAN']);
    fputcsv($out, ['Tanggal', 'Jumlah Transaksi', 'Omzet Total', 'Omzet Paid', 'Omzet Pending']);
    foreach ($data['rekap_rows'] as $row) {
        fputcsv($out, [
            $row['tx_date'] ?? '',
            $row['trx_count'] ?? 0,
            $row['omzet_total'] ?? 0,
            $row['omzet_paid'] ?? 0,
            $row['omzet_pending'] ?? 0,
        ]);
    }
    fputcsv($out, []);

    fputcsv($out, ['LABA PER PRODUK']);
    fputcsv($out, ['Produk', 'Varian', 'Qty Terjual', 'Total Base Qty', 'Satuan', 'Harga Jual Avg', 'Harga Beli', 'Omzet', 'Modal', 'Laba']);
    foreach ($data['laba_rows'] as $row) {
        fputcsv($out, [
            $row['product_name'] ?? '',
            $row['variant_name'] ?? '',
            $row['qty_total'] ?? 0,
            round((float)($row['base_qty_total'] ?? 0), 2),
            $row['unit_symbol_snapshot'] ?? '',
            $row['harga_jual_avg'] ?? 0,
            $row['harga_beli'] ?? 0,
            $row['omzet_total'] ?? 0,
            $row['modal_total'] ?? 0,
            $row['laba_total'] ?? 0,
        ]);
    }
    fclose($out);
    exit;
}

function output_excel_export(array $data, string $startDate, string $endDate, string $status): void
{
    $filename = "laporan_transaksi_{$startDate}_{$endDate}.xls";
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo '<html><head><meta charset="UTF-8"><style>table{border-collapse:collapse;}th,td{border:1px solid #999;padding:4px;font-size:12px;}th{background:#eee;}</style></head><body>';
    echo '<h3>Laporan Transaksi</h3>';
    echo '<p>Periode: ' . htmlspecialchars($startDate . ' s/d ' . $endDate) . ' | Status: ' . htmlspecialchars(strtoupper($status)) . '</p>';
    echo '<table>';
    echo '<tr><th colspan="2">Ringkasan</th></tr>';
    echo '<tr><td>Jumlah Transaksi</td><td>' . (float)($data['summary']['total_trx'] ?? 0) . '</td></tr>';
    echo '<tr><td>Omzet Total</td><td>' . (float)($data['summary']['omzet_total'] ?? 0) . '</td></tr>';
    echo '<tr><td>Omzet Paid</td><td>' . (float)($data['summary']['omzet_paid'] ?? 0) . '</td></tr>';
    echo '<tr><td>Omzet Pending</td><td>' . (float)($data['summary']['omzet_pending'] ?? 0) . '</td></tr>';
    echo '<tr><td>Total Base Qty</td><td>' . round((float)($data['summary']['total_base_qty'] ?? 0), 2) . '</td></tr>';
    echo '<tr><td>Laba Kotor</td><td>' . (float)($data['laba_summary']['laba_kotor'] ?? 0) . '</td></tr>';
    echo '</table><br>';

    echo '<table><tr><th colspan="15">Detail Transaksi</th></tr>';
    echo '<tr><th>Waktu</th><th>Invoice</th><th>Pelanggan</th><th>Status</th><th>Produk</th><th>Varian</th><th>Qty</th><th>Satuan</th><th>Konversi Base</th><th>Total Base Qty</th><th>Harga Jual</th><th>Subtotal</th><th>Harga Beli</th><th>Modal</th><th>Laba</th></tr>';
    foreach ($data['detail_rows'] as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string)($row['transaction_at'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['invoice_no'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['customer_name'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['status'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['product_name'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['variant_name'] ?? '')) . '</td>';
        echo '<td>' . (float)($row['qty'] ?? 0) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['unit_symbol_snapshot'] ?? '')) . '</td>';
        echo '<td>' . round((float)($row['unit_base_qty_snapshot'] ?? 0), 2) . '</td>';
        echo '<td>' . round((float)($row['base_qty_total'] ?? 0), 2) . '</td>';
        echo '<td>' . (float)($row['price'] ?? 0) . '</td>';
        echo '<td>' . (float)($row['subtotal'] ?? 0) . '</td>';
        echo '<td>' . (float)($row['harga_beli'] ?? 0) . '</td>';
        echo '<td>' . (float)($row['modal_total'] ?? 0) . '</td>';
        echo '<td>' . (float)($row['laba'] ?? 0) . '</td>';
        echo '</tr>';
    }
    echo '</table><br>';

    echo '<table><tr><th colspan="5">Rekap Harian</th></tr>';
    echo '<tr><th>Tanggal</th><th>Jumlah Transaksi</th><th>Omzet Total</th><th>Omzet Paid</th><th>Omzet Pending</th></tr>';
    foreach ($data['rekap_rows'] as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string)($row['tx_date'] ?? '')) . '</td>';
        echo '<td>' . (float)($row['trx_count'] ?? 0) . '</td>';
        echo '<td>' . (float)($row['omzet_total'] ?? 0) . '</td>';
        echo '<td>' . (float)($row['omzet_paid'] ?? 0) . '</td>';
        echo '<td>' . (float)($row['omzet_pending'] ?? 0) . '</td>';
        echo '</tr>';
    }
    echo '</table><br>';

    echo '<table><tr><th colspan="10">Laba Per Produk</th></tr>';
    echo '<tr><th>Produk</th><th>Varian</th><th>Qty Terjual</th><th>Total Base Qty</th><th>Satuan</th><th>Harga Jual Avg</th><th>Harga Beli</th><th>Omzet</th><th>Modal</th><th>Laba</th></tr>';
    foreach ($data['laba_rows'] as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string)($row['product_name'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['variant_name'] ?? '')) . '</td>';
        echo '<td>' . (float)($row['qty_total'] ?? 0) . '</td>';
        echo '<td>' . round((float)($row['base_qty_total'] ?? 0), 2) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['unit_symbol_snapshot'] ?? '')) . '</td>';
        echo '<td>' . (float)($row['harga_jual_avg'] ?? 0) . '</td>';
        echo '<td>' . (float)($row['harga_beli'] ?? 0) . '</td>';
        echo '<td>' . (float)($row['omzet_total'] ?? 0) . '</td>';
        echo '<td>' . (float)($row['modal_total'] ?? 0) . '</td>';
        echo '<td>' . (float)($row['laba_total'] ?? 0) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</body></html>';
    exit;
}

$startDate = trim((string)($_GET['start_date'] ?? date('Y-m-01')));
$endDate = trim((string)($_GET['end_date'] ?? date('Y-m-d')));
$status = trim((string)($_GET['status'] ?? 'all'));
if (!in_array($status, ['all', 'pending', 'paid'], true)) {
    $status = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'refresh_unit_snapshot') {
    $startDatePost = trim((string)($_POST['start_date'] ?? date('Y-m-01')));
    $endDatePost = trim((string)($_POST['end_date'] ?? date('Y-m-d')));
    $statusPost = trim((string)($_POST['status'] ?? 'all'));
    if (!in_array($statusPost, ['all', 'pending', 'paid'], true)) {
        $statusPost = 'all';
    }
    $updated = sync_missing_unit_snapshots($pdo, $startDatePost, $endDatePost, $statusPost);
    header('Location: laporan.php?start_date=' . urlencode($startDatePost) . '&end_date=' . urlencode($endDatePost) . '&status=' . urlencode($statusPost) . '&msg=unit_refresh_done&updated=' . $updated);
    exit;
}

$data = fetch_report_data($pdo, $startDate, $endDate, $status);

$exportType = trim((string)($_GET['export'] ?? ''));
if ($exportType === 'csv') {
    output_csv_export($data, $startDate, $endDate, $status);
}
if ($exportType === 'excel') {
    output_excel_export($data, $startDate, $endDate, $status);
}

$summary = $data['summary'];
$labaSummary = $data['laba_summary'];
$detailRows = $data['detail_rows'];
$rekapRows = $data['rekap_rows'];
$labaRows = $data['laba_rows'];
$baseQtyByUnit = [];
foreach ($detailRows as $dr) {
    $unit = trim((string)($dr['unit_symbol_snapshot'] ?? ''));
    if ($unit === '') {
        $unit = 'unit';
    }
    if (!isset($baseQtyByUnit[$unit])) {
        $baseQtyByUnit[$unit] = 0.0;
    }
    $baseQtyByUnit[$unit] += (float)($dr['base_qty_total'] ?? 0);
}
ksort($baseQtyByUnit);

$labaQtyTotal = 0.0;
$labaBaseQtyTotal = 0.0;
$labaOmzetTotal = 0.0;
$labaModalTotal = 0.0;
$labaGrandTotal = 0.0;
foreach ($labaRows as $lr) {
    $labaQtyTotal += (float)($lr['qty_total'] ?? 0);
    $labaBaseQtyTotal += (float)($lr['base_qty_total'] ?? 0);
    $labaOmzetTotal += (float)($lr['omzet_total'] ?? 0);
    $labaModalTotal += (float)($lr['modal_total'] ?? 0);
    $labaGrandTotal += (float)($lr['laba_total'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Aplikasi SiMade</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/products.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .report-page { flex: 1; padding: 24px 30px; overflow-y: auto; }
        .filter-box {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 12px;
            display: grid;
            grid-template-columns: 1fr 1fr 180px auto auto auto auto;
            gap: 10px;
            align-items: end;
            margin-bottom: 12px;
        }
        .filter-box label { display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 5px; font-weight: 600; }
        .filter-box input, .filter-box select {
            width: 100%;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 9px 10px;
            font-size: 13px;
            background: #fff;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }
        .card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 12px;
        }
        .card .label { font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
        .card .value { font-size: 20px; font-weight: 700; color: #111827; }
        .panel {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            margin-bottom: 12px;
            overflow: hidden;
        }
        .panel-head {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
            background: #f8fafc;
            font-weight: 700;
            font-size: 14px;
        }
        details.panel > summary.panel-head {
            list-style: none;
            cursor: pointer;
            border-bottom: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        details.panel > summary.panel-head::-webkit-details-marker {
            display: none;
        }
        .panel-toggle {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
        }
        details.panel .panel-body {
            border-top: 1px solid var(--border-color);
            animation: panelFade 180ms ease;
        }
        @keyframes panelFade {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .table-wrap { overflow: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 940px; }
        .table th, .table td {
            padding: 9px 10px;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
            font-size: 12px;
        }
        .table th { background: #fcfcfd; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); font-size: 11px; }
        .table td.num, .table th.num { text-align: right; white-space: nowrap; }
        .sortable-th {
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }
        .sort-icon {
            display: inline-block;
            margin-left: 6px;
            color: #94a3b8;
            font-size: 10px;
            width: 10px;
            text-align: center;
        }
        .badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
            display: inline-block;
        }
        .btn-refresh-icon {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 8px;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            vertical-align: middle;
            margin-left: 6px;
        }
        .alert-save {
            background: #ecfdf5;
            border: 1px solid #10b981;
            color: #065f46;
            border-radius: 12px;
            padding: 10px 12px;
            margin-bottom: 10px;
            font-size: 13px;
            font-weight: 600;
        }
        .tfoot-total td {
            background: #fafafa;
            font-weight: 700;
            border-top: 2px solid #e5e7eb;
        }
        .badge.pending { background: #ffedd5; color: #9a3412; border: 1px solid #fdba74; }
        .badge.paid { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        @media (max-width: 1300px) {
            .filter-box { grid-template-columns: 1fr 1fr 1fr auto; }
        }
        @media (max-width: 1150px) {
            .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filter-box { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 760px) {
            .cards { grid-template-columns: 1fr; }
            .filter-box { grid-template-columns: 1fr; }
            .report-page { padding: 16px; }
        }
        @media print {
            .side-nav, .filter-box, .section-header .text-muted { display: none !important; }
            .report-page { padding: 0 !important; }
            .cards, .panel { break-inside: avoid; }
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
            <li><a href="price_variants.php" title="Varian Harga"><i data-feather="tag"></i></a></li>
            <li class="active"><a href="laporan.php" title="Laporan"><i data-feather="bar-chart-2"></i></a></li>
            <li><a href="settings.php" title="Pengaturan"><i data-feather="settings"></i></a></li>
            <li><a href="logout.php" title="Logout"><i data-feather="log-out"></i></a></li>
        </ul>
    </nav>

    <main class="report-page">
        <header class="section-header" style="margin-bottom:10px;">
            <div>
                <h1 style="font-size:26px;margin-bottom:4px;">Laporan</h1>
                <p class="text-muted">Laporan transaksi detail, rekap harian, dan laba berdasarkan harga beli produk.</p>
            </div>
        </header>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'unit_refresh_done'): ?>
            <div class="alert-save">
                Refresh satuan transaksi selesai. Data ter-update: <?= number_format((int)($_GET['updated'] ?? 0), 0, ',', '.') ?> baris item.
            </div>
        <?php endif; ?>

        <form class="filter-box" method="GET">
            <div>
                <label for="start_date">Tanggal Mulai</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div>
                <label for="end_date">Tanggal Akhir</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div>
                <label for="status">Status Transaksi</label>
                <select id="status" name="status">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua</option>
                    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Sudah Bayar</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Belum Bayar</option>
                </select>
            </div>
            <button class="btn-primary" type="submit" style="height:40px;">Terapkan</button>
            <button class="btn-secondary" type="button" style="height:40px;" onclick="window.print()">Cetak</button>
            <a class="btn-secondary" style="height:40px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;" href="?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&status=<?= urlencode($status) ?>&export=excel">Save as Excel</a>
            <a class="btn-secondary" style="height:40px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;" href="?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&status=<?= urlencode($status) ?>&export=csv">Save as CSV</a>
        </form>

        <div class="cards">
            <div class="card">
                <div class="label">Jumlah Transaksi</div>
                <div class="value"><?= number_format((float)($summary['total_trx'] ?? 0), 0, ',', '.') ?></div>
            </div>
            <div class="card">
                <div class="label">Omzet Total</div>
                <div class="value"><?= rupiah($summary['omzet_total'] ?? 0) ?></div>
            </div>
            <div class="card">
                <div class="label">Omzet Paid</div>
                <div class="value"><?= rupiah($summary['omzet_paid'] ?? 0) ?></div>
            </div>
            <div class="card">
                <div class="label">Laba Kotor (Paid)</div>
                <div class="value"><?= rupiah($labaSummary['laba_kotor'] ?? 0) ?></div>
            </div>
            <?php if (empty($baseQtyByUnit)): ?>
                <div class="card">
                    <div class="label">Total Base Qty</div>
                    <div class="value">0,00 unit</div>
                </div>
            <?php else: ?>
                <?php foreach ($baseQtyByUnit as $unitSymbol => $qtyVal): ?>
                    <div class="card">
                        <div class="label">Total Base Qty (<?= htmlspecialchars($unitSymbol) ?>)</div>
                        <div class="value"><?= number_format((float)$qtyVal, 2, ',', '.') ?> <?= htmlspecialchars($unitSymbol) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <details class="panel" open>
            <summary class="panel-head">
                <span>Laporan Transaksi Detail</span>
                <span class="panel-toggle">Klik untuk collapse/expand</span>
            </summary>
            <div class="panel-body">
            <div class="table-wrap">
                <table class="table" style="min-width:1240px;">
                    <thead>
                    <tr>
                        <th class="sortable-th" data-sort-type="date">Waktu <span class="sort-icon">↕</span></th>
                        <th class="sortable-th" data-sort-type="text">Invoice <span class="sort-icon">↕</span></th>
                        <th class="sortable-th" data-sort-type="text">Pelanggan <span class="sort-icon">↕</span></th>
                        <th class="sortable-th" data-sort-type="text">Status <span class="sort-icon">↕</span></th>
                        <th class="sortable-th" data-sort-type="text">Produk <span class="sort-icon">↕</span></th>
                        <th class="sortable-th" data-sort-type="text">Varian <span class="sort-icon">↕</span></th>
                        <th class="num sortable-th" data-sort-type="num">Qty <span class="sort-icon">↕</span></th>
                        <th class="num sortable-th" data-sort-type="text">
                            Satuan
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="refresh_unit_snapshot">
                                <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                                <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                                <button type="submit" class="btn-refresh-icon" title="Refresh satuan dari master produk">
                                    <i data-feather="refresh-cw" style="width:13px;height:13px;"></i>
                                </button>
                            </form>
                            <span class="sort-icon">↕</span>
                        </th>
                        <th class="num sortable-th" data-sort-type="num">Konversi Base <span class="sort-icon">↕</span></th>
                        <th class="num sortable-th" data-sort-type="num">Total Base Qty <span class="sort-icon">↕</span></th>
                        <th class="num sortable-th" data-sort-type="num">Harga Jual <span class="sort-icon">↕</span></th>
                        <th class="num sortable-th" data-sort-type="num">Subtotal <span class="sort-icon">↕</span></th>
                        <th class="num sortable-th" data-sort-type="num">Harga Beli <span class="sort-icon">↕</span></th>
                        <th class="num sortable-th" data-sort-type="num">Modal <span class="sort-icon">↕</span></th>
                        <th class="num sortable-th" data-sort-type="num">Laba <span class="sort-icon">↕</span></th>
                    </tr>
                    </thead>
                    <tbody id="detailBody">
                    <?php if (empty($detailRows)): ?>
                        <tr><td colspan="15" style="text-align:center;color:var(--text-muted);padding:16px;">Tidak ada data transaksi.</td></tr>
                    <?php else: ?>
                        <?php foreach ($detailRows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$row['transaction_at']) ?></td>
                                <td><?= htmlspecialchars((string)$row['invoice_no']) ?></td>
                                <td><?= htmlspecialchars((string)$row['customer_name']) ?></td>
                                <td><span class="badge <?= $row['status'] === 'paid' ? 'paid' : 'pending' ?>"><?= $row['status'] === 'paid' ? 'Paid' : 'Pending' ?></span></td>
                                <td><?= htmlspecialchars((string)$row['product_name']) ?></td>
                                <td><?= htmlspecialchars((string)$row['variant_name']) ?></td>
                                <td class="num"><?= number_format((float)$row['qty'], 0, ',', '.') ?></td>
                                <td class="num"><?= htmlspecialchars((string)($row['unit_symbol_snapshot'] ?? '')) ?></td>
                                <td class="num"><?= number_format((float)($row['unit_base_qty_snapshot'] ?? 0), 2, ',', '.') ?></td>
                                <td class="num"><?= number_format((float)($row['base_qty_total'] ?? 0), 2, ',', '.') ?></td>
                                <td class="num"><?= rupiah($row['price']) ?></td>
                                <td class="num"><?= rupiah($row['subtotal']) ?></td>
                                <td class="num"><?= rupiah($row['harga_beli']) ?></td>
                                <td class="num"><?= rupiah($row['modal_total']) ?></td>
                                <td class="num"><?= rupiah($row['laba']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </details>

        <details class="panel">
            <summary class="panel-head">
                <span>Laporan Transaksi Rekap (Harian)</span>
                <span class="panel-toggle">Klik untuk collapse/expand</span>
            </summary>
            <div class="panel-body">
            <div class="table-wrap">
                <table class="table" style="min-width:680px;">
                    <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th class="num">Jumlah Transaksi</th>
                        <th class="num">Omzet Total</th>
                        <th class="num">Omzet Paid</th>
                        <th class="num">Omzet Pending</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rekapRows)): ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:16px;">Tidak ada data rekap.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rekapRows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$row['tx_date']) ?></td>
                                <td class="num"><?= number_format((float)$row['trx_count'], 0, ',', '.') ?></td>
                                <td class="num"><?= rupiah($row['omzet_total']) ?></td>
                                <td class="num"><?= rupiah($row['omzet_paid']) ?></td>
                                <td class="num"><?= rupiah($row['omzet_pending']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </details>

        <details class="panel">
            <summary class="panel-head">
                <span>Laporan Transaksi Laba (Per Produk/Varian)</span>
                <span class="panel-toggle">Klik untuk collapse/expand</span>
            </summary>
            <div class="panel-body">
            <div class="table-wrap">
                <table class="table" style="min-width:980px;">
                    <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Varian</th>
                        <th class="num">Qty Terjual</th>
                        <th class="num">Total Base Qty</th>
                        <th class="num">Satuan</th>
                        <th class="num">Harga Jual Rata-rata</th>
                        <th class="num">Harga Beli</th>
                        <th class="num">Omzet</th>
                        <th class="num">Modal</th>
                        <th class="num">Laba</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($labaRows)): ?>
                        <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:16px;">Tidak ada data laba.</td></tr>
                    <?php else: ?>
                        <?php foreach ($labaRows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$row['product_name']) ?></td>
                                <td><?= htmlspecialchars((string)$row['variant_name']) ?></td>
                                <td class="num"><?= number_format((float)$row['qty_total'], 0, ',', '.') ?></td>
                                <td class="num"><?= number_format((float)($row['base_qty_total'] ?? 0), 2, ',', '.') ?></td>
                                <td class="num"><?= htmlspecialchars((string)($row['unit_symbol_snapshot'] ?? '')) ?></td>
                                <td class="num"><?= rupiah($row['harga_jual_avg']) ?></td>
                                <td class="num"><?= rupiah($row['harga_beli']) ?></td>
                                <td class="num"><?= rupiah($row['omzet_total']) ?></td>
                                <td class="num"><?= rupiah($row['modal_total']) ?></td>
                                <td class="num"><?= rupiah($row['laba_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <?php if (!empty($labaRows)): ?>
                    <tfoot>
                        <tr class="tfoot-total">
                            <td colspan="2">Total</td>
                            <td class="num"><?= number_format($labaQtyTotal, 0, ',', '.') ?></td>
                            <td class="num"><?= number_format($labaBaseQtyTotal, 2, ',', '.') ?></td>
                            <td class="num">-</td>
                            <td class="num">-</td>
                            <td class="num"><?= rupiah($labaOmzetTotal) ?></td>
                            <td class="num"><?= rupiah($labaModalTotal) ?></td>
                            <td class="num"><?= rupiah($labaGrandTotal) ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
            </div>
        </details>
    </main>
</div>
<script>
function parseNum(text) {
    const clean = String(text || '')
        .replace(/[^0-9,.-]/g, '')
        .replace(/\./g, '')
        .replace(',', '.');
    const val = parseFloat(clean);
    return Number.isNaN(val) ? 0 : val;
}

function parseDate(text) {
    const v = String(text || '').trim().replace(' ', 'T');
    const t = Date.parse(v);
    return Number.isNaN(t) ? 0 : t;
}

function initDetailSorting() {
    const table = document.querySelector('.panel .table');
    if (!table) return;
    const tbody = document.getElementById('detailBody');
    if (!tbody) return;
    const headers = table.querySelectorAll('thead th.sortable-th');
    let activeIndex = -1;
    let activeDir = 'asc';

    headers.forEach((th, idx) => {
        th.addEventListener('click', () => {
            const sortType = th.getAttribute('data-sort-type') || 'text';
            const allRows = Array.from(tbody.querySelectorAll('tr'));
            const dataRows = allRows.filter((r) => !r.classList.contains('summary-row'));
            const summaryRows = allRows.filter((r) => r.classList.contains('summary-row'));

            if (activeIndex === idx) {
                activeDir = activeDir === 'asc' ? 'desc' : 'asc';
            } else {
                activeIndex = idx;
                activeDir = 'asc';
            }

            dataRows.sort((a, b) => {
                const aTxt = (a.children[idx]?.innerText || '').trim();
                const bTxt = (b.children[idx]?.innerText || '').trim();
                let av = aTxt;
                let bv = bTxt;
                if (sortType === 'num') {
                    av = parseNum(aTxt);
                    bv = parseNum(bTxt);
                } else if (sortType === 'date') {
                    av = parseDate(aTxt);
                    bv = parseDate(bTxt);
                } else {
                    av = aTxt.toLowerCase();
                    bv = bTxt.toLowerCase();
                }
                if (av < bv) return activeDir === 'asc' ? -1 : 1;
                if (av > bv) return activeDir === 'asc' ? 1 : -1;
                return 0;
            });

            headers.forEach((h) => {
                const icon = h.querySelector('.sort-icon');
                if (icon) icon.textContent = '↕';
            });
            const activeIcon = th.querySelector('.sort-icon');
            if (activeIcon) activeIcon.textContent = activeDir === 'asc' ? '▲' : '▼';

            dataRows.forEach((r) => tbody.appendChild(r));
            summaryRows.forEach((r) => tbody.appendChild(r));
        });
    });
}

feather.replace();
initDetailSorting();
</script>
</body>
</html>
