<?php
require_once 'auth.php';
require_once 'config/database.php';

// Handle AJAX for fetching prices
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_prices') {
$pdo = get_db();
ensure_auth_tables($pdo);
require_api_login();
    $productId = (int)$_GET['product_id'];
    try {
        $stmt = $pdo->prepare('SELECT variant_id, harga FROM product_prices WHERE product_id = :pid');
        $stmt->execute([':pid' => $productId]);
        $prices = $stmt->fetchAll();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'prices' => $prices]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'err' => $e->getMessage()]);
    }
    exit;
}

// Pastikan request via POST dan ada action-nya
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: products.php');
    exit;
}

$pdo    = get_db();
$action = $_POST['action'];
$hasUnitId = $pdo->query("SHOW COLUMNS FROM products LIKE 'unit_id'")->fetch(PDO::FETCH_ASSOC);
if (!$hasUnitId) {
    $pdo->exec('ALTER TABLE products ADD COLUMN unit_id INT UNSIGNED NULL AFTER kategori');
}
$hasUnitBaseQty = $pdo->query("SHOW COLUMNS FROM products LIKE 'unit_base_qty'")->fetch(PDO::FETCH_ASSOC);
if (!$hasUnitBaseQty) {
    $pdo->exec('ALTER TABLE products ADD COLUMN unit_base_qty DECIMAL(15,4) NOT NULL DEFAULT 1 AFTER unit_id');
}
$hasSupplierId = $pdo->query("SHOW COLUMNS FROM products LIKE 'supplier_id'")->fetch(PDO::FETCH_ASSOC);
if (!$hasSupplierId) {
    $pdo->exec('ALTER TABLE products ADD COLUMN supplier_id INT UNSIGNED NULL AFTER unit_base_qty');
}

// Check and Add new columns for flags
$hasIsTransaction = $pdo->query("SHOW COLUMNS FROM products LIKE 'is_transaction_product'")->fetch(PDO::FETCH_ASSOC);
if (!$hasIsTransaction) {
    $pdo->exec('ALTER TABLE products ADD COLUMN is_transaction_product TINYINT(1) NOT NULL DEFAULT 1 AFTER supplier_id');
}
$hasIsPurchase = $pdo->query("SHOW COLUMNS FROM products LIKE 'is_purchase_product'")->fetch(PDO::FETCH_ASSOC);
if (!$hasIsPurchase) {
    $pdo->exec('ALTER TABLE products ADD COLUMN is_purchase_product TINYINT(1) NOT NULL DEFAULT 1 AFTER is_transaction_product');
}

function normalizeVariantPrices($rawPrices) {
    if (!is_array($rawPrices)) {
        return [];
    }
    $cleanPrices = [];
    foreach ($rawPrices as $variantId => $harga) {
        $variantId = (int)$variantId;
        if ($variantId <= 0 || $harga === '' || $harga === null) {
            continue;
        }
        $priceValue = (float)$harga;
        if ($priceValue < 0) {
            continue;
        }
        $cleanPrices[$variantId] = $priceValue;
    }
    return $cleanPrices;
}

function resolveMainSellingPrice($manualPrice, $variantPrices, $defaultVariantId) {
    $manualPrice = (float)$manualPrice;
    $defaultVariantId = (int)$defaultVariantId;

    if ($defaultVariantId > 0 && isset($variantPrices[$defaultVariantId])) {
        return $variantPrices[$defaultVariantId];
    }

    if (!empty($variantPrices)) {
        return reset($variantPrices);
    }

    return $manualPrice;
}

// Helper to save variant prices
function saveVariantPrices($pdo, $productId, $prices) {
    $pdo->prepare('DELETE FROM product_prices WHERE product_id = :pid')->execute([':pid' => $productId]);

    if (empty($prices)) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO product_prices (product_id, variant_id, harga) VALUES (:pid, :vid, :harga)');
    foreach ($prices as $vid => $harga) {
        $stmt->execute([
            ':pid'   => $productId,
            ':vid'   => $vid,
            ':harga' => $harga
        ]);
    }
}

// ─────────────────────────────────────────
// CREATE
// ─────────────────────────────────────────
if ($action === 'create') {
    try {
        $variantPrices = normalizeVariantPrices($_POST['variant_prices'] ?? []);
        $unitId = (int)($_POST['unit_id'] ?? 0);
        $unitBaseQty = (float)($_POST['unit_base_qty'] ?? 1);
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $isTransaction = isset($_POST['is_transaction_product']) ? 1 : 0;
        $isPurchase = isset($_POST['is_purchase_product']) ? 1 : 0;

        if ($unitBaseQty <= 0) {
            $unitBaseQty = 1;
        }
        $hargaJualUtama = resolveMainSellingPrice(
            $_POST['harga_jual'] ?? 0,
            $variantPrices,
            $_POST['default_variant_id'] ?? 0
        );

        $pdo->beginTransaction();
        $sql = 'INSERT INTO products (kode_barang, nama_barang, kategori, unit_id, unit_base_qty, supplier_id, is_transaction_product, is_purchase_product, harga_beli, harga_jual, stok)
                VALUES (:kode_barang, :nama_barang, :kategori, :unit_id, :unit_base_qty, :supplier_id, :is_transaction, :is_purchase, :harga_beli, :harga_jual, :stok)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':kode_barang' => trim($_POST['kode_barang']),
            ':nama_barang' => trim($_POST['nama_barang']),
            ':kategori'    => $_POST['kategori'],
            ':unit_id'     => $unitId > 0 ? $unitId : null,
            ':unit_base_qty' => $unitBaseQty,
            ':supplier_id' => $supplierId > 0 ? $supplierId : null,
            ':is_transaction' => $isTransaction,
            ':is_purchase' => $isPurchase,
            ':harga_beli'  => (float)$_POST['harga_beli'],
            ':harga_jual'  => $hargaJualUtama,
            ':stok'        => (int)$_POST['stok'],
        ]);
        
        $newId = $pdo->lastInsertId();
        
        saveVariantPrices($pdo, $newId, $variantPrices);
        
        $pdo->commit();
        header('Location: products.php?msg=success_create');
    } catch (PDOException $e) {
        $pdo->rollBack();
        $err = ($e->getCode() == 23000)
            ? 'Kode barang sudah digunakan, gunakan kode yang berbeda.'
            : $e->getMessage();
        header('Location: products.php?msg=error_create&err=' . urlencode($err));
    }
    exit;
}

// ─────────────────────────────────────────
// UPDATE
// ─────────────────────────────────────────
if ($action === 'update') {
    $id = (int)$_POST['id'];
    if ($id <= 0) {
        header('Location: products.php?msg=error_update&err=' . urlencode('ID produk tidak valid.'));
        exit;
    }
    try {
        $variantPrices = normalizeVariantPrices($_POST['variant_prices'] ?? []);
        $unitId = (int)($_POST['unit_id'] ?? 0);
        $unitBaseQty = (float)($_POST['unit_base_qty'] ?? 1);
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $isTransaction = isset($_POST['is_transaction_product']) ? 1 : 0;
        $isPurchase = isset($_POST['is_purchase_product']) ? 1 : 0;

        if ($unitBaseQty <= 0) {
            $unitBaseQty = 1;
        }
        $hargaJualUtama = resolveMainSellingPrice(
            $_POST['harga_jual'] ?? 0,
            $variantPrices,
            $_POST['default_variant_id'] ?? 0
        );

        $pdo->beginTransaction();
        $sql = 'UPDATE products
                SET kode_barang = :kode_barang,
                    nama_barang = :nama_barang,
                    kategori    = :kategori,
                    unit_id     = :unit_id,
                    unit_base_qty = :unit_base_qty,
                    supplier_id = :supplier_id,
                    is_transaction_product = :is_transaction,
                    is_purchase_product = :is_purchase,
                    harga_beli  = :harga_beli,
                    harga_jual  = :harga_jual,
                    stok        = :stok
                WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':kode_barang' => trim($_POST['kode_barang']),
            ':nama_barang' => trim($_POST['nama_barang']),
            ':kategori'    => $_POST['kategori'],
            ':unit_id'     => $unitId > 0 ? $unitId : null,
            ':unit_base_qty' => $unitBaseQty,
            ':supplier_id' => $supplierId > 0 ? $supplierId : null,
            ':is_transaction' => $isTransaction,
            ':is_purchase' => $isPurchase,
            ':harga_beli'  => (float)$_POST['harga_beli'],
            ':harga_jual'  => $hargaJualUtama,
            ':stok'        => (int)$_POST['stok'],
            ':id'          => $id,
        ]);

        saveVariantPrices($pdo, $id, $variantPrices);
        
        $pdo->commit();
        header('Location: products.php?msg=success_update');
    } catch (PDOException $e) {
        $pdo->rollBack();
        $err = ($e->getCode() == 23000)
            ? 'Kode barang sudah digunakan oleh produk lain.'
            : $e->getMessage();
        header('Location: products.php?msg=error_update&err=' . urlencode($err));
    }
    exit;
}

// ─────────────────────────────────────────
// DELETE
// ─────────────────────────────────────────
if ($action === 'delete') {
    $id = (int)$_POST['id'];
    if ($id <= 0) {
        header('Location: products.php?msg=error_delete&err=' . urlencode('ID produk tidak valid.'));
        exit;
    }
    try {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);
        header('Location: products.php?msg=success_delete');
    } catch (PDOException $e) {
        header('Location: products.php?msg=error_delete&err=' . urlencode($e->getMessage()));
    }
    exit;
}

// Fallback
header('Location: products.php');
exit;
?>
