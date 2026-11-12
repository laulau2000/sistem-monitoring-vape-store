<?php
/**
 * Struk — halaman cetak nota setelah transaksi tersimpan.
 * Dapat dibuka ulang dari Riwayat Penjualan untuk cetak ulang.
 * Parameter: id (ID penjualan), auto (1 = langsung buka dialog cetak).
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

$scope = scoped_branch_id();
$id    = (int) ($_GET['id'] ?? 0);
$auto  = (int) ($_GET['auto'] ?? 0) === 1;

if ($id <= 0) {
    flash_set('error', 'Nota tidak ditemukan.');
    redirect('penjualan.php');
}

$stmt = db()->prepare(
    'SELECT s.*, b.nama AS branch_nama, b.kode AS branch_kode,
            b.alamat AS branch_alamat, b.telepon AS branch_telepon,
            u.nama AS kasir
     FROM sales s
     JOIN branches b ON b.id = s.branch_id
     LEFT JOIN users u ON u.id = s.user_id
     WHERE s.id = ?'
);
$stmt->execute([$id]);
$sale = $stmt->fetch();

/* Isolasi data: staf cabang hanya boleh melihat nota cabangnya sendiri */
if (!$sale || ($scope !== null && (int) $sale['branch_id'] !== $scope)) {
    flash_set('error', 'Nota tidak ditemukan untuk cabang Anda.');
    redirect(is_superadmin() ? 'penjualan.php' : 'pos.php');
}

$stmt = db()->prepare(
    'SELECT si.qty, si.harga, si.subtotal, p.nama, p.kode
     FROM sale_items si JOIN products p ON p.id = si.product_id
     WHERE si.sale_id = ? ORDER BY si.id'
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$metodeList = ['tunai' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer'];
$metode     = $metodeList[$sale['metode_bayar']] ?? ucfirst((string) $sale['metode_bayar']);
$totalQty   = 0;
foreach ($items as $it) {
    $totalQty += (int) $it['qty'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk <?= e($sale['invoice']) ?> &middot; <?= e(app_name()) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="receipt-body">

<div class="receipt-page">
    <div class="receipt-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Cetak Struk
        </button>
        <a class="btn btn-ghost" href="pos.php">Transaksi Baru</a>
        <?php if (!is_kasir()): ?>
            <a class="btn btn-ghost" href="penjualan.php">Riwayat Penjualan</a>
        <?php endif; ?>
    </div>

    <div class="receipt" id="receipt">
        <div class="receipt__brand">
            <?php if (app_logo() !== '' && is_file(__DIR__ . '/' . app_logo())): ?>
                <img class="receipt__logo" src="<?= e(app_logo()) ?>" alt="<?= e(app_name()) ?>">
            <?php endif; ?>
            <h2><?= e(app_name()) ?></h2>
            <div class="receipt__branch"><?= e($sale['branch_nama']) ?></div>
            <?php if ($sale['branch_alamat']): ?><div class="receipt__muted"><?= e($sale['branch_alamat']) ?></div><?php endif; ?>
            <?php if ($sale['branch_telepon']): ?><div class="receipt__muted">Telp. <?= e($sale['branch_telepon']) ?></div><?php endif; ?>
        </div>

        <div class="receipt__divider"></div>

        <div class="receipt__meta">
            <div><span>Invoice</span><strong><?= e($sale['invoice']) ?></strong></div>
            <div><span>Tanggal</span><strong><?= tanggal_id($sale['tanggal'], true) ?></strong></div>
            <div><span>Kasir</span><strong><?= e($sale['kasir'] ?: '-') ?></strong></div>
            <div><span>Cabang</span><strong><?= e($sale['branch_kode']) ?></strong></div>
        </div>

        <div class="receipt__divider"></div>

        <?php foreach ($items as $it): ?>
            <div class="receipt__item">
                <div class="receipt__item-name"><?= e($it['nama']) ?></div>
                <div class="receipt__item-row">
                    <span><?= angka($it['qty']) ?> &times; <?= rupiah($it['harga']) ?></span>
                    <span><?= rupiah($it['subtotal']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="receipt__divider"></div>

        <div class="receipt__row"><span>Subtotal (<?= angka($totalQty) ?> item)</span><span><?= rupiah((float) $sale['total'] + (float) $sale['diskon']) ?></span></div>
        <?php if ((float) $sale['diskon'] > 0): ?>
            <div class="receipt__row"><span>Diskon</span><span>-<?= rupiah($sale['diskon']) ?></span></div>
        <?php endif; ?>
        <div class="receipt__row receipt__row--total"><span>TOTAL</span><span><?= rupiah($sale['total']) ?></span></div>

        <div class="receipt__divider"></div>

        <div class="receipt__row"><span>Metode Bayar</span><span><?= e($metode) ?></span></div>
        <div class="receipt__row"><span>Bayar</span><span><?= rupiah($sale['bayar']) ?></span></div>
        <?php if ($sale['metode_bayar'] === 'tunai'): ?>
            <div class="receipt__row"><span>Kembalian</span><span><?= rupiah($sale['kembalian']) ?></span></div>
        <?php endif; ?>

        <?php if (!empty($sale['catatan'])): ?>
            <div class="receipt__divider"></div>
            <div class="receipt__row"><span>Catatan</span><span><?= e($sale['catatan']) ?></span></div>
        <?php endif; ?>

        <div class="receipt__divider"></div>

        <div class="receipt__thanks">
            <div>Terima kasih telah berbelanja</div>
            <div class="receipt__muted"><?= e(setting('footer_struk', 'Barang yang sudah dibeli tidak dapat ditukar/dikembalikan')) ?></div>
        </div>
    </div>
</div>

<?php if ($auto): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
</body>
</html>
