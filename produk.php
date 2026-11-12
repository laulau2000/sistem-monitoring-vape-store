<?php
/**
 * Produk — master produk (kelola oleh superadmin, lihat oleh cabang).
 * Tampilan: ringkasan KPI, tab kategori, dan kartu produk.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();
csrf_check();

$isSuper = is_superadmin();

/* ---- Simpan / ubah produk (superadmin) --------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_superadmin();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id         = (int) ($_POST['id'] ?? 0);
        $kode       = trim($_POST['kode'] ?? '');
        $nama       = trim($_POST['nama'] ?? '');
        $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
        $harga      = (float) ($_POST['harga'] ?? 0);
        $hargaBeli  = (float) ($_POST['harga_beli'] ?? 0);
        $stok       = (int) ($_POST['stok'] ?? 0);
        $hapusFoto  = isset($_POST['hapus_foto']) ? true : false;

        if ($kode === '' || $nama === '') {
            flash_set('error', 'Kode dan nama produk wajib diisi.');
            redirect('produk.php');
        }
        try {
            /* Ambil foto lama (untuk update) */
            $fotoLama = '';
            if ($id > 0) {
                $f = db()->prepare('SELECT foto FROM products WHERE id = ?');
                $f->execute([$id]);
                $fotoLama = (string) ($f->fetchColumn() ?: '');
            }

            $foto = $fotoLama;

            /* Hapus foto jika diminta */
            if ($hapusFoto && $fotoLama !== '') {
                $pathLama = __DIR__ . '/' . $fotoLama;
                if (is_file($pathLama)) { @unlink($pathLama); }
                $foto = '';
            }

            /* Upload foto baru */
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $tmp  = $_FILES['foto']['tmp_name'];
                $info = @getimagesize($tmp);
                $mime = $info['mime'] ?? '';
                $ext  = '';
                if      ($mime === 'image/jpeg') { $ext = 'jpg'; }
                elseif  ($mime === 'image/png')  { $ext = 'png'; }
                elseif  ($mime === 'image/webp') { $ext = 'webp'; }
                elseif  ($mime === 'image/gif')  { $ext = 'gif'; }

                if ($ext === '') {
                    throw new Exception('Format foto tidak valid. Gunakan JPG, PNG, WEBP, atau GIF.');
                }
                if ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
                    throw new Exception('Ukuran foto terlalu besar (maks 2MB).');
                }

                $dir  = __DIR__ . '/img/produk';
                if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                $namaFile = 'produk-' . date('YmdHis') . '-' . mt_rand(1000, 9999) . '.' . $ext;
                $dest     = $dir . '/' . $namaFile;
                if (!@move_uploaded_file($tmp, $dest)) {
                    throw new Exception('Gagal menyimpan foto produk.');
                }
                /* Hapus foto lama jika diganti */
                if ($fotoLama !== '' && $fotoLama !== $foto) {
                    $pathLama = __DIR__ . '/' . $fotoLama;
                    if (is_file($pathLama)) { @unlink($pathLama); }
                }
                $foto = 'img/produk/' . $namaFile;
            }

            if ($id > 0) {
                $stmt = db()->prepare(
                    'UPDATE products SET kode=?, nama=?, category_id=?, harga=?, harga_beli=?, stok=?, foto=? WHERE id=?'
                );
                $stmt->execute([$kode, $nama, $categoryId, $harga, $hargaBeli, $stok, $foto, $id]);
                flash_set('success', 'Produk "' . $nama . '" berhasil diperbarui.');
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO products (kode, nama, category_id, harga, harga_beli, stok, foto, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$kode, $nama, $categoryId, $harga, $hargaBeli, $stok, $foto]);
                flash_set('success', 'Produk "' . $nama . '" berhasil ditambahkan.');
            }
        } catch (Throwable $ex) {
            flash_set('error', 'Gagal menyimpan produk: ' . $ex->getMessage());
        }
        redirect('produk.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $used = db()->prepare('SELECT COUNT(*) FROM sale_items WHERE product_id = ?');
        $used->execute([$id]);
        if ((int) $used->fetchColumn() > 0) {
            flash_set('error', 'Produk tidak dapat dihapus karena sudah dipakai pada transaksi.');
        } else {
            $f = db()->prepare('SELECT foto FROM products WHERE id = ?');
            $f->execute([$id]);
            $foto = (string) ($f->fetchColumn() ?: '');
            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            if ($foto !== '') {
                $path = __DIR__ . '/' . $foto;
                if (is_file($path)) { @unlink($path); }
            }
            flash_set('success', 'Produk berhasil dihapus.');
        }
        redirect('produk.php');
    }
}

/* ---- Ambil seluruh produk ---------------------------------------- */
$semua = db()->query(
    'SELECT p.id, p.kode, p.nama, p.category_id, p.stok, p.harga, p.harga_beli, p.foto, c.nama AS kategori
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.nama'
)->fetchAll();

/* ---- Ringkasan ---------------------------------------------------- */
$totalSku   = count($semua);
$totalUnit  = 0;
$nilaiBeli  = 0;
$nilaiJual  = 0;
$stokHabis  = 0;
$stokMenipis= 0;
$maxStok    = 0;
$countKat   = [];
foreach ($semua as $p) {
    $qty = (int) $p['stok'];
    $totalUnit   += $qty;
    $nilaiBeli   += $qty * (float) $p['harga_beli'];
    $nilaiJual   += $qty * (float) $p['harga'];
    $maxStok      = max($maxStok, $qty);
    if ($qty <= 0) {
        $stokHabis++;
    } elseif ($qty <= STOK_MINIMUM) {
        $stokMenipis++;
    }
    $ck = (int) $p['category_id'];
    $countKat[$ck] = ($countKat[$ck] ?? 0) + 1;
}

/* ---- Filter kategori & pencarian --------------------------------- */
$filterKat = (int) ($_GET['kategori'] ?? 0);
$cari      = trim($_GET['cari'] ?? '');

$produk = [];
foreach ($semua as $p) {
    if ($filterKat > 0 && (int) $p['category_id'] !== $filterKat) {
        continue;
    }
    if ($cari !== '') {
        $hay = strtolower($p['nama'] . ' ' . $p['kode'] . ' ' . ($p['kategori'] ?? ''));
        if (strpos($hay, strtolower($cari)) === false) {
            continue;
        }
    }
    $produk[] = $p;
}

$kategori = db()->query('SELECT id, nama FROM categories ORDER BY nama')->fetchAll();

$page_title = 'Produk';
$page_sub   = $totalSku . ' produk terdaftar · ' . angka($totalUnit) . ' unit stok';
$active     = 'produk';
require __DIR__ . '/includes/header.php';
?>

<!-- Ringkasan -->
<div class="grid-kpi">
    <div class="kpi">
        <div class="kpi__icon bg-blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
        </div>
        <div class="kpi__label">Total Produk</div>
        <div class="kpi__value"><?= angka($totalSku) ?></div>
        <div class="kpi__meta"><?= angka($totalUnit) ?> unit stok tersedia</div>
    </div>
    <div class="kpi">
        <div class="kpi__icon bg-purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="kpi__label">Nilai Persediaan (HPP)</div>
        <div class="kpi__value"><?= rupiah($nilaiBeli) ?></div>
        <div class="kpi__meta">Estimasi nilai jual <?= rupiah($nilaiJual) ?></div>
    </div>
    <div class="kpi">
        <div class="kpi__icon bg-amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="kpi__label">Perlu Perhatian</div>
        <div class="kpi__value"><?= angka($stokHabis + $stokMenipis) ?></div>
        <div class="kpi__meta"><span class="down"><?= angka($stokHabis) ?> habis</span> &middot; <?= angka($stokMenipis) ?> menipis</div>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <div><h3>Master Produk</h3><p>Daftar produk vape dan aksesoris</p></div>
        <div class="flex items-center gap-8">
            <form method="get" class="search-input">
                <?php if ($filterKat > 0): ?><input type="hidden" name="kategori" value="<?= $filterKat ?>"><?php endif; ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="form-control" type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari produk..." style="width:200px;">
            </form>
            <?php if ($isSuper): ?>
                <button class="btn btn-primary btn-sm" data-modal-target="modalProduk">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Tambah Produk
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="produk-tabs" id="produkTabs">
        <?php
        $qBase = $cari !== '' ? '&cari=' . urlencode($cari) : '';
        $tabSemua = $filterKat === 0 ? ' active' : '';
        ?>
        <a class="produk-tab<?= $tabSemua ?>" href="produk.php?<?= ltrim($qBase, '&') ?>">
            Semua <span class="produk-tab__count"><?= angka($totalSku) ?></span>
        </a>
        <?php foreach ($kategori as $k):
            $kid = (int) $k['id'];
            $on  = $filterKat === $kid ? ' active' : ''; ?>
            <a class="produk-tab<?= $on ?>" href="produk.php?kategori=<?= $kid ?><?= $qBase ?>">
                <?= e($k['nama']) ?> <span class="produk-tab__count"><?= angka($countKat[$kid] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="panel__body">
        <?php if (!$produk): ?>
            <div class="empty-state">Belum ada produk<?= ($cari || $filterKat) ? ' yang cocok dengan filter ini' : '' ?>.</div>
        <?php else: ?>
        <div class="produk-grid">
            <?php foreach ($produk as $p):
                $qty = (int) $p['stok'];
                if ($qty <= 0) {
                    $statusKelas = 'badge-red';     $statusLabel = 'Habis';
                } elseif ($qty <= STOK_MINIMUM) {
                    $statusKelas = 'badge-amber';   $statusLabel = 'Menipis';
                } else {
                    $statusKelas = 'badge-success'; $statusLabel = 'Aman';
                }
                $pct = $maxStok > 0 ? max(4, round(($qty / $maxStok) * 100)) : 0;
                $row = [
                    'id' => $p['id'], 'kode' => $p['kode'], 'nama' => $p['nama'],
                    'category_id' => $p['category_id'], 'harga' => $p['harga'],
                    'harga_beli' => $p['harga_beli'], 'stok' => $p['stok'],
                    'foto' => $p['foto'] ?: '',
                ]; ?>
                <div class="produk-card">
                    <div class="produk-card__top">
                        <span class="produk-card__cat"><?= e($p['kategori'] ?: 'Tanpa kategori') ?></span>
                        <span class="badge <?= $statusKelas ?>"><?= $statusLabel ?></span>
                    </div>
                    <?php if (!empty($p['foto']) && is_file(__DIR__ . '/' . $p['foto'])): ?>
                        <div class="produk-card__foto">
                            <img src="<?= e($p['foto']) ?>" alt="<?= e($p['nama']) ?>">
                        </div>
                    <?php endif; ?>
                    <div class="produk-card__name"><?= e($p['nama']) ?></div>
                    <div class="produk-card__code mono"><?= e($p['kode']) ?></div>
                    <div class="produk-card__stats">
                        <div><span>Harga Jual</span><strong><?= rupiah($p['harga']) ?></strong></div>
                        <div><span>HPP</span><strong class="muted"><?= rupiah($p['harga_beli']) ?></strong></div>
                    </div>
                    <div class="produk-card__bar"><span class="<?= $statusKelas === 'badge-red' ? 'low' : ($statusKelas === 'badge-amber' ? 'mid' : '') ?>" style="width:<?= $pct ?>%"></span></div>
                    <div class="produk-card__foot">
                        <div class="produk-card__stok">Stok <strong><?= angka($qty) ?></strong> unit</div>
                        <?php if ($isSuper): ?>
                        <div class="produk-card__actions">
                            <button type="button" class="icon-btn" title="Edit"
                                    data-modal-target="modalProduk"
                                    data-payload='<?= e(json_encode($row)) ?>'>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                            </button>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Hapus produk <?= e($p['nama']) ?>?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="icon-btn danger" title="Hapus">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isSuper): ?>
<div class="modal-backdrop" id="modalProduk">
    <div class="modal">
        <form method="post" id="formProduk" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="prodId" value="0">
            <input type="hidden" name="foto_lama" id="prodFotoLama" value="">
            <div class="modal__head">
                <h3 id="prodTitle">Tambah Produk</h3>
                <button type="button" class="modal__close" data-close-modal="modalProduk">&times;</button>
            </div>
            <div class="modal__body">
                <div class="form-grid">
                    <div class="form-row">
                        <label for="prodKode">Kode Produk</label>
                        <input class="form-control" type="text" name="kode" id="prodKode" placeholder="mis. LQ-001" required>
                    </div>
                    <div class="form-row">
                        <label for="prodNama">Nama Produk</label>
                        <input class="form-control" type="text" name="nama" id="prodNama" placeholder="mis. Liquid Saltnic 30ml" required>
                    </div>
                </div>
                <div class="form-row">
                    <label for="prodKategori">Kategori</label>
                    <select class="form-control" name="category_id" id="prodKategori">
                        <option value="">— Tanpa kategori —</option>
                        <?php foreach ($kategori as $k): ?>
                            <option value="<?= (int) $k['id'] ?>"><?= e($k['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grid">
                    <div class="form-row">
                        <label for="prodHarga">Harga Jual (Rp)</label>
                        <input class="form-control" type="number" name="harga" id="prodHarga" min="0" step="500" value="0">
                    </div>
                    <div class="form-row">
                        <label for="prodBeli">Harga Beli / HPP (Rp)</label>
                        <input class="form-control" type="number" name="harga_beli" id="prodBeli" min="0" step="500" value="0">
                    </div>
                </div>
                <div class="form-row">
                    <label for="prodStok">Stok Awal</label>
                    <input class="form-control" type="number" name="stok" id="prodStok" min="0" value="0">
                    <div class="form-hint">Untuk penambahan stok rutin dari expedisi, gunakan menu <strong>Barang Masuk</strong>.</div>
                </div>
                <div class="form-row">
                    <label for="prodFoto">Foto Produk</label>
                    <div class="foto-upload">
                        <div class="foto-preview" id="fotoPreview">
                            <div class="foto-preview__empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                <span>Belum ada foto</span>
                            </div>
                        </div>
                        <div class="foto-upload__actions">
                            <label class="btn btn-ghost btn-sm" for="prodFoto" style="cursor:pointer;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                Pilih Foto
                            </label>
                            <input type="file" name="foto" id="prodFoto" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;">
                            <label class="foto-hapus" id="fotoHapusWrap" style="display:none;">
                                <input type="checkbox" name="hapus_foto" id="prodHapusFoto">
                                <span>Hapus foto yang ada</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-hint">Format JPG/PNG/WEBP/GIF, maks 2MB. Foto akan tampil di POS untuk memudahkan kasir mengenali produk.</div>
                </div>
            </div>
            <div class="modal__foot">
                <button type="button" class="btn btn-ghost" data-close-modal="modalProduk">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Produk</button>
            </div>
        </form>
    </div>
</div>

<?php
$extra_js = <<<'JS'
(function () {
    var modal      = document.getElementById('modalProduk');
    var preview    = document.getElementById('fotoPreview');
    var fileInput  = document.getElementById('prodFoto');
    var fotoLama   = document.getElementById('prodFotoLama');
    var hapusWrap  = document.getElementById('fotoHapusWrap');
    var hapusFoto  = document.getElementById('prodHapusFoto');

    function tampilkanPreview(src) {
        if (src) {
            preview.innerHTML = '<img src="' + src + '" alt="Preview">';
        } else {
            preview.innerHTML =
                '<div class="foto-preview__empty">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>' +
                '<span>Belum ada foto</span></div>';
        }
    }

    function resetFoto() {
        fotoLama.value = '';
        fileInput.value = '';
        hapusFoto.checked = false;
        hapusWrap.style.display = 'none';
        tampilkanPreview('');
    }

    /* Preview saat memilih file */
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (!file) { return; }
            var reader = new FileReader();
            reader.onload = function (e) { tampilkanPreview(e.target.result); };
            reader.readAsDataURL(file);
            hapusWrap.style.display = 'none';
            hapusFoto.checked = false;
        });
    }

    /* Klik "Tambah Produk" — kosongkan form & foto */
    var btnTambah = document.querySelector('[data-modal-target="modalProduk"]:not([data-payload])');
    if (btnTambah) {
        btnTambah.addEventListener('click', function () {
            document.getElementById('formProduk').reset();
            document.getElementById('prodId').value = '0';
            document.getElementById('prodTitle').textContent = 'Tambah Produk';
            resetFoto();
        });
    }

    /* Klik edit — isi form termasuk foto dari data-payload */
    document.querySelectorAll('[data-modal-target="modalProduk"][data-payload]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('prodTitle').textContent = 'Edit Produk';
            var data;
            try { data = JSON.parse(btn.getAttribute('data-payload')); } catch (e) { data = {}; }
            resetFoto();
            if (data.foto) {
                fotoLama.value = data.foto;
                tampilkanPreview(data.foto);
                hapusWrap.style.display = '';
            }
        });
    });
})();
JS;
endif;
require __DIR__ . '/includes/footer.php';
?>
