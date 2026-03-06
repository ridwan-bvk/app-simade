<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function ensure_auth_tables(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            userid VARCHAR(80) NOT NULL UNIQUE,
            full_name VARCHAR(120) NULL,
            password_hash VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (userid, full_name, password_hash, is_active)
             VALUES (:userid, :full_name, :password_hash, 1)'
        );
        $stmt->execute([
            ':userid' => 'admin',
            ':full_name' => 'Administrator',
            ':password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
        ]);
    }
}

function auth_login(PDO $pdo, string $userid, string $password): bool
{
    $stmt = $pdo->prepare('SELECT id, userid, full_name, password_hash, is_active FROM users WHERE userid = :userid LIMIT 1');
    $stmt->execute([':userid' => $userid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || (int)$user['is_active'] !== 1) {
        return false;
    }
    if (!password_verify($password, (string)$user['password_hash'])) {
        return false;
    }

    $_SESSION['auth_user'] = [
        'id' => (int)$user['id'],
        'userid' => (string)$user['userid'],
        'full_name' => (string)($user['full_name'] ?? ''),
    ];
    return true;
}

function current_user(): ?array
{
    return isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])
        ? $_SESSION['auth_user']
        : null;
}

function require_login(string $redirect = 'login.php'): void
{
    if (!current_user()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function require_api_login(): void
{
    if (!current_user()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Sesi login berakhir. Silakan login ulang.']);
        exit;
    }
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

