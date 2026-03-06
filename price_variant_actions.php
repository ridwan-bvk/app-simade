<?php
require_once 'auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!current_user()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi login berakhir.']);
    exit;
}
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: price_variants.php');
    exit;
}

$pdo    = get_db();
$action = $_POST['action'];

if ($action === 'create') {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO master_variasi (nama_variansi, deskripsi, warna, is_aktif)
             VALUES (:nama, :desc, :warna, :aktif)'
        );
        $stmt->execute([
            ':nama'  => trim($_POST['nama_variansi']),
            ':desc'  => trim($_POST['deskripsi'] ?? ''),
            ':warna' => $_POST['warna'] ?? '#4F46E5',
            ':aktif' => isset($_POST['is_aktif']) ? 1 : 0,
        ]);
        header('Location: price_variants.php?msg=success_create');
    } catch (PDOException $e) {
        header('Location: price_variants.php?msg=error_create&err=' . urlencode($e->getMessage()));
    }
    exit;
}

if ($action === 'update') {
    $id = (int)$_POST['id'];
    try {
        $stmt = $pdo->prepare(
            'UPDATE master_variasi
             SET nama_variansi=:nama, deskripsi=:desc, warna=:warna, is_aktif=:aktif
             WHERE id=:id'
        );
        $stmt->execute([
            ':nama'  => trim($_POST['nama_variansi']),
            ':desc'  => trim($_POST['deskripsi'] ?? ''),
            ':warna' => $_POST['warna'] ?? '#4F46E5',
            ':aktif' => isset($_POST['is_aktif']) ? 1 : 0,
            ':id'    => $id,
        ]);
        header('Location: price_variants.php?msg=success_update');
    } catch (PDOException $e) {
        header('Location: price_variants.php?msg=error_update&err=' . urlencode($e->getMessage()));
    }
    exit;
}

if ($action === 'delete') {
    $id = (int)$_POST['id'];
    try {
        $stmt = $pdo->prepare('DELETE FROM master_variasi WHERE id=:id');
        $stmt->execute([':id' => $id]);
        header('Location: price_variants.php?msg=success_delete');
    } catch (PDOException $e) {
        header('Location: price_variants.php?msg=error_delete&err=' . urlencode($e->getMessage()));
    }
    exit;
}

header('Location: price_variants.php');
exit;
?>
