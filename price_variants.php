<?php
require_once 'auth.php';
require_once 'config/database.php';

$pdo = get_db();
ensure_auth_tables($pdo);
require_login();
$stmt = $pdo->query('SELECT * FROM master_variasi ORDER BY id ASC');
$variants = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Variansi Harga - POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/products.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        /* ── Variant Cards Grid ── */
        .variants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 8px;
        }
        .variant-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: all 0.25s cubic-bezier(.4,0,.2,1);
            position: relative;
            overflow: hidden;
        }
        .variant-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--varian-color, #4F46E5);
            border-radius: 4px 4px 0 0;
        }
        .variant-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.08);
            border-color: var(--varian-color, #4F46E5);
        }
        .variant-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            width: fit-content;
        }
        .variant-badge .dot {
            width: 8px; height: 8px;
            border-radius: 50%;
        }
        .variant-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }
        .variant-desc {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.5;
        }
        .variant-actions {
            display: flex;
            gap: 8px;
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
        }
        .btn-edit-card {
            flex: 1;
            padding: 8px 12px;
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-edit-card:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        .btn-del-card {
            padding: 8px 12px;
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .btn-del-card:hover {
            background: #FEF2F2;
            border-color: var(--rose);
            color: var(--rose);
        }
        /* inactive overlay */
        .variant-card.inactive { opacity: 0.55; }
        .badge-inactive {
            position: absolute;
            top: 16px; right: 16px;
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
        }
        /* Color picker row */
        .color-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .color-swatch {
            width: 32px; height: 32px;
            border-radius: 50%;
            border: 3px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
        }
        .color-swatch.selected,
        .color-swatch:hover {
            border-color: var(--text-primary);
            transform: scale(1.15);
        }
        .info-banner {
            background: linear-gradient(135deg, #EFF6FF 0%, #F0FDF4 100%);
            border: 1px solid #DBEAFE;
            border-radius: var(--border-radius-md);
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .info-banner i { color: #3B82F6; flex-shrink: 0; }
        .info-banner p { font-size: 14px; color: #1E40AF; line-height: 1.5; margin: 0; }
    </style>
</head>
<body>
<div class="pos-layout">
    <!-- Sidebar -->
    <nav class="side-nav">
        <div class="logo">
            <div class="logo-icon"><i data-feather="box"></i></div>
        </div>
        <ul class="nav-links">
            <li><a href="index.php" title="Kasir"><i data-feather="shopping-cart"></i></a></li>
            <li><a href="transactions.php" title="Transaksi"><i data-feather="list"></i></a></li>
            <li><a href="products.php" title="Master Produk"><i data-feather="package"></i></a></li>
            <li class="active"><a href="price_variants.php" title="Varian Harga"><i data-feather="tag"></i></a></li>
            <li><a href="laporan.php" title="Laporan"><i data-feather="bar-chart-2"></i></a></li>
            <li><a href="settings.php" title="Pengaturan"><i data-feather="settings"></i></a></li>
            <li><a href="logout.php" title="Logout"><i data-feather="log-out"></i></a></li>
        </ul>
    </nav>

    <!-- Main -->
    <main style="flex:1; padding: 32px 48px; overflow-y: auto; max-width: 100vw;">
        <header class="section-header" style="margin-bottom: 24px;">
            <!-- <div>
                <h1 style="font-size: 28px; margin-bottom: 8px;">Master Variansi Harga</h1>
                <p class="text-muted">Kelola jenis-jenis harga: Normal, Reseller, Promo, Grosir, dll.</p>
            </div> -->
            <button class="btn-primary" onclick="openCreateModal()" style="display:flex;align-items:center;gap:8px;">
                <i data-feather="plus"></i> Tambah Variansi Baru
            </button>
        </header>

        <?php if (isset($_GET['msg'])): ?>
            <?php $isErr = strpos($_GET['msg'], 'error') !== false; ?>
            <div class="alert <?= $isErr ? 'alert-danger' : 'alert-success' ?>" id="alertBox">
                <i data-feather="<?= $isErr ? 'alert-circle' : 'check-circle' ?>"></i>
                <?php if (!$isErr): ?>
                    <?php if ($_GET['msg']=='success_create') echo 'Variansi harga baru berhasil ditambahkan!'; ?>
                    <?php if ($_GET['msg']=='success_update') echo 'Variansi harga berhasil diperbarui!'; ?>
                    <?php if ($_GET['msg']=='success_delete') echo 'Variansi harga berhasil dihapus!'; ?>
                <?php else: ?>
                    Terjadi kesalahan: <?= htmlspecialchars($_GET['err'] ?? '') ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="info-banner">
            <i data-feather="info" style="width:20px;height:20px;margin-top:2px;"></i>
            <p>Variansi harga digunakan untuk menentukan kelompok harga berbeda pada setiap produk (contoh: Harga Normal, Harga Reseller, Harga Promo). Setelah membuat variansi, Anda dapat mengatur harga spesifik per produk di halaman <a href="products.php" style="color:#1D4ED8;font-weight:600;">Master Produk</a>.</p>
        </div>

        <?php if (empty($variants)): ?>
            <div style="text-align:center;padding:60px;color:var(--text-muted);">
                <i data-feather="tag" style="width:56px;height:56px;opacity:0.3;"></i>
                <p style="margin-top:16px;font-size:16px;">Belum ada variansi harga. Mulai tambahkan sekarang!</p>
            </div>
        <?php else: ?>
        <div class="variants-grid">
            <?php foreach ($variants as $v): ?>
                <?php $col = htmlspecialchars($v['warna']); ?>
                <div class="variant-card <?= !$v['is_aktif'] ? 'inactive' : '' ?>"
                     style="--varian-color: <?= $col ?>;">
                    <?php if (!$v['is_aktif']): ?>
                        <span class="badge-inactive">Nonaktif</span>
                    <?php endif; ?>
                    <div class="variant-badge" style="background:<?= $col ?>18;color:<?= $col ?>;">
                        <span class="dot" style="background:<?= $col ?>;"></span>
                        Variansi Harga
                    </div>
                    <div class="variant-name"><?= htmlspecialchars($v['nama_variansi']) ?></div>
                    <div class="variant-desc"><?= htmlspecialchars($v['deskripsi'] ?: 'Tidak ada deskripsi.') ?></div>
                    <div class="variant-actions">
                        <button class="btn-edit-card"
                                onclick='openEditModal(<?= json_encode($v) ?>)'>
                            <i data-feather="edit-2" style="width:14px;height:14px;"></i> Edit
                        </button>
                        <form action="price_variant_actions.php" method="POST"
                              onsubmit="return confirm('Hapus varian ini?');" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $v['id'] ?>">
                            <button type="submit" class="btn-del-card" title="Hapus">
                                <i data-feather="trash-2" style="width:14px;height:14px;"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Add Card Placeholder -->
            <div class="variant-card" onclick="openCreateModal()"
                 style="border-style:dashed;cursor:pointer;align-items:center;justify-content:center;min-height:200px;background:transparent;">
                <i data-feather="plus-circle" style="width:36px;height:36px;color:var(--text-muted);opacity:0.5;"></i>
                <p style="color:var(--text-muted);font-weight:500;margin-top:8px;">Tambah Variansi Baru</p>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Modal -->
<div class="modal-overlay" id="variantModal">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h3 id="modalTitle">Tambah Variansi Harga</h3>
            <button class="btn-close" onclick="closeModal()"><i data-feather="x"></i></button>
        </div>
        <form action="price_variant_actions.php" method="POST" id="variantForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="variantId" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="nama_variansi">Nama Variansi <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="nama_variansi" name="nama_variansi" class="form-control"
                           required placeholder="Contoh: Harga Reseller">
                </div>
                <div class="form-group">
                    <label for="deskripsi">Deskripsi</label>
                    <input type="text" id="deskripsi" name="deskripsi" class="form-control"
                           placeholder="Contoh: Harga khusus untuk pembelian qty banyak">
                </div>
                <div class="form-group">
                    <label>Warna Label</label>
                    <div class="color-options" id="colorOptions">
                        <?php
                        $colors = ['#10B981','#4F46E5','#EF4444','#F59E0B','#3B82F6','#8B5CF6','#EC4899','#14B8A6','#6B7280','#111827'];
                        foreach ($colors as $c):
                        ?>
                        <div class="color-swatch" style="background:<?= $c ?>;"
                             data-color="<?= $c ?>"
                             onclick="selectColor(this)"
                             title="<?= $c ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="warna" id="warnaInput" value="#4F46E5">
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                        <input type="checkbox" id="is_aktif" name="is_aktif" value="1" checked
                               style="width:18px;height:18px;accent-color:var(--primary);">
                        <span>Aktifkan varian ini</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn-primary" id="btnSubmit">
                    <i data-feather="save" style="width:16px;height:16px;vertical-align:text-bottom;margin-right:4px;"></i>
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
feather.replace();
const modal = document.getElementById('variantModal');

function selectColor(el) {
    document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('warnaInput').value = el.dataset.color;
}

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Variansi Harga';
    document.getElementById('formAction').value = 'create';
    document.getElementById('variantForm').reset();
    document.getElementById('variantId').value = '';
    selectColor(document.querySelector('.color-swatch'));
    modal.classList.add('active');
    setTimeout(() => document.getElementById('nama_variansi').focus(), 100);
}

function openEditModal(v) {
    document.getElementById('modalTitle').textContent = 'Edit Variansi Harga';
    document.getElementById('formAction').value = 'update';
    document.getElementById('variantId').value = v.id;
    document.getElementById('nama_variansi').value = v.nama_variansi;
    document.getElementById('deskripsi').value = v.deskripsi || '';
    document.getElementById('warnaInput').value = v.warna || '#4F46E5';
    document.getElementById('is_aktif').checked = v.is_aktif == 1;

    // Select color swatch
    document.querySelectorAll('.color-swatch').forEach(s => {
        s.classList.toggle('selected', s.dataset.color === v.warna);
    });
    modal.classList.add('active');
}

function closeModal() { modal.classList.remove('active'); }
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// Auto-select first color on load
selectColor(document.querySelector('.color-swatch'));

// Auto-hide alert
const alertBox = document.getElementById('alertBox');
if (alertBox) setTimeout(() => alertBox.style.opacity = '0', 4000);
</script>
</body>
</html>
