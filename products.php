<?php
require_once 'auth.php';
require_once 'config/database.php';

// Ambil data produk dari MySQL
$pdo = get_db();
ensure_auth_tables($pdo);
require_login();
$stmt = $pdo->query(
    'SELECT p.*, COALESCE(v.total_variant_prices, 0) AS total_variant_prices
     FROM products p
     LEFT JOIN (
        SELECT product_id, COUNT(*) AS total_variant_prices
        FROM product_prices
        GROUP BY product_id
     ) v ON v.product_id = p.id
     ORDER BY p.id DESC'
);
$products = $stmt->fetchAll();

// Ambil variansi harga yang aktif dari TABEL BARU: master_variasi
$stmt = $pdo->query('SELECT * FROM master_variasi WHERE is_aktif = 1 ORDER BY id ASC');
$active_variants = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Produk - POS</title>
    <!-- Modern Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/products.css">
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .variant-pricing-panel {
            margin-bottom: 16px;
            background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
            border: 1px solid #dbeafe;
            border-radius: var(--border-radius-md);
            padding: 12px;
        }
        .variant-pricing-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        .variant-pricing-head h4 {
            margin: 0;
            font-size: 15px;
            color: #1e3a8a;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .variant-pricing-head p {
            margin: 0;
            color: #1e40af;
            font-size: 12px;
        }
        .variant-editor-row {
            display: grid;
            grid-template-columns: 1.3fr 1fr auto;
            gap: 8px;
            margin-bottom: 8px;
        }
        .variant-editor-row .form-group {
            margin-bottom: 0;
        }
        .variant-editor-row .form-control {
            background: #ffffff;
        }
        .variant-editor-action {
            display: flex;
            align-items: flex-end;
        }
        .variant-editor-action .btn-secondary {
            width: 100%;
            border-color: #bfdbfe;
            color: #1d4ed8;
            background: #eff6ff;
        }
        .variant-editor-action .btn-secondary:hover {
            background: #dbeafe;
        }
        .variant-prices-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-height: 120px;
            overflow-y: auto;
            padding-right: 4px;
        }
        .variant-price-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: white;
            padding: 10px 12px;
            border-radius: var(--border-radius-md);
            border: 1px solid var(--border-color);
        }
        .variant-price-main {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }
        .variant-price-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .variant-price-row .variant-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .variant-price-value {
            font-size: 13px;
            font-weight: 700;
            color: var(--emerald);
        }
        .variant-price-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-mini {
            border: 1px solid var(--border-color);
            background: #fff;
            color: var(--text-secondary);
            border-radius: var(--border-radius-sm);
            padding: 4px 8px;
            font-size: 11px;
            cursor: pointer;
        }
        .btn-mini:hover {
            background: var(--bg-main);
        }
        .btn-mini.danger {
            color: var(--rose);
            border-color: #fecaca;
        }
        .variant-empty {
            text-align: center;
            color: var(--text-muted);
            border: 1px dashed #bfdbfe;
            border-radius: var(--border-radius-md);
            padding: 12px;
            font-size: 12px;
        }
        .variant-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
        }
        .modal-content {
            max-width: 680px !important;
            max-height: 92vh !important;
        }
        .modal-body {
            padding: 16px !important;
        }
        .form-group {
            margin-bottom: 12px !important;
        }
        .modal-footer {
            position: sticky;
            bottom: 0;
            z-index: 2;
            padding: 14px 16px !important;
        }
        @media (max-width: 768px) {
            .variant-editor-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <div class="pos-layout">
        <!-- Sidebar Navigation -->
        <nav class="side-nav">
            <div class="logo">
                <div class="logo-icon"><i data-feather="box"></i></div>
            </div>
            <ul class="nav-links">
                <li><a href="index.php" title="Kasir"><i data-feather="shopping-cart"></i></a></li>
                <li><a href="transactions.php" title="Transaksi"><i data-feather="list"></i></a></li>
                <li class="active"><a href="products.php" title="Produk"><i data-feather="package"></i></a></li>
            <li><a href="laporan.php" title="Laporan"><i data-feather="bar-chart-2"></i></a></li>
            <li><a href="settings.php" title="Pengaturan"><i data-feather="settings"></i></a></li>
            <li><a href="logout.php" title="Logout"><i data-feather="log-out"></i></a></li>
        </ul>
    </nav>

        <!-- Main Content -->
        <main class="products-section" style="flex: 1; padding: 32px 48px; max-width: 100vw;">
            <header class="section-header" style="margin-bottom: 24px;">
                <div>
                    <h1 style="font-size: 28px; margin-bottom: 8px;">Master Data Produk</h1>
                    <p class="text-muted">Kelola data stok, harga, dan kategori produk untuk sistem kasir Anda.</p>
                </div>
                <div>
                    <button class="btn-primary" onclick="openCreateModal()" style="display: flex; align-items: center; gap: 8px;">
                        <i data-feather="plus"></i> Tambah Produk Baru
                    </button>
                </div>
            </header>

            <?php if (isset($_GET['msg'])): ?>
                <?php if ($_GET['msg'] == 'success_create'): ?>
                    <div class="alert alert-success">
                        <i data-feather="check-circle"></i> Produk baru berhasil ditambahkan!
                    </div>
                <?php elseif ($_GET['msg'] == 'success_update'): ?>
                    <div class="alert alert-success">
                        <i data-feather="check-circle"></i> Data produk berhasil diperbarui!
                    </div>
                <?php elseif ($_GET['msg'] == 'success_delete'): ?>
                    <div class="alert alert-success">
                        <i data-feather="check-circle"></i> Produk berhasil dihapus dari sistem!
                    </div>
                <?php elseif (strpos($_GET['msg'], 'error') !== false): ?>
                    <div class="alert alert-danger">
                        <i data-feather="alert-circle"></i> Terjadi kesalahan: <?= htmlspecialchars($_GET['err'] ?? 'Tidak diketahui') ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th style="width: 120px;">Kode</th>
                            <th>Nama Barang</th>
                            <th style="width: 140px;">Kategori</th>
                            <th style="width: 150px;">Harga Beli</th>
                            <th style="width: 190px;">Harga Jual</th>
                            <th style="width: 100px; text-align: center;">Stok</th>
                            <th style="width: 110px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 48px 24px;">
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                        <i data-feather="package" style="width: 48px; height: 48px; opacity: 0.5;"></i>
                                        <p>Belum ada data produk di database.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $no => $p): ?>
                            <tr>
                                <td style="color: var(--text-muted); font-size: 13px;"><?= $no + 1 ?></td>
                                <td style="font-family: monospace; font-size: 13px; color: var(--text-secondary);"><?= htmlspecialchars($p['kode_barang']) ?></td>
                                <td style="font-weight: 500; font-size: 15px;"><?= htmlspecialchars($p['nama_barang']) ?></td>
                                <td>
                                    <span class="category-btn active" style="padding: 4px 10px; font-size: 12px; cursor: default; background: var(--bg-main); color: var(--text-secondary); border-color: var(--border-color);">
                                        <?= htmlspecialchars($p['kategori']) ?>
                                    </span>
                                </td>
                                <td>Rp <?= number_format($p['harga_beli'], 0, ',', '.') ?></td>
                                <td style="font-weight: 600; color: var(--emerald);">
                                    Rp <?= number_format($p['harga_jual'], 0, ',', '.') ?>
                                    <?php if ((int)$p['total_variant_prices'] > 0): ?>
                                        <div class="variant-count-badge">
                                            <i data-feather="layers" style="width: 12px; height: 12px;"></i>
                                            <?= (int)$p['total_variant_prices'] ?> varian
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php
                                        $stok = $p['stok'];
                                        $stokColor  = $stok <= 10 ? 'var(--rose)' : 'var(--text-primary)';
                                        $stokWeight = $stok <= 10 ? '700' : '600';
                                    ?>
                                    <span style="color: <?= $stokColor ?>; font-weight: <?= $stokWeight ?>;">
                                        <?= htmlspecialchars($stok) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons" style="justify-content: center;">
                                        <button class="btn-icon edit" onclick='openEditModal(<?= json_encode($p) ?>)' title="Edit Produk">
                                            <i data-feather="edit-2"></i>
                                        </button>
                                        <form action="product_actions.php" method="POST"
                                              onsubmit="return confirm('Peringatan: Apakah Anda yakin ingin menghapus produk ini? Aksi ini tidak dapat dibatalkan.');"
                                              style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn-icon delete" title="Hapus Produk">
                                                <i data-feather="trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Create/Edit Modal Overlay -->
    <div class="modal-overlay" id="productModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Produk Baru</h3>
                <button class="btn-close" onclick="closeModal()" title="Tutup Modal"><i data-feather="x"></i></button>
            </div>
            <form action="product_actions.php" method="POST" id="productForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="productId" value="">
                
                <div class="modal-body">
                    <!-- Harga per Variansi -->
                    <div class="variant-pricing-panel">
                        <div class="variant-pricing-head">
                            <h4>
                                <i data-feather="tag" style="width: 16px; height: 16px;"></i>
                                Harga Jual per Variansi
                            </h4>
                            <p style="font-size:11px;">Opsional</p>
                        </div>

                        <div class="variant-editor-row">
                            <div class="form-group">
                                <label for="default_variant_id">Varian Harga Default (Kasir)</label>
                                <select id="default_variant_id" name="default_variant_id" class="form-control" style="appearance: auto;">
                                    <option value="">-- Gunakan Harga Jual Utama (manual) --</option>
                                    <?php foreach ($active_variants as $av): ?>
                                        <option value="<?= $av['id'] ?>" data-nama="<?= htmlspecialchars($av['nama_variansi']) ?>" data-warna="<?= htmlspecialchars($av['warna']) ?>">
                                            <?= htmlspecialchars($av['nama_variansi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="variant_price_editor">Harga Jual Varian (Rp)</label>
                                <input type="number" id="variant_price_editor" class="form-control" min="0" step="0.01" placeholder="Isi harga varian terpilih">
                            </div>
                            <div class="form-group variant-editor-action">
                                <button type="button" class="btn-secondary" onclick="saveCurrentVariantPrice()">
                                    Simpan Harga
                                </button>
                            </div>
                        </div>

                        <div id="variantPricesList" class="variant-prices-list">
                            <div class="variant-empty">Belum ada harga varian yang diinput.</div>
                        </div>
                        <div id="variantHiddenInputs"></div>
                    </div>

                    <div style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="kode_barang">Kode Barang</label>
                            <input type="text" id="kode_barang" name="kode_barang" class="form-control"
                                   required placeholder="Contoh: BRG-001">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="kategori">Kategori</label>
                            <select id="kategori" name="kategori" class="form-control" required style="appearance: none;">
                                <option value="">-- Pilih Kategori --</option>
                                <option value="Makanan">Makanan</option>
                                <option value="Minuman">Minuman</option>
                                <option value="Snack">Snack</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="nama_barang">Nama Produk</label>
                        <input type="text" id="nama_barang" name="nama_barang" class="form-control"
                               required placeholder="Contoh: Indomie Goreng Spesial">
                    </div>

                    <div style="display: flex; gap: 20px; margin-top: 8px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="harga_beli">Harga Beli Dasar (Rp)</label>
                            <input type="number" id="harga_beli" name="harga_beli" class="form-control"
                                   required min="0" placeholder="0">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="harga_jual">Harga Jual Utama (Rp)</label>
                            <input type="number" id="harga_jual" name="harga_jual" class="form-control"
                                   required min="0" placeholder="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="stok">Stok Awal</label>
                        <input type="number" id="stok" name="stok" class="form-control"
                               required min="0" value="0">
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn-primary" id="btnSubmit">
                        <i data-feather="save" style="width: 16px; height: 16px; display: inline-block; vertical-align: text-bottom; margin-right: 4px;"></i>
                        Simpan Produk
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        feather.replace();

        const modal       = document.getElementById('productModal');
        const form        = document.getElementById('productForm');
        const modalTitle  = document.getElementById('modalTitle');
        const formAction  = document.getElementById('formAction');
        const btnSubmit   = document.getElementById('btnSubmit');

        const defaultVariantSelect = document.getElementById('default_variant_id');
        const variantPriceEditor = document.getElementById('variant_price_editor');
        const variantPricesList = document.getElementById('variantPricesList');
        const variantHiddenInputs = document.getElementById('variantHiddenInputs');
        const hargaJualInput = document.getElementById('harga_jual');
        const variantMeta = {};
        let variantPrices = {};

        defaultVariantSelect.querySelectorAll('option').forEach(opt => {
            if (!opt.value) return;
            variantMeta[opt.value] = {
                nama: opt.dataset.nama || `Varian ${opt.value}`,
                warna: opt.dataset.warna || '#4F46E5',
            };
        });

        function renderVariantHiddenInputs() {
            variantHiddenInputs.innerHTML = '';
            Object.keys(variantPrices).forEach(variantId => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = `variant_prices[${variantId}]`;
                hidden.value = variantPrices[variantId];
                variantHiddenInputs.appendChild(hidden);
            });
        }

        function renderVariantPriceList() {
            const entries = Object.entries(variantPrices);
            if (entries.length === 0) {
                variantPricesList.innerHTML = '<div class="variant-empty">Belum ada harga varian yang diinput.</div>';
                renderVariantHiddenInputs();
                return;
            }

            variantPricesList.innerHTML = entries.map(([variantId, price]) => {
                const meta = variantMeta[variantId] || { nama: `Varian ${variantId}`, warna: '#4F46E5' };
                const isDefault = defaultVariantSelect.value === String(variantId);
                return `
                    <div class="variant-price-row">
                        <div class="variant-price-main">
                            <span class="variant-dot" style="background:${meta.warna};"></span>
                            <span class="variant-price-name">${meta.nama}</span>
                        </div>
                        <div class="variant-price-value">Rp ${Number(price).toLocaleString('id-ID')}</div>
                        <div class="variant-price-actions">
                            ${isDefault ? '<span class="btn-mini" style="border-color:#bfdbfe;color:#1d4ed8;">Default</span>' : ''}
                            <button type="button" class="btn-mini" onclick="editVariantPrice(${variantId})">Edit</button>
                            <button type="button" class="btn-mini danger" onclick="removeVariantPrice(${variantId})">Hapus</button>
                        </div>
                    </div>
                `;
            }).join('');
            renderVariantHiddenInputs();
        }

        function resetVariantPrices() {
            variantPrices = {};
            defaultVariantSelect.value = '';
            variantPriceEditor.value = '';
            renderVariantPriceList();
        }

        function getVariantPriceById(variantId) {
            const value = variantPrices[String(variantId)];
            return (value === undefined || value === null || value === '') ? null : value;
        }

        function syncHargaJualFromDefaultVariant() {
            const selectedVariantId = defaultVariantSelect.value;
            if (!selectedVariantId) {
                variantPriceEditor.value = '';
                renderVariantPriceList();
                return;
            }

            const variantPrice = getVariantPriceById(selectedVariantId);
            variantPriceEditor.value = variantPrice !== null ? variantPrice : '';
            if (variantPrice !== null && variantPrice !== '') {
                hargaJualInput.value = variantPrice;
            }
            renderVariantPriceList();
        }

        function saveCurrentVariantPrice() {
            const selectedVariantId = defaultVariantSelect.value;
            if (!selectedVariantId) {
                alert('Pilih varian harga terlebih dahulu.');
                return;
            }

            const raw = variantPriceEditor.value.trim();
            if (raw === '') {
                alert('Isi harga jual varian terlebih dahulu.');
                return;
            }

            const numericValue = Number(raw);
            if (Number.isNaN(numericValue) || numericValue < 0) {
                alert('Harga varian tidak valid.');
                return;
            }

            variantPrices[String(selectedVariantId)] = numericValue;
            hargaJualInput.value = numericValue;
            renderVariantPriceList();
        }

        function editVariantPrice(variantId) {
            defaultVariantSelect.value = String(variantId);
            variantPriceEditor.value = variantPrices[String(variantId)] ?? '';
            variantPriceEditor.focus();
        }

        function removeVariantPrice(variantId) {
            delete variantPrices[String(variantId)];
            if (defaultVariantSelect.value === String(variantId)) {
                variantPriceEditor.value = '';
            }
            renderVariantPriceList();
        }

        window.saveCurrentVariantPrice = saveCurrentVariantPrice;
        window.editVariantPrice = editVariantPrice;
        window.removeVariantPrice = removeVariantPrice;

        function openCreateModal() {
            modalTitle.textContent = 'Tambah Produk Baru';
            formAction.value = 'create';
            form.reset();
            document.getElementById('productId').value = '';
            resetVariantPrices();
            btnSubmit.innerHTML = '<i data-feather="save" style="width: 16px; height: 16px; display: inline-block; vertical-align: text-bottom; margin-right: 4px;"></i> Simpan Produk';
            feather.replace();
            modal.classList.add('active');
            setTimeout(() => document.getElementById('kode_barang').focus(), 100);
        }

        function openEditModal(product) {
            modalTitle.textContent = 'Edit Data Produk';
            formAction.value = 'update';
            document.getElementById('productId').value    = product.id;
            document.getElementById('kode_barang').value  = product.kode_barang || '';
            document.getElementById('nama_barang').value  = product.nama_barang || '';
            document.getElementById('kategori').value     = product.kategori    || '';
            document.getElementById('harga_beli').value   = product.harga_beli  || 0;
            document.getElementById('harga_jual').value   = product.harga_jual  || 0;
            document.getElementById('stok').value         = product.stok        || 0;

            resetVariantPrices();

            // Fetch variant prices for this product
            fetch(`product_actions.php?action=get_prices&product_id=${product.id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let detectedDefaultVariant = '';
                        data.prices.forEach(p => {
                            const roundedPrice = Math.round(Number(p.harga));
                            variantPrices[String(p.variant_id)] = roundedPrice;
                            if (!detectedDefaultVariant && Number(product.harga_jual) === roundedPrice) {
                                detectedDefaultVariant = String(p.variant_id);
                            }
                        });
                        renderVariantPriceList();
                        if (detectedDefaultVariant) {
                            defaultVariantSelect.value = detectedDefaultVariant;
                            variantPriceEditor.value = variantPrices[detectedDefaultVariant] ?? '';
                        }
                    }
                });

            btnSubmit.innerHTML = '<i data-feather="check" style="width: 16px; height: 16px; display: inline-block; vertical-align: text-bottom; margin-right: 4px;"></i> Simpan Perubahan';
            feather.replace();
            modal.classList.add('active');
            setTimeout(() => document.getElementById('nama_barang').focus(), 100);
        }

        function closeModal() {
            modal.classList.remove('active');
        }

        // Tutup modal klik overlay
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });

        // Tutup modal ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.classList.contains('active')) closeModal();
        });

        // Auto-hide alert setelah 4 detik
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(a => setTimeout(() => a.style.opacity = '0', 4000));

        defaultVariantSelect.addEventListener('change', syncHargaJualFromDefaultVariant);
        variantPriceEditor.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveCurrentVariantPrice();
            }
        });
    </script>
</body>
</html>
