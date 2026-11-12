<?php
/**
 * Ketersediaan Stok Gudang — pantau jumlah & nilai persediaan barang di gudang.
 * Bersifat baca-saja; pengelolaan produk tetap di menu Produk.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

/* ---- Ambil seluruh produk --------------------------------------- */
$rows = db()->query(
    'SELECT p.id, p.kode, p.nama, p.category_id, p.stok, p.harga, p.harga_beli,
            c.nama AS kategori
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.stok ASC, p.nama'
)->fetchAll();

/* ---- Ringkasan gudang ------------------------------------------- */
$totalSku    = count($rows);
$totalUnit   = 0;
$nilaiBeli   = 0;
$nilaiJual   = 0;
$stokHabis   = 0;
$stokMenipis = 0;
$maxStok     = 0;

foreach ($rows as $r) {
    $qty = (int) $r['stok'];
    $totalUnit += $qty;
    $nilaiBeli += $qty * (float) $r['harga_beli'];
    $nilaiJual += $qty * (float) $r['harga'];
    $maxStok    = max($maxStok, $qty);
    if ($qty <= 0) {
        $stokHabis++;
    } elseif ($qty <= STOK_MINIMUM) {
        $stokMenipis++;
    }
}
$perluRestock = $stokMenipis + $stokHabis;

/* ---- Filter ------------------------------------------------------ */
$filterKat    = (int) ($_GET['kategori'] ?? 0);
$filterStatus = (string) ($_GET['status'] ?? '');
$cari         = trim($_GET['cari'] ?? '');

$kategori = db()->query('SELECT id, nama FROM categories ORDER BY nama')->fetchAll();

$list = [];
foreach ($rows as $r) {
    $qty = (int) $r['stok'];

    if ($filterKat > 0 && (int) $r['category_id'] !== $filterKat) {
        continue;
    }
    if ($filterStatus === 'habis' && $qty > 0) {
        continue;
    }
    if ($filterStatus === 'menipis' && !($qty > 0 && $qty <= STOK_MINIMUM)) {
        continue;
    }
    if ($filterStatus === 'aman' && $qty <= STOK_MINIMUM) {
        continue;
    }
    if ($cari !== '') {
        $haystack = strtolower($r['nama'] . ' ' . $r['kode'] . ' ' . ($r['kategori'] ?? ''));
        if (strpos($haystack, strtolower($cari)) === false) {
            continue;
        }
    }

    $r['_unit']   = $qty;
    $r['_nilai']  = $qty * (float) $r['harga'];
    $list[] = $r;
}

/* ---- Ekspor CSV (mengikuti filter aktif) ------------------------- */
if (($_GET['export'] ?? '') === 'csv') {
    $csv = [];
    foreach ($list as $r) {
        $q = (int) $r['stok'];
        $status = $q <= 0 ? 'Habis' : ($q <= STOK_MINIMUM ? 'Menipis' : 'Aman');
        $csv[] = [
            $r['kode'],
            $r['nama'],
            $r['kategori'] ?: 'Tanpa kategori',
            $q,
            (float) $r['harga_beli'],
            (float) $r['harga'],
            $q * (float) $r['harga_beli'],
            $q * (float) $r['harga'],
            $status,
        ];
    }
    export_csv(
        'ketersediaan-stok-' . date('Ymd') . '.csv',
        ['Kode', 'Nama Produk', 'Kategori', 'Stok', 'HPP per Unit', 'Harga Jual', 'Nilai HPP', 'Nilai Jual', 'Status'],
        $csv
    );
}

$filterQS = http_build_query(array_filter([
    'kategori' => $filterKat > 0 ? $filterKat : '',
    'status'   => $filterStatus,
    'cari'     => $cari,
], static function ($v) { return $v !== '' && $v !== null; }));

$page_title = 'Ketersediaan Stok Gudang';
$page_sub   = $totalSku . ' SKU terdaftar · ' . angka($totalUnit) . ' unit tersedia';
$active     = 'stok';
require __DIR__ . '/includes/header.php';
?>

<!-- Ringkasan gudang -->
<div class="grid-kpi">
    <div class="kpi">
        <div class="kpi__icon bg-blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
        </div>
        <div class="kpi__label">Total SKU</div>
        <div class="kpi__value"><?= angka($totalSku) ?></div>
        <div class="kpi__meta">Jenis produk di gudang</div>
    </div>
    <div class="kpi">
        <div class="kpi__icon bg-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg>
        </div>
        <div class="kpi__label">Total Unit Stok</div>
        <div class="kpi__value"><?= angka($totalUnit) ?></div>
        <div class="kpi__meta">Akumulasi seluruh barang</div>
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
        <div class="kpi__label">Perlu Restock</div>
        <div class="kpi__value"><?= angka($perluRestock) ?></div>
        <div class="kpi__meta"><span class="down"><?= angka($stokHabis) ?> habis</span> &middot; <?= angka($stokMenipis) ?> menipis</div>
    </div>
</div>

<!-- Filter -->
<div class="panel">
    <div class="panel__head">
        <div><h3>Ketersediaan Barang</h3><p>Pantau stok &amp; nilai persediaan gudang pusat</p></div>
        <span class="badge badge-blue">Gudang Pusat</span>
    </div>
    <div class="panel__body">
        <form method="get" class="flex items-center gap-12" style="flex-wrap:wrap;">
            <div style="min-width:190px;">
                <select class="form-control" name="kategori">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($kategori as $k): ?>
                        <option value="<?= (int) $k['id'] ?>" <?= $filterKat === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:160px;">
                <select class="form-control" name="status">
                    <option value="">Semua Status</option>
                    <option value="aman"    <?= $filterStatus === 'aman' ? 'selected' : '' ?>>Stok Aman</option>
                    <option value="menipis" <?= $filterStatus === 'menipis' ? 'selected' : '' ?>>Stok Menipis</option>
                    <option value="habis"   <?= $filterStatus === 'habis' ? 'selected' : '' ?>>Stok Habis</option>
                </select>
            </div>
            <input class="form-control" type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari produk / kode..." style="width:auto;min-width:200px;">
            <button class="btn btn-primary btn-sm" type="submit">Terapkan</button>
            <a class="btn btn-ghost btn-sm" href="stok.php">Reset</a>
            <a class="btn btn-ghost btn-sm" href="stok.php?<?= e($filterQS !== '' ? $filterQS . '&' : '') ?>export=csv">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Ekspor CSV
            </a>
        </form>
    </div>
</div>

<!-- Daftar stok (grid card) -->
<div class="panel">
    <div class="panel__head">
        <div><h3>Daftar Persediaan</h3><p>Menampilkan <?= angka(count($list)) ?> dari <?= angka($totalSku) ?> SKU</p></div>
    </div>
    <div class="panel__body">
        <?php if (!$list): ?>
            <div class="empty-state">Tidak ada produk yang cocok dengan filter ini.</div>
        <?php else: ?>
        <div class="grid-branch">
            <?php foreach ($list as $p):
                $qty = (int) $p['_unit'];
                if ($qty <= 0) {
                    $statusKelas = 'badge-red';
                    $statusLabel = 'Habis';
                } elseif ($qty <= STOK_MINIMUM) {
                    $statusKelas = 'badge-amber';
                    $statusLabel = 'Menipis';
                } else {
                    $statusKelas = 'badge-success';
                    $statusLabel = 'Aman';
                }
                $pct = $maxStok > 0 ? round(($qty / $maxStok) * 100) : 0; ?>
                <div class="branch-card">
                    <div class="branch-card__top">
                        <div>
                            <div class="branch-card__name"><?= e($p['nama']) ?></div>
                            <div class="branch-card__code"><?= e($p['kode']) ?> &middot; <?= e($p['kategori'] ?: 'Tanpa kategori') ?></div>
                        </div>
                        <span class="badge <?= $statusKelas ?>"><?= $statusLabel ?></span>
                    </div>
                    <div class="branch-card__omset"><?= angka($qty) ?> <span style="font-size:12px;font-weight:600;color:var(--text-muted);">unit</span></div>
                    <div class="branch-card__meta">
                        <span>Nilai jual <?= rupiah($p['_nilai']) ?></span>
                        <span>HPP <?= rupiah($p['harga_beli']) ?>/unit</span>
                    </div>
                    <div class="branch-card__bar"><span style="width:<?= $pct ?>%"></span></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
