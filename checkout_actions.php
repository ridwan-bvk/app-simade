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
    $stmt = $pdo->prepare(
        'INSERT INTO sales_transaction_items
            (transaction_id, product_id, product_code, product_name, variant_id, variant_name, price, qty, subtotal)
         VALUES
            (:transaction_id, :product_id, :product_code, :product_name, :variant_id, :variant_name, :price, :qty, :subtotal)'
    );
    foreach ($items as $item) {
        $qty = (int)($item['quantity'] ?? 0);
        if ($qty <= 0) continue;
        $price = (float)($item['price'] ?? 0);
        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':product_id' => isset($item['id']) ? (int)$item['id'] : null,
            ':product_code' => $item['code'] ?? null,
            ':product_name' => (string)($item['name'] ?? ''),
            ':variant_id' => ($item['variant_id'] ?? '') !== '' ? (int)$item['variant_id'] : null,
            ':variant_name' => ($item['variant_name'] ?? '') !== '' ? (string)$item['variant_name'] : null,
            ':price' => $price,
            ':qty' => $qty,
            ':subtotal' => $price * $qty,
        ]);
    }
}

ensure_transaction_tables($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list_today';
    if ($action === 'list_today') {
        $status = $_GET['status'] ?? 'all';
        $search = trim((string)($_GET['search'] ?? ''));
        $date = trim((string)($_GET['date'] ?? date('Y-m-d')));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = (int)($_GET['page_size'] ?? 20);
        if ($pageSize < 5) $pageSize = 5;
        if ($pageSize > 100) $pageSize = 100;
        $offset = ($page - 1) * $pageSize;

        $where = ['DATE(transaction_at) = :tx_date'];
        $params = [':tx_date' => $date];

        if (in_array($status, ['pending', 'paid'], true)) {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }
        if ($search !== '') {
            $where[] = '(invoice_no LIKE :search OR customer_name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM sales_transactions WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $pageSize));

        $listSql = "SELECT id, invoice_no, status, is_printed, printed_at, transaction_at, customer_name, total, paid_amount
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
                     paid_amount = 0, change_amount = 0, status = "pending", transaction_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                ':customer_name' => $customerName !== '' ? $customerName : null,
                ':subtotal' => $subtotal,
                ':total' => $total,
                ':id' => $draftId,
            ]);
            $transactionId = $draftId;
        } else {
            $invoiceNo = 'DRF-' . date('YmdHis') . '-' . random_int(100, 999);
            $stmt = $pdo->prepare(
                'INSERT INTO sales_transactions
                    (invoice_no, status, is_printed, printed_at, transaction_at, customer_name, subtotal, total, paid_amount, change_amount)
                 VALUES
                    (:invoice_no, "pending", 0, NULL, NOW(), :customer_name, :subtotal, :total, 0, 0)'
            );
            $stmt->execute([
                ':invoice_no' => $invoiceNo,
                ':customer_name' => $customerName !== '' ? $customerName : null,
                ':subtotal' => $subtotal,
                ':total' => $total,
            ]);
            $transactionId = (int)$pdo->lastInsertId();
        }

        save_items($pdo, $transactionId, $items);
        $pdo->commit();
        echo json_encode(['success' => true, 'transaction_id' => $transactionId, 'status' => 'pending']);
        exit;
    }

    if ($action === 'pay') {
        if ($draftId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE sales_transactions
                 SET status = "paid", customer_name = :customer_name, subtotal = :subtotal, total = :total,
                     paid_amount = :paid, change_amount = :change, transaction_at = NOW(),
                     invoice_no = CASE WHEN invoice_no LIKE "DRF-%" THEN :new_invoice ELSE invoice_no END
                 WHERE id = :id'
            );
            $stmt->execute([
                ':customer_name' => $customerName !== '' ? $customerName : null,
                ':subtotal' => $subtotal,
                ':total' => $total,
                ':paid' => $paid,
                ':change' => $change,
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
                    (invoice_no, status, is_printed, printed_at, transaction_at, customer_name, subtotal, total, paid_amount, change_amount)
                 VALUES
                    (:invoice_no, "paid", 0, NULL, NOW(), :customer_name, :subtotal, :total, :paid, :change)'
            );
            $stmt->execute([
                ':invoice_no' => $invoiceNo,
                ':customer_name' => $customerName !== '' ? $customerName : null,
                ':subtotal' => $subtotal,
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
