<?php
require_once 'config/database.php';
require_once 'auth.php';

$pdo = get_db();
ensure_auth_tables($pdo);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS app_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(120) NOT NULL UNIQUE,
        setting_value LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

if (current_user()) {
    header('Location: index.php');
    exit;
}

$storeName = 'Aplikasi SiMade';
$logoUrl = '';
$rows = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('receipt_store_name','receipt_logo_url')")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    if ((string)$row['setting_key'] === 'receipt_store_name' && trim((string)$row['setting_value']) !== '') {
        $storeName = (string)$row['setting_value'];
    }
    if ((string)$row['setting_key'] === 'receipt_logo_url') {
        $logoUrl = trim((string)$row['setting_value']);
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userid = trim((string)($_POST['userid'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($userid === '' || $password === '') {
        $error = 'User ID dan password wajib diisi.';
    } else {
        if (auth_login($pdo, $userid, $password)) {
            header('Location: index.php');
            exit;
        }
        $error = 'Login gagal. Periksa User ID atau password.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($storeName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f6f8fb;
            --card: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --line: #e5e7eb;
            --primary: #2563eb;
            --primary-2: #1d4ed8;
            --danger-bg: #fef2f2;
            --danger-line: #fecaca;
            --danger-text: #991b1b;
        }
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at 15% 20%, rgba(37,99,235,.12), transparent 42%),
                radial-gradient(circle at 90% 10%, rgba(16,185,129,.10), transparent 36%),
                var(--bg);
            display: grid;
            place-items: center;
            padding: 18px;
        }
        .card {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 18px 40px rgba(15,23,42,.08);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .logo-box {
            width: 54px; height: 54px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #f8fafc;
            display: grid; place-items: center;
            overflow: hidden;
        }
        .logo-box img { width: 100%; height: 100%; object-fit: contain; }
        .logo-fallback {
            width: 100%; height: 100%;
            display: grid; place-items: center;
            color: #1d4ed8; font-weight: 800;
        }
        h1 { margin: 0; font-size: 21px; color: var(--text); }
        .muted { margin: 3px 0 0; font-size: 12px; color: var(--muted); }
        .field { margin-top: 12px; }
        .field label {
            display: block;
            margin-bottom: 6px;
            color: #374151;
            font-size: 12px;
            font-weight: 600;
        }
        .field input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            font-size: 14px;
            outline: none;
        }
        .field input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,.12);
        }
        .btn {
            width: 100%;
            margin-top: 14px;
            border: 0;
            border-radius: 10px;
            padding: 12px;
            font-size: 14px;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            cursor: pointer;
        }
        .error {
            margin-top: 10px;
            font-size: 13px;
            font-weight: 600;
            padding: 9px 10px;
            border-radius: 10px;
            color: var(--danger-text);
            border: 1px solid var(--danger-line);
            background: var(--danger-bg);
        }
        .seed-note {
            margin-top: 12px;
            font-size: 12px;
            color: var(--muted);
            border-top: 1px dashed var(--line);
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            <div class="logo-box">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo">
                <?php else: ?>
                    <div class="logo-fallback">SM</div>
                <?php endif; ?>
            </div>
            <div>
                <h1><?= htmlspecialchars($storeName) ?></h1>
                <p class="muted">Masuk untuk melanjutkan ke sistem kasir</p>
            </div>
        </div>

        <form method="POST" autocomplete="off">
            <div class="field">
                <label for="userid">User ID</label>
                <input type="text" id="userid" name="userid" placeholder="Masukkan User ID">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Masukkan Password">
            </div>
            <?php if ($error !== ''): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <button type="submit" class="btn">Login</button>
        </form>

        <div class="seed-note">
            <!-- Akun awal: <strong>admin</strong> / <strong>admin123</strong> -->
        </div>
    </div>
</body>
</html>
