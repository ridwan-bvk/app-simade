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
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS print_templates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        template_type VARCHAR(40) NOT NULL UNIQUE,
        template_name VARCHAR(120) NOT NULL,
        format_type VARCHAR(20) NOT NULL DEFAULT "html",
        template_content LONGTEXT NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

function save_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO app_settings (setting_key, setting_value)
         VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([':k' => $key, ':v' => $value]);
}

$defaults = [
    'receipt_store_name' => 'Toko POS Kita',
    'receipt_store_address' => 'Jl. Kenangan No. 123',
    'receipt_store_phone' => '',
    'receipt_bank_account' => '',
    'receipt_logo_url' => '',
    'receipt_header_text' => '',
    'receipt_footer_text' => 'Terima kasih sudah berbelanja',
    'receipt_show_logo' => '0',
    'receipt_show_store_info' => '1',
    'receipt_show_item_code' => '0',
    'receipt_paper_width_mm' => '58',
    'receipt_font_size_px' => '12',
    'receipt_header_align' => 'center',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_receipt_template') {
    $payload = [
        'receipt_store_name' => trim($_POST['receipt_store_name'] ?? ''),
        'receipt_store_address' => trim($_POST['receipt_store_address'] ?? ''),
        'receipt_store_phone' => trim($_POST['receipt_store_phone'] ?? ''),
        'receipt_bank_account' => trim($_POST['receipt_bank_account'] ?? ''),
        'receipt_logo_url' => trim($_POST['receipt_logo_url'] ?? ''),
        'receipt_header_text' => trim($_POST['receipt_header_text'] ?? ''),
        'receipt_footer_text' => trim($_POST['receipt_footer_text'] ?? ''),
        'receipt_show_logo' => isset($_POST['receipt_show_logo']) ? '1' : '0',
        'receipt_show_store_info' => isset($_POST['receipt_show_store_info']) ? '1' : '0',
        'receipt_show_item_code' => isset($_POST['receipt_show_item_code']) ? '1' : '0',
        'receipt_paper_width_mm' => in_array($_POST['receipt_paper_width_mm'] ?? '58', ['58', '80'], true) ? $_POST['receipt_paper_width_mm'] : '58',
        'receipt_font_size_px' => (string)max(10, min(16, (int)($_POST['receipt_font_size_px'] ?? 12))),
        'receipt_header_align' => in_array($_POST['receipt_header_align'] ?? 'center', ['left', 'center'], true) ? $_POST['receipt_header_align'] : 'center',
    ];

    if ($payload['receipt_store_name'] === '') {
        $payload['receipt_store_name'] = $defaults['receipt_store_name'];
    }
    if ($payload['receipt_footer_text'] === '') {
        $payload['receipt_footer_text'] = $defaults['receipt_footer_text'];
    }

    if (!empty($_FILES['receipt_logo_file']) && (int)($_FILES['receipt_logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadErr = (int)($_FILES['receipt_logo_file']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadErr === UPLOAD_ERR_OK) {
            $tmpName = (string)$_FILES['receipt_logo_file']['tmp_name'];
            $fileSize = (int)$_FILES['receipt_logo_file']['size'];
            $origName = (string)$_FILES['receipt_logo_file']['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
            if (!in_array($ext, $allowed, true)) {
                header('Location: settings.php?msg=error_logo_type');
                exit;
            }
            if ($fileSize > 2 * 1024 * 1024) {
                header('Location: settings.php?msg=error_logo_size');
                exit;
            }

            $uploadDir = __DIR__ . '/assets/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $newName = 'logo_' . date('Ymd_His') . '_' . random_int(1000, 9999) . '.' . $ext;
            $destPath = $uploadDir . '/' . $newName;
            if (!move_uploaded_file($tmpName, $destPath)) {
                header('Location: settings.php?msg=error_logo_upload');
                exit;
            }
            $payload['receipt_logo_url'] = 'assets/uploads/' . $newName;
        } else {
            header('Location: settings.php?msg=error_logo_upload');
            exit;
        }
    }

    foreach ($payload as $key => $value) {
        save_setting($pdo, $key, $value);
    }

    header('Location: settings.php?msg=saved');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_print_templates') {
    $notaTemplate = trim((string)($_POST['template_nota_html'] ?? ''));
    $kwitansiTemplate = trim((string)($_POST['template_kwitansi_html'] ?? ''));

    $stmt = $pdo->prepare(
        'INSERT INTO print_templates (template_type, template_name, format_type, template_content, is_active)
         VALUES (:type, :name, "html", :content, 1)
         ON DUPLICATE KEY UPDATE
            template_name = VALUES(template_name),
            template_content = VALUES(template_content),
            is_active = VALUES(is_active),
            updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':type' => 'nota',
        ':name' => 'Template Nota',
        ':content' => $notaTemplate,
    ]);
    $stmt->execute([
        ':type' => 'kwitansi',
        ':name' => 'Template Kwitansi',
        ':content' => $kwitansiTemplate,
    ]);

    header('Location: settings.php?msg=template_saved');
    exit;
}

$current = $defaults;
$rows = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'receipt_%'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    $key = (string)$row['setting_key'];
    if (array_key_exists($key, $current)) {
        $current[$key] = (string)$row['setting_value'];
    }
}

$templateRows = $pdo->query('SELECT template_type, template_content FROM print_templates WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC);
$printTemplates = [
    'nota' => '',
    'kwitansi' => '',
];
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
    <title>Pengaturan - POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/products.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .settings-layout {
            display: grid;
            grid-template-columns: minmax(420px, 1fr) 360px;
            gap: 20px;
            margin-top: 20px;
            align-items: start;
        }
        .settings-panel {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 22px;
            box-shadow: var(--shadow-sm);
        }
        .settings-title {
            margin: 0 0 14px;
            font-size: 19px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .field {
            margin-bottom: 12px;
        }
        .field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-secondary);
        }
        .field input,
        .field textarea,
        .field select {
            width: 100%;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: #fff;
            padding: 10px 11px;
            font-size: 14px;
            outline: none;
        }
        .field textarea { min-height: 76px; resize: vertical; }
        .field input:focus,
        .field textarea:focus,
        .field select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }
        .check-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin: 8px 0 14px;
        }
        .check-item {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 10px;
            font-size: 13px;
        }
        .panel-footer {
            border-top: 1px solid var(--border-color);
            padding-top: 12px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .receipt-preview-wrap {
            background: linear-gradient(135deg, #eef2ff 0%, #ecfeff 100%);
            border: 1px solid #c7d2fe;
        }
        .receipt-preview-paper {
            margin: 0 auto;
            width: 280px;
            background: #fff;
            border: 1px dashed #d1d5db;
            border-radius: 12px;
            padding: 12px;
            font-family: "Courier New", monospace;
            font-size: 12px;
            color: #111827;
        }
        .muted-note {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
        }
        .cards {
            margin-top: 18px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 12px;
        }
        .backup-panel {
            margin-top: 18px;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 16px;
        }
        .template-panel {
            margin-top: 18px;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 16px;
        }
        .template-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 10px;
        }
        .template-box textarea {
            width: 100%;
            min-height: 260px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-family: Consolas, monospace;
            font-size: 12px;
            line-height: 1.5;
            padding: 10px;
            resize: vertical;
        }
        .template-hint {
            margin-top: 10px;
            font-size: 12px;
            color: var(--text-muted);
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 10px;
        }
        .backup-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }
        .btn-backup {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            background: #f8fafc;
        }
        .btn-backup:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #eef2ff;
        }
        .small-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 14px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
            text-decoration: none;
            color: inherit;
        }
        .small-card .ic {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef2ff;
            color: #4338ca;
        }
        .alert-save {
            background: #ecfdf5;
            border: 1px solid #10b981;
            color: #065f46;
            border-radius: 12px;
            padding: 12px 14px;
            margin-top: 12px;
            font-size: 13px;
            font-weight: 600;
        }
        @media (max-width: 1100px) {
            .settings-layout { grid-template-columns: 1fr; }
            .template-grid { grid-template-columns: 1fr; }
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
            <li><a href="laporan.php" title="Laporan"><i data-feather="bar-chart-2"></i></a></li>
            <li class="active"><a href="settings.php" title="Pengaturan"><i data-feather="settings"></i></a></li>
            <li><a href="logout.php" title="Logout"><i data-feather="log-out"></i></a></li>
        </ul>
    </nav>

    <main style="flex:1; padding: 30px 44px; overflow-y: auto;">
        <header class="section-header" style="margin-bottom: 10px;">
            <div>
                <h1 style="font-size: 28px;">Pengaturan Sistem</h1>
                <p class="text-muted">Atur template cetakan resi dan konfigurasi sistem kasir.</p>
            </div>
        </header>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
            <div class="alert-save">
                Master template resi berhasil disimpan.
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'invalid_export'): ?>
            <div class="alert-save" style="background:#fef2f2;border-color:#f87171;color:#991b1b;">
                Jenis export tidak valid.
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'template_saved'): ?>
            <div class="alert-save">
                Master format laporan cetakan berhasil disimpan.
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && in_array($_GET['msg'], ['error_logo_type', 'error_logo_size', 'error_logo_upload'], true)): ?>
            <div class="alert-save" style="background:#fef2f2;border-color:#f87171;color:#991b1b;">
                <?php if ($_GET['msg'] === 'error_logo_type') echo 'Format logo tidak valid. Gunakan PNG/JPG/JPEG/WEBP/SVG.'; ?>
                <?php if ($_GET['msg'] === 'error_logo_size') echo 'Ukuran logo terlalu besar (maksimal 2MB).'; ?>
                <?php if ($_GET['msg'] === 'error_logo_upload') echo 'Gagal upload logo. Coba lagi.'; ?>
            </div>
        <?php endif; ?>

        <div class="settings-layout">
            <section class="settings-panel">
                <h2 class="settings-title">
                    <i data-feather="printer" style="width:18px;height:18px;"></i>
                    Master Cetakan Resi
                </h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_receipt_template">

                    <div class="form-grid-2">
                        <div class="field">
                            <label for="receipt_store_name">Nama Toko</label>
                            <input type="text" id="receipt_store_name" name="receipt_store_name" value="<?= htmlspecialchars($current['receipt_store_name']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="receipt_store_phone">No. Telepon</label>
                            <input type="text" id="receipt_store_phone" name="receipt_store_phone" value="<?= htmlspecialchars($current['receipt_store_phone']) ?>" placeholder="Contoh: 0812-xxxx-xxxx">
                        </div>
                    </div>

                    <div class="field">
                        <label for="receipt_bank_account">No. Rekening (Opsional)</label>
                        <input type="text" id="receipt_bank_account" name="receipt_bank_account" value="<?= htmlspecialchars($current['receipt_bank_account']) ?>" placeholder="Contoh: BCA 1234567890 a/n SiMade">
                    </div>

                    <div class="field">
                        <label for="receipt_store_address">Alamat Toko</label>
                        <textarea id="receipt_store_address" name="receipt_store_address"><?= htmlspecialchars($current['receipt_store_address']) ?></textarea>
                    </div>

                    <div class="form-grid-2">
                        <div class="field">
                            <label for="receipt_logo_url">URL Logo</label>
                            <input type="text" id="receipt_logo_url" name="receipt_logo_url" value="<?= htmlspecialchars($current['receipt_logo_url']) ?>" placeholder="https://.../logo.png">
                            <div class="muted-note">Bisa isi URL logo atau upload file logo di bawah.</div>
                        </div>
                        <div class="field">
                            <label for="receipt_logo_file">Upload Logo</label>
                            <input type="file" id="receipt_logo_file" name="receipt_logo_file" accept=".png,.jpg,.jpeg,.webp,.svg">
                        </div>
                        <div class="field">
                            <label for="receipt_header_align">Posisi Header</label>
                            <select id="receipt_header_align" name="receipt_header_align">
                                <option value="center" <?= $current['receipt_header_align'] === 'center' ? 'selected' : '' ?>>Tengah</option>
                                <option value="left" <?= $current['receipt_header_align'] === 'left' ? 'selected' : '' ?>>Kiri</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="field">
                            <label for="receipt_paper_width_mm">Lebar Kertas</label>
                            <select id="receipt_paper_width_mm" name="receipt_paper_width_mm">
                                <option value="58" <?= $current['receipt_paper_width_mm'] === '58' ? 'selected' : '' ?>>58 mm</option>
                                <option value="80" <?= $current['receipt_paper_width_mm'] === '80' ? 'selected' : '' ?>>80 mm</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="receipt_font_size_px">Ukuran Font</label>
                            <input type="number" id="receipt_font_size_px" name="receipt_font_size_px" min="10" max="16" value="<?= htmlspecialchars($current['receipt_font_size_px']) ?>">
                        </div>
                    </div>

                    <div class="field">
                        <label for="receipt_header_text">Teks Header Tambahan</label>
                        <input type="text" id="receipt_header_text" name="receipt_header_text" value="<?= htmlspecialchars($current['receipt_header_text']) ?>" placeholder="Contoh: Cabang Jakarta Barat">
                    </div>

                    <div class="field">
                        <label for="receipt_footer_text">Teks Footer</label>
                        <input type="text" id="receipt_footer_text" name="receipt_footer_text" value="<?= htmlspecialchars($current['receipt_footer_text']) ?>" required>
                    </div>

                    <div class="check-grid">
                        <label class="check-item"><input type="checkbox" name="receipt_show_logo" <?= $current['receipt_show_logo'] === '1' ? 'checked' : '' ?>> Tampilkan Logo</label>
                        <label class="check-item"><input type="checkbox" name="receipt_show_store_info" <?= $current['receipt_show_store_info'] === '1' ? 'checked' : '' ?>> Tampilkan Info Toko</label>
                        <label class="check-item"><input type="checkbox" name="receipt_show_item_code" <?= $current['receipt_show_item_code'] === '1' ? 'checked' : '' ?>> Tampilkan Kode Produk</label>
                    </div>

                    <div class="panel-footer">
                        <button type="submit" class="btn-primary">
                            <i data-feather="save" style="width:14px;height:14px;vertical-align:text-bottom;"></i>
                            Simpan Template
                        </button>
                    </div>
                </form>
            </section>

            <aside class="settings-panel receipt-preview-wrap">
                <h3 class="settings-title" style="font-size:16px;">
                    <i data-feather="eye" style="width:16px;height:16px;"></i>
                    Preview Struktur Resi
                </h3>
                <div class="receipt-preview-paper">
                    <div style="text-align: <?= htmlspecialchars($current['receipt_header_align']) ?>;">
                        <?php if ($current['receipt_show_logo'] === '1' && $current['receipt_logo_url'] !== ''): ?>
                            <img src="<?= htmlspecialchars($current['receipt_logo_url']) ?>" alt="logo" style="max-width:110px; max-height:50px; object-fit:contain;">
                        <?php endif; ?>
                        <?php if ($current['receipt_show_store_info'] === '1'): ?>
                            <div style="font-weight:700;"><?= htmlspecialchars($current['receipt_store_name']) ?></div>
                            <div style="font-size:11px;color:#4b5563;"><?= htmlspecialchars($current['receipt_store_address']) ?></div>
                            <?php if ($current['receipt_store_phone'] !== ''): ?>
                                <div style="font-size:11px;color:#4b5563;"><?= htmlspecialchars($current['receipt_store_phone']) ?></div>
                            <?php endif; ?>
                            <?php if ($current['receipt_bank_account'] !== ''): ?>
                                <div style="font-size:11px;color:#4b5563;">Rek: <?= htmlspecialchars($current['receipt_bank_account']) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($current['receipt_header_text'] !== ''): ?>
                            <div style="font-size:11px;color:#4b5563;"><?= htmlspecialchars($current['receipt_header_text']) ?></div>
                        <?php endif; ?>
                    </div>
                    <hr style="border:none;border-top:1px dashed #d1d5db;margin:8px 0;">
                    <div style="font-size:11px;">Contoh Produk A x2 <span style="float:right;">Rp 40.000</span></div>
                    <div style="font-size:11px;">Contoh Produk B x1 <span style="float:right;">Rp 15.000</span></div>
                    <div style="clear:both;"></div>
                    <hr style="border:none;border-top:1px dashed #d1d5db;margin:8px 0;">
                    <div style="font-weight:700;">Total <span style="float:right;">Rp 55.000</span></div>
                    <div style="clear:both;"></div>
                    <div style="text-align:center;margin-top:10px;font-size:11px;color:#374151;"><?= htmlspecialchars($current['receipt_footer_text']) ?></div>
                </div>
                <p class="muted-note">Preview ini menampilkan struktur dasar. Hasil final mengikuti data transaksi saat cetak dari menu kasir.</p>
            </aside>
        </div>

        <div class="cards">
            <a href="price_variants.php" class="small-card">
                <div class="ic"><i data-feather="tag"></i></div>
                <div>
                    <strong>Master Variasi Harga</strong>
                    <div class="muted-note">Kelola varian Reseller, Promo, Grosir, dan lainnya.</div>
                </div>
            </a>
        </div>

        <section class="backup-panel">
            <h3 class="settings-title" style="font-size:16px; margin-bottom: 6px;">
                <i data-feather="database" style="width:16px;height:16px;"></i>
                Backup Database
            </h3>
            <p class="muted-note">Download file backup sesuai kebutuhan. Gunakan SQL untuk restore penuh, CSV untuk analisis transaksi.</p>
            <div class="backup-actions">
                <a class="btn-backup" href="backup_export.php?type=full_sql">
                    <i data-feather="download" style="width:14px;height:14px;"></i>
                    Export SQL (Semua Data)
                </a>
                <a class="btn-backup" href="backup_export.php?type=transactions_sql">
                    <i data-feather="file-text" style="width:14px;height:14px;"></i>
                    Export SQL (Transaksi)
                </a>
                <a class="btn-backup" href="backup_export.php?type=transactions_csv">
                    <i data-feather="file" style="width:14px;height:14px;"></i>
                    Export CSV (Transaksi)
                </a>
            </div>
        </section>

        <section class="template-panel">
            <h3 class="settings-title" style="font-size:16px; margin-bottom: 6px;">
                <i data-feather="file-text" style="width:16px;height:16px;"></i>
                Master Format Laporan Cetakan
            </h3>
            <p class="muted-note">Template ini dipakai saat cetak di menu transaksi. Format tersimpan dalam tipe HTML dan bisa diedit kapan saja.</p>
            <form method="POST">
                <input type="hidden" name="action" value="save_print_templates">
                <div class="template-grid">
                    <div class="template-box">
                        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Template Nota (HTML)</label>
                        <textarea name="template_nota_html" placeholder="<div>Template Nota...</div>"><?= htmlspecialchars($printTemplates['nota']) ?></textarea>
                    </div>
                    <div class="template-box">
                        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Template Kwitansi (HTML)</label>
                        <textarea name="template_kwitansi_html" placeholder="<div>Template Kwitansi...</div>"><?= htmlspecialchars($printTemplates['kwitansi']) ?></textarea>
                    </div>
                </div>
                <div class="template-hint">
                    Placeholder yang tersedia:
                    <code>{{store_name}}</code>
                    <code>{{store_address}}</code>
                    <code>{{store_phone}}</code>
                    <code>{{bank_account}}</code>
                    <code>{{logo_url}}</code>
                    <code>{{logo_img}}</code>
                    <code>{{invoice_no}}</code>
                    <code>{{transaction_at}}</code>
                    <code>{{customer_name}}</code>
                    <code>{{items_rows}}</code>
                    <code>{{total}}</code>
                    <code>{{paid_amount}}</code>
                    <code>{{change_amount}}</code>
                    <code>{{status}}</code>
                    <code>{{footer_text}}</code>
                </div>
                <div class="panel-footer" style="margin-top:10px;">
                    <button type="submit" class="btn-primary">
                        <i data-feather="save" style="width:14px;height:14px;vertical-align:text-bottom;"></i>
                        Simpan Template Cetak
                    </button>
                </div>
            </form>
        </section>
    </main>
</div>
<script>feather.replace();</script>
</body>
</html>
