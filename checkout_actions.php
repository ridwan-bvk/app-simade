<?php
require_once 'config/database.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = get_db();
ensure_auth_tables($pdo);
require_api_login();

function ensure_transaction_tables(PDO $pdo): void
{
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
            discount DECIMAL(15,2) NOT NULL DEFAULT 0,
            downpayment DECIMAL(15,2) NOT NULL DEFAULT 0,
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

    $hasStatus = $pdo->query("SHOW COLUMNS FROM sales_transactions LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasStatus) {
        $pdo->exec("ALTER TABLE sales_transactions ADD COLUMN status ENUM('pending','paid') NOT NULL DEFAULT 'pending' AFTER invoice_no");
    }
    $hasPrinted = $pdo->query("SHOW COLUMNS FROM sales_transactions LIKE 'is_printed'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasPrinted) {
        $pdo->exec("ALTER TABLE sales_transactions ADD COLUMN is_printed TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
    }
    $hasPrintedAt = $pdo->query("SHOW COLUMNS FROM sales_transactions LIKE 'printed_at'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasPrintedAt) {
        $pdo->exec("ALTER TABLE sales_transactions ADD COLUMN printed_at DATETIME NULL AFTER is_printed");
    }
    $hasDiscount = $pdo->query("SHOW COLUMNS FROM sales_transactions LIKE 'discount'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasDiscount) {
        $pdo->exec("ALTER TABLE sales_transactions ADD COLUMN discount DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER subtotal");
    }
    $hasDownpayment = $pdo->query("SHOW COLUMNS FROM sales_transactions LIKE 'downpayment'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasDownpayment) {
        $pdo->exec("ALTER TABLE sales_transactions ADD COLUMN downpayment DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER discount");
    }
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
}

function json_error(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function save_items(PDO $pdo, int $transactionId, array $items): void
{
    $pdo->prepare('DELETE FROM sales_transaction_items WHERE transaction_id = :id')->execute([':id' => $transactionId]);

    $productIds = [];
    foreach ($items as $item) {
        $pid = isset($item['id']) ? (int)$item['id'] : 0;
        if ($pid > 0) {
            $productIds[] = $pid;
        }
    }
    $productIds = array_values(array_unique($productIds));
    $productUnitMap = [];
    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $unitStmt = $pdo->prepare(
            "SELECT p.id, p.unit_id, p.unit_base_qty, mu.unit_symbol
             FROM products p
             LEFT JOIN master_units mu ON mu.id = p.unit_id
             WHERE p.id IN ({$placeholders})"
        );
        $unitStmt->execute($productIds);
        $rows = $unitStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $pid = (int)$row['id'];
            $productUnitMap[$pid] = [
                'unit_id' => $row['unit_id'] !== null ? (int)$row['unit_id'] : null,
                'unit_symbol' => $row['unit_symbol'] !== null ? (string)$row['unit_symbol'] : null,
                'unit_base_qty' => max(0.0001, (float)($row['unit_base_qty'] ?? 1)),
            ];
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sales_transaction_items
            (transaction_id, product_id, product_code, product_name, variant_id, variant_name, price, qty, unit_id_snapshot, unit_symbol_snapshot, unit_base_qty_snapshot, base_qty_total, subtotal)
         VALUES
            (:transaction_id, :product_id, :product_code, :product_name, :variant_id, :variant_name, :price, :qty, :unit_id_snapshot, :unit_symbol_snapshot, :unit_base_qty_snapshot, :base_qty_total, :subtotal)'
    );
    foreach ($items as $item) {
        $qty = (int)($item['quantity'] ?? 0);
        if ($qty <= 0) continue;
        $price = (float)($item['price'] ?? 0);
        $productId = isset($item['id']) ? (int)$item['id'] : 0;
        $unitInfo = $productUnitMap[$productId] ?? [
            'unit_id' => null,
            'unit_symbol' => null,
            'unit_base_qty' => 1.0,
        ];
        $unitBaseQty = max(0.0001, (float)$unitInfo['unit_base_qty']);
        $baseQtyTotal = $qty * $unitBaseQty;
        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':product_id' => $productId > 0 ? $productId : null,
            ':product_code' => $item['code'] ?? null,
            ':product_name' => (string)($item['name'] ?? ''),
            ':variant_id' => ($item['variant_id'] ?? '') !== '' ? (int)$item['variant_id'] : null,
            ':variant_name' => ($item['variant_name'] ?? '') !== '' ? (string)$item['variant_name'] : null,
            ':price' => $price,
            ':qty' => $qty,
            ':unit_id_snapshot' => $unitInfo['unit_id'],
            ':unit_symbol_snapshot' => $unitInfo['unit_symbol'],
            ':unit_base_qty_snapshot' => $unitBaseQty,
            ':base_qty_total' => $baseQtyTotal,
            ':subtotal' => $price * $qty,
        ]);
    }
}

ensure_transaction_tables($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list_today';
    if ($action === 'list_today') {
        try {
            $status = $_GET['status'] ?? 'all';
            $search = trim((string)($_GET['search'] ?? ''));
            $searchLower = function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search);
            $legacyDate = trim((string)($_GET['date'] ?? ''));
            $startDate = trim((string)($_GET['start_date'] ?? ''));
            $endDate = trim((string)($_GET['end_date'] ?? ''));
            if ($startDate === '' && $endDate === '' && $legacyDate !== '') {
                $startDate = $legacyDate;
                $endDate = $legacyDate;
            }
            $page = max(1, (int)($_GET['page'] ?? 1));
            $pageSize = (int)($_GET['page_size'] ?? 20);
            if ($pageSize < 5) $pageSize = 5;
            if ($pageSize > 100) $pageSize = 100;
            $where = [];
            $params = [];

            if ($startDate !== '') {
                $startObj = DateTime::createFromFormat('Y-m-d', $startDate);
                if (!$startObj || $startObj->format('Y-m-d') !== $startDate) {
                    json_error(422, 'Format tanggal awal tidak valid.');
                }
            }
            if ($endDate !== '') {
                $endObj = DateTime::createFromFormat('Y-m-d', $endDate);
                if (!$endObj || $endObj->format('Y-m-d') !== $endDate) {
                    json_error(422, 'Format tanggal akhir tidak valid.');
                }
            }
            if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
                json_error(422, 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
            }

            if ($startDate !== '' && $endDate !== '') {
                $where[] = 'DATE(transaction_at) BETWEEN :start_date AND :end_date';
                $params[':start_date'] = $startDate;
                $params[':end_date'] = $endDate;
            } elseif ($startDate !== '') {
                $where[] = 'DATE(transaction_at) >= :start_date';
                $params[':start_date'] = $startDate;
            } elseif ($endDate !== '') {
                $where[] = 'DATE(transaction_at) <= :end_date';
                $params[':end_date'] = $endDate;
            }

            if (in_array($status, ['pending', 'paid'], true)) {
                $where[] = 'status = :status';
                $params[':status'] = $status;
            }
            if ($search !== '') {
                $where[] = "(LOWER(invoice_no) LIKE CONCAT('%', LOWER(:search_invoice), '%') OR LOWER(COALESCE(customer_name, '')) LIKE CONCAT('%', LOWER(:search_customer), '%'))";
                $params[':search_invoice'] = $searchLower;
                $params[':search_customer'] = $searchLower;
            }
            $whereSql = empty($where) ? '1=1' : implode(' AND ', $where);

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM sales_transactions WHERE {$whereSql}");
            $countStmt->execute($params);
            $totalRows = (int)$countStmt->fetchColumn();
            $totalPages = max(1, (int)ceil($totalRows / $pageSize));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $pageSize;

        $listSql = "SELECT id, invoice_no, status, is_printed, printed_at, transaction_at, customer_name, subtotal, discount, downpayment, total, paid_amount
                    FROM sales_transactions
                    WHERE {$whereSql}
                    ORDER BY id DESC
                    LIMIT {$pageSize} OFFSET {$offset}";
            $rowsStmt = $pdo->prepare($listSql);
            $rowsStmt->execute($params);
            $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'transactions' => $rows,
                'pagination' => [
                    'page' => $page,
                    'page_size' => $pageSize,
                    'total_rows' => $totalRows,
                    'total_pages' => $totalPages,
                ],
            ]);
            exit;
        } catch (Throwable $e) {
            error_log('[TX_LIST_ERROR] ' . $e->getMessage());
            json_error(500, 'Gagal memuat daftar transaksi.');
        }
    }
    if ($action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_error(422, 'ID transaksi tidak valid.');

        $txStmt = $pdo->prepare('SELECT * FROM sales_transactions WHERE id = :id');
        $txStmt->execute([':id' => $id]);
        $tx = $txStmt->fetch(PDO::FETCH_ASSOC);
        if (!$tx) json_error(404, 'Transaksi tidak ditemukan.');

        $itemsStmt = $pdo->prepare('SELECT * FROM sales_transaction_items WHERE transaction_id = :id ORDER BY id ASC');
        $itemsStmt->execute([':id' => $id]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'transaction' => $tx, 'items' => $items]);
        exit;
    }

    json_error(400, 'Action GET tidak dikenal.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, 'Method tidak diizinkan.');
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) json_error(400, 'Payload tidak valid.');

$action = $payload['action'] ?? 'pay';
$items = $payload['items'] ?? [];
if (in_array($action, ['save_draft', 'pay'], true)) {
    if (!is_array($items) || empty($items)) {
        json_error(422, 'Item transaksi kosong.');
    }
}

$customerName = trim((string)($payload['customer_name'] ?? ''));
$subtotal = (float)($payload['subtotal'] ?? 0);
$total = (float)($payload['total'] ?? 0);
$paid = (float)($payload['paid_amount'] ?? 0);
$change = (float)($payload['change_amount'] ?? 0);
$draftId = (int)($payload['draft_id'] ?? 0);

try {
    $pdo->beginTransaction();

    if ($action === 'save_draft') {
        if ($draftId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE sales_transactions
                 SET customer_name = :customer_name, subtotal = :subtotal, total = :total,
                    paid_amount = :paid, change_amount = :change, discount = :discount, downpayment = :downpayment, status = "pending", transaction_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                ':customer_name' => $customerName !== '' ? $customerName : null,
                ':subtotal' => $subtotal,
                ':total' => $total,
                ':paid' => $paid,
                ':change' => $change,
                ':discount' => (float)($payload['discount'] ?? 0),
                ':downpayment' => (float)($payload['downpayment'] ?? 0),
                ':id' => $draftId,
            ]);
            $transactionId = $draftId;
        } else {
            $invoiceNo = 'DRF-' . date('YmdHis') . '-' . random_int(100, 999);
            $stmt = $pdo->prepare(
                'INSERT INTO sales_transactions
                    (invoice_no, status, is_printed, printed_at, transaction_at, customer_name, subtotal, discount, downpayment, total, paid_amount, change_amount)
                 VALUES
                    (:invoice_no, "pending", 0, NULL, NOW(), :customer_name, :subtotal, :discount, :downpayment, :total, :paid, :change)'
            );
            $stmt->execute([
                ':invoice_no' => $invoiceNo,
                ':customer_name' => $customerName !== '' ? $customerName : null,
                ':subtotal' => $subtotal,
                ':discount' => (float)($payload['discount'] ?? 0),
                ':downpayment' => (float)($payload['downpayment'] ?? 0),
                ':total' => $total,
                ':paid' => $paid,
                ':change' => $change,
            ]);
            $transactionId = (int)$pdo->lastInsertId();
        }

        save_items($pdo, $transactionId, $items);
        $invStmt = $pdo->prepare('SELECT invoice_no FROM sales_transactions WHERE id = :id');
        $invStmt->execute([':id' => $transactionId]);
        $invoiceNo = (string)$invStmt->fetchColumn();
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'transaction_id' => $transactionId,
            'invoice_no' => $invoiceNo,
            'status' => 'pending',
        ]);
        exit;
    }

    if ($action === 'pay') {
        if ($draftId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE sales_transactions
                 SET status = "paid", customer_name = :customer_name, subtotal = :subtotal, total = :total,
                    paid_amount = :paid, change_amount = :change, discount = :discount, downpayment = :downpayment, transaction_at = NOW(),
                     invoice_no = CASE WHEN invoice_no LIKE "DRF-%" THEN :new_invoice ELSE invoice_no END
                 WHERE id = :id'
            );
            $stmt->execute([
                ':customer_name' => $customerName !== '' ? $customerName : null,
                ':subtotal' => $subtotal,
                ':total' => $total,
                ':paid' => $paid,
                ':change' => $change,
                ':discount' => (float)($payload['discount'] ?? 0),
                ':downpayment' => (float)($payload['downpayment'] ?? 0),
                ':new_invoice' => 'TRX-' . date('YmdHis') . '-' . random_int(100, 999),
                ':id' => $draftId,
            ]);
            $transactionId = $draftId;
            save_items($pdo, $transactionId, $items);
            $invStmt = $pdo->prepare('SELECT invoice_no FROM sales_transactions WHERE id = :id');
            $invStmt->execute([':id' => $transactionId]);
            $invoiceNo = (string)$invStmt->fetchColumn();
        } else {
            $invoiceNo = 'TRX-' . date('YmdHis') . '-' . random_int(100, 999);
            $stmt = $pdo->prepare(
                'INSERT INTO sales_transactions
                    (invoice_no, status, is_printed, printed_at, transaction_at, customer_name, subtotal, discount, downpayment, total, paid_amount, change_amount)
                 VALUES
                    (:invoice_no, "paid", 0, NULL, NOW(), :customer_name, :subtotal, :discount, :downpayment, :total, :paid, :change)'
            );
            $stmt->execute([
                ':invoice_no' => $invoiceNo,
                ':customer_name' => $customerName !== '' ? $customerName : null,
                ':subtotal' => $subtotal,
                ':discount' => (float)($payload['discount'] ?? 0),
                ':downpayment' => (float)($payload['downpayment'] ?? 0),
                ':total' => $total,
                ':paid' => $paid,
                ':change' => $change,
            ]);
            $transactionId = (int)$pdo->lastInsertId();
            save_items($pdo, $transactionId, $items);
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'invoice_no' => $invoiceNo,
            'transaction_id' => $transactionId,
            'status' => 'paid',
        ]);
        exit;
    }

    if ($action === 'mark_printed') {
        $id = (int)($payload['transaction_id'] ?? 0);
        if ($id <= 0) json_error(422, 'ID transaksi tidak valid.');

        $stmt = $pdo->prepare(
            'UPDATE sales_transactions
             SET is_printed = 1, printed_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_pending') {
        $id = (int)($payload['transaction_id'] ?? 0);
        if ($id <= 0) json_error(422, 'ID transaksi tidak valid.');

        $check = $pdo->prepare('SELECT status, is_printed FROM sales_transactions WHERE id = :id');
        $check->execute([':id' => $id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) json_error(404, 'Transaksi tidak ditemukan.');

        if ($row['status'] !== 'pending' || (int)$row['is_printed'] === 1) {
            json_error(422, 'Transaksi hanya bisa dihapus jika status belum bayar dan belum cetak.');
        }

        $del = $pdo->prepare('DELETE FROM sales_transactions WHERE id = :id');
        $del->execute([':id' => $id]);
        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    json_error(400, 'Action POST tidak dikenal.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_error(500, 'Gagal memproses transaksi: ' . $e->getMessage());
}
