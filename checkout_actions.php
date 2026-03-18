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
            payment_method ENUM("cash","transfer") NOT NULL DEFAULT "cash",
            payment_note VARCHAR(255) NULL,
            is_non_cash TINYINT(1) NOT NULL DEFAULT 0,
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
    $hasPaymentMethod = $pdo->query("SHOW COLUMNS FROM sales_transactions LIKE 'payment_method'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasPaymentMethod) {
        $pdo->exec("ALTER TABLE sales_transactions ADD COLUMN payment_method ENUM('cash','transfer') NOT NULL DEFAULT 'cash' AFTER change_amount");
    }
    $hasPaymentNote = $pdo->query("SHOW COLUMNS FROM sales_transactions LIKE 'payment_note'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasPaymentNote) {
        $pdo->exec("ALTER TABLE sales_transactions ADD COLUMN payment_note VARCHAR(255) NULL AFTER payment_method");
    }
    $hasIsNonCash = $pdo->query("SHOW COLUMNS FROM sales_transactions LIKE 'is_non_cash'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasIsNonCash) {
        $pdo->exec("ALTER TABLE sales_transactions ADD COLUMN is_non_cash TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_note");
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

function get_current_auth_identifiers(): array
{
    $identifiers = [
        'user_id' => null,
        'username' => null,
        'email' => null,
    ];

    if (session_status() === PHP_SESSION_ACTIVE || session_status() === PHP_SESSION_NONE) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $sessionUserIdKeys = ['user_id', 'auth_user_id', 'login_user_id', 'id'];
        foreach ($sessionUserIdKeys as $key) {
            if (isset($_SESSION[$key]) && (int)$_SESSION[$key] > 0) {
                $identifiers['user_id'] = (int)$_SESSION[$key];
                break;
            }
        }
        $sessionUsernameKeys = ['username', 'user_username', 'login_username', 'name'];
        foreach ($sessionUsernameKeys as $key) {
            if (!empty($_SESSION[$key])) {
                $identifiers['username'] = trim((string)$_SESSION[$key]);
                break;
            }
        }
        $sessionEmailKeys = ['email', 'user_email', 'login_email'];
        foreach ($sessionEmailKeys as $key) {
            if (!empty($_SESSION[$key])) {
                $identifiers['email'] = trim((string)$_SESSION[$key]);
                break;
            }
        }
    }

    $functionCandidates = ['current_user', 'auth_user', 'get_auth_user'];
    foreach ($functionCandidates as $fn) {
        if (function_exists($fn)) {
            try {
                $user = $fn();
                if (is_array($user)) {
                    if ($identifiers['user_id'] === null) {
                        $identifiers['user_id'] = isset($user['id']) ? (int)$user['id'] : (isset($user['user_id']) ? (int)$user['user_id'] : null);
                    }
                    if ($identifiers['username'] === null) {
                        $identifiers['username'] = isset($user['username']) ? trim((string)$user['username']) : (isset($user['name']) ? trim((string)$user['name']) : null);
                    }
                    if ($identifiers['email'] === null && isset($user['email'])) {
                        $identifiers['email'] = trim((string)$user['email']);
                    }
                }
            } catch (Throwable $e) {
                // ignore optional auth helper mismatch
            }
        }
    }

    return $identifiers;
}

function detect_auth_tables(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT TABLE_NAME, COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND COLUMN_NAME IN ('id', 'user_id', 'username', 'email', 'password_hash', 'password')"
    );
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    if (!$rows) {
        return [];
    }

    $tables = [];
    foreach ($rows as $row) {
        $table = (string)$row['TABLE_NAME'];
        $column = (string)$row['COLUMN_NAME'];
        if (!isset($tables[$table])) {
            $tables[$table] = [];
        }
        $tables[$table][$column] = true;
    }

    $candidates = [];
    foreach ($tables as $table => $columns) {
        if (!isset($columns['password_hash']) && !isset($columns['password'])) {
            continue;
        }
        $score = 0;
        if (isset($columns['id']) || isset($columns['user_id'])) $score += 2;
        if (isset($columns['username'])) $score += 2;
        if (isset($columns['email'])) $score += 1;
        if (stripos($table, 'user') !== false) $score += 2;
        if (stripos($table, 'auth') !== false) $score += 1;
        $candidates[] = ['table' => $table, 'columns' => array_keys($columns), 'score' => $score];
    }

    usort($candidates, static function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    return $candidates;
}

function verify_current_login_password(PDO $pdo, string $password): bool
{
    $password = trim($password);
    if ($password === '') {
        return false;
    }

    $identifiers = get_current_auth_identifiers();
    $tables = detect_auth_tables($pdo);
    if (!$tables) {
        return false;
    }

    foreach ($tables as $candidate) {
        $table = $candidate['table'];
        $columns = array_fill_keys($candidate['columns'], true);
        $passwordColumn = isset($columns['password_hash']) ? 'password_hash' : 'password';
        $idColumn = isset($columns['id']) ? 'id' : (isset($columns['user_id']) ? 'user_id' : null);

        if ($identifiers['user_id'] !== null && $idColumn !== null) {
            $stmt = $pdo->prepare("SELECT {$passwordColumn} AS password_value FROM {$table} WHERE {$idColumn} = :id LIMIT 1");
            $stmt->execute([':id' => $identifiers['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['password_value'])) {
                $storedPassword = (string)$row['password_value'];
                if (password_verify($password, $storedPassword) || hash_equals($storedPassword, $password)) {
                    return true;
                }
            }
        }

        if ($identifiers['username'] !== null && isset($columns['username'])) {
            $stmt = $pdo->prepare("SELECT {$passwordColumn} AS password_value FROM {$table} WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $identifiers['username']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['password_value'])) {
                $storedPassword = (string)$row['password_value'];
                if (password_verify($password, $storedPassword) || hash_equals($storedPassword, $password)) {
                    return true;
                }
            }
        }

        if ($identifiers['email'] !== null && isset($columns['email'])) {
            $stmt = $pdo->prepare("SELECT {$passwordColumn} AS password_value FROM {$table} WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $identifiers['email']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['password_value'])) {
                $storedPassword = (string)$row['password_value'];
                if (password_verify($password, $storedPassword) || hash_equals($storedPassword, $password)) {
                    return true;
                }
            }
        }
    }

    return false;
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

        $listSql = "SELECT id, invoice_no, status, is_printed, printed_at, transaction_at, customer_name, subtotal, discount, downpayment, total, paid_amount, change_amount, payment_method, payment_note, is_non_cash
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
$downpaymentPayload = (float)($payload['downpayment'] ?? 0);
$paymentMethod = strtolower(trim((string)($payload['payment_method'] ?? 'cash')));
$paymentMethod = $paymentMethod === 'transfer' ? 'transfer' : 'cash';
$paymentNote = trim((string)($payload['payment_note'] ?? ''));
$isNonCash = $paymentMethod === 'transfer' ? 1 : 0;

if (in_array($action, ['save_draft', 'pay'], true)) {
    if ($subtotal < 0 || $total < 0 || $paid < 0 || $change < 0) {
        json_error(422, 'Nominal transaksi tidak valid.');
    }
}
if ($action === 'pay' && $paid <= 0) {
    json_error(422, 'Nominal pembayaran wajib diisi.');
}
if ($action === 'pay') {
    $minimumPaid = max(0, $total - $downpaymentPayload);
    if ($paid < $minimumPaid) {
        json_error(422, 'Nominal pembayaran masih kurang dari total tagihan.');
    }
}

try {
    $pdo->beginTransaction();

    if ($action === 'save_draft') {
        if ($draftId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE sales_transactions
                 SET customer_name = :customer_name, subtotal = :subtotal, total = :total,
                    paid_amount = :paid, change_amount = :change, discount = :discount, downpayment = :downpayment,
                    payment_method = :payment_method, payment_note = :payment_note, is_non_cash = :is_non_cash,
                    status = "pending", transaction_at = NOW()
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
                ':payment_method' => $paymentMethod,
                ':payment_note' => $paymentNote !== '' ? $paymentNote : null,
                ':is_non_cash' => $isNonCash,
                ':id' => $draftId,
            ]);
            $transactionId = $draftId;
        } else {
            $invoiceNo = 'DRF-' . date('YmdHis') . '-' . random_int(100, 999);
            $stmt = $pdo->prepare(
                'INSERT INTO sales_transactions
                    (invoice_no, status, is_printed, printed_at, transaction_at, customer_name, subtotal, discount, downpayment, total, paid_amount, change_amount, payment_method, payment_note, is_non_cash)
                 VALUES
                    (:invoice_no, "pending", 0, NULL, NOW(), :customer_name, :subtotal, :discount, :downpayment, :total, :paid, :change, :payment_method, :payment_note, :is_non_cash)'
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
                ':payment_method' => $paymentMethod,
                ':payment_note' => $paymentNote !== '' ? $paymentNote : null,
                ':is_non_cash' => $isNonCash,
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
                    paid_amount = :paid, change_amount = :change, discount = :discount, downpayment = :downpayment,
                    payment_method = :payment_method, payment_note = :payment_note, is_non_cash = :is_non_cash,
                    transaction_at = NOW(),
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
                ':payment_method' => $paymentMethod,
                ':payment_note' => $paymentNote !== '' ? $paymentNote : null,
                ':is_non_cash' => $isNonCash,
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
                    (invoice_no, status, is_printed, printed_at, transaction_at, customer_name, subtotal, discount, downpayment, total, paid_amount, change_amount, payment_method, payment_note, is_non_cash)
                 VALUES
                    (:invoice_no, "paid", 0, NULL, NOW(), :customer_name, :subtotal, :discount, :downpayment, :total, :paid, :change, :payment_method, :payment_note, :is_non_cash)'
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
                ':payment_method' => $paymentMethod,
                ':payment_note' => $paymentNote !== '' ? $paymentNote : null,
                ':is_non_cash' => $isNonCash,
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

    if ($action === 'cancel_paid') {
        $id = (int)($payload['transaction_id'] ?? 0);
        $password = (string)($payload['password'] ?? '');
        if ($id <= 0) json_error(422, 'ID transaksi tidak valid.');
        if (!verify_current_login_password($pdo, $password)) {
            json_error(422, 'Password login tidak sesuai.');
        }

        $check = $pdo->prepare('SELECT status, downpayment FROM sales_transactions WHERE id = :id');
        $check->execute([':id' => $id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) json_error(404, 'Transaksi tidak ditemukan.');
        if ((string)$row['status'] !== 'paid') {
            json_error(422, 'Hanya transaksi yang sudah bayar yang bisa dibatalkan.');
        }

        $stmt = $pdo->prepare(
            'UPDATE sales_transactions
             SET status = "pending",
                 paid_amount = :paid_amount,
                 change_amount = 0,
                 transaction_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':paid_amount' => (float)($row['downpayment'] ?? 0),
            ':id' => $id,
        ]);
        $pdo->commit();
        echo json_encode(['success' => true, 'status' => 'pending']);
        exit;
    }

    json_error(400, 'Action POST tidak dikenal.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_error(500, 'Gagal memproses transaksi: ' . $e->getMessage());
}
