<?php
/**
 * Laporan Penjualan — halaman cetak laporan per periode.
 * Dibuka di tab baru; halaman otomatis membuka dialog cetak bila ?auto=1.
 * Parameter: dari, sampai, cabang, auto.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

if (is_kasir()) { redirect('pos.php'); }

$scope = scoped_branch_id();

$filterBranch = $scope !== null ? $scope : (int) ($_GET['cabang'] ?? 0);
$dari   = trim($_GET['dari'] ?? '');
$sampai = trim($_GET['sampai'] ?? '');
$auto   = (int) ($_GET['auto'] ?? 0) === 1;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari))   { $dari = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) { $sampai = date('Y-m-d'); }
if ($dari > $sampai) { [$dari, $sampai] = [$sampai, $dari]; }

$conds  = ['DATE(s.tanggal) >= :dari', 'DATE(s.tanggal) <= :sampai'];
$params = ['dari' => $dari, 'sampai' => $sampai];
if ($filterBranch > 0) { $conds[] = 's.branch_id = :bid'; $params['bid'] = $filterBranch; }
$where = 'WHERE ' . implode(' AND ', $conds);

/* Ringkasan */
$sum = db()->prepare(
    "SELECT COUNT(*) AS jml, COALESCE(SUM(s.total),0) AS omset,
            COALESCE(SUM(s.hpp),0) AS hpp, COALESCE(SUM(s.diskon),0) AS diskon
     FROM sales s $where"
);
$sum->execute($params);
$ringkas = $sum->fetch();

$qty = db()->prepare("SELECT COALESCE(SUM(si.qty),0) FROM sale_items si JOIN sales s ON s.id = si.sale_id $where");
$qty->execute($params);
$totalUnit = (int) $qty->fetchColumn();

/* Rincian per cabang (hanya bila tidak difilter ke satu cabang) */
$perCabang = [];
if ($filterBranch <= 0) {
    $q = db()->prepare(
        "SELECT b.nama, COUNT(*) AS jml, COALESCE(SUM(s.total),0) AS omset, COALESCE(SUM(s.hpp),0) AS hpp
         FROM sales s JOIN branches b ON b.id = s.branch_id $where
         GROUP BY b.id ORDER BY omset DESC"
    );
    $q->execute($params);
    $perCabang = $q->fetchAll();
}

/* Daftar transaksi */
$trx = db()->prepare(
    "SELECT s.invoice, s.tanggal, s.total, s.hpp, s.diskon, s.metode_bayar, b.nama AS cabang, u.nama AS kasir,
            (SELECT COALESCE(SUM(si.qty),0) FROM sale_items si WHERE si.sale_id = s.id) AS qty
     FROM sales s JOIN branches b ON b.id = s.branch_id
     LEFT JOIN users u ON u.id = s.user_id
     $where ORDER BY s.tanggal ASC, s.id ASC"
);
$trx->execute($params);
$daftar = $trx->fetchAll();

/* Nama cabang untuk judul */
$namaCabang = 'Semua Cabang';
if ($filterBranch > 0) {
    $c = db()->prepare('SELECT nama FROM branches WHERE id = ?');
    $c->execute([$filterBranch]);
    $namaCabang = (string) ($c->fetchColumn() ?: 'Semua Cabang');
}

$laba = (float) $ringkas['omset'] - (float) $ringkas['hpp'];
$logo = app_logo();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan <?= e($dari) ?> s/d <?= e($sampai) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #1e293b; margin: 0; padding: 24px; background: #fff; font-size: 12px; line-height: 1.45;
        }
        .doc { max-width: 820px; margin: 0 auto; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 14px; border-bottom: 2px solid #1e293b; margin-bottom: 18px; }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand img { width: 48px; height: 48px; object-fit: contain; }
        .brand h1 { font-size: 20px; font-weight: 800; margin: 0; letter-spacing: -.3px; }
        .brand p { font-size: 11px; color: #64748b; margin: 2px 0 0; }
        .doc-title { text-align: right; }
        .doc-title h2 { font-size: 18px; font-weight: 800; margin: 0; color: #b91c1c; text-transform: uppercase; letter-spacing: .5px; }
        .doc-title p { font-size: 11px; color: #64748b; margin: 3px 0 0; }

        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px; }
        .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; }
        .card span { font-size: 10.5px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; display: block; }
        .card strong { font-size: 15px; font-weight: 800; }
        .card strong.red { color: #b91c1c; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 12px; }
        th { background: #1e293b; color: #fff; text-align: left; padding: 8px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; }
        td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .num { text-align: right; font-family: 'JetBrains Mono', monospace; }
        tfoot td { font-weight: 800; border-top: 2px solid #1e293b; border-bottom: none; }

        .section-title { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: #64748b; margin: 18px 0 8px; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 36px; }
        .sig-box { text-align: center; }
        .sig-box .label { font-size: 11px; color: #64748b; border-bottom: 1px solid #cbd5e1; padding-bottom: 6px; margin-bottom: 60px; }
        .sig-box .name { font-size: 12px; font-weight: 700; border-top: 1px solid #1e293b; padding-top: 6px; }

        .toolbar { position: fixed; bottom: 20px; right: 20px; display: flex; gap: 10px; z-index: 100; }
        .toolbar button { padding: 10px 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,.15); }
        .btn-print { background: #b91c1c; color: #fff; }
        .btn-close { background: #f1f5f9; color: #1e293b; }

        @media print { body { padding: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="toolbar no-print">
    <button class="btn-close" onclick="tutupLaporan()">Tutup</button>
    <button class="btn-print" onclick="window.print()">Cetak / Simpan PDF</button>
</div>

<script>
    /* Tutup jendela; jika tab tidak dibuka oleh script, kembali ke halaman sebelumnya. */
    function tutupLaporan() {
        window.close();
        setTimeout(function () {
            if (!window.closed) {
                window.location.href = document.referrer || 'penjualan.php';
            }
        }, 250);
    }
</script>

<div class="doc">
    <div class="head">
        <div class="brand">
            <?php if ($logo !== '' && is_file(__DIR__ . '/' . $logo)): ?><img src="<?= e($logo) ?>" alt="Logo"><?php endif; ?>
            <div>
                <h1><?= e(app_name()) ?></h1>
                <p><?= e(setting('alamat_toko', '') ?: '-') ?> · Telp: <?= e(setting('telepon_toko', '') ?: '-') ?></p>
            </div>
        </div>
        <div class="doc-title">
            <h2>Laporan Penjualan</h2>
            <p>Periode: <?= e(date('d/m/Y', strtotime($dari))) ?> — <?= e(date('d/m/Y', strtotime($sampai))) ?></p>
            <p>Cabang: <?= e($namaCabang) ?></p>
            <p>Dicetak: <?= e(date('d/m/Y H:i')) ?></p>
        </div>
    </div>

    <div class="cards">
        <div class="card"><span>Jumlah Transaksi</span><strong><?= angka($ringkas['jml']) ?></strong></div>
        <div class="card"><span>Total Unit Terjual</span><strong><?= angka($totalUnit) ?></strong></div>
        <div class="card"><span>Total Diskon</span><strong><?= rupiah($ringkas['diskon']) ?></strong></div>
        <div class="card"><span>Total Omset</span><strong class="red"><?= rupiah($ringkas['omset']) ?></strong></div>
        <div class="card"><span>Total HPP</span><strong><?= rupiah($ringkas['hpp']) ?></strong></div>
        <div class="card"><span>Laba Kotor</span><strong><?= rupiah($laba) ?></strong></div>
    </div>

    <?php if ($perCabang): ?>
        <div class="section-title">Rekapitulasi per Cabang</div>
        <table>
            <thead><tr><th>Cabang</th><th class="num">Transaksi</th><th class="num">Omset</th><th class="num">HPP</th><th class="num">Laba Kotor</th></tr></thead>
            <tbody>
                <?php foreach ($perCabang as $pc): ?>
                    <tr>
                        <td><?= e($pc['nama']) ?></td>
                        <td class="num"><?= angka($pc['jml']) ?></td>
                        <td class="num"><?= rupiah($pc['omset']) ?></td>
                        <td class="num"><?= rupiah($pc['hpp']) ?></td>
                        <td class="num"><?= rupiah((float) $pc['omset'] - (float) $pc['hpp']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="section-title">Rincian Transaksi</div>
    <?php if (!$daftar): ?>
        <table><tbody><tr><td style="text-align:center;color:#64748b;padding:24px;">Tidak ada transaksi pada periode ini.</td></tr></tbody></table>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>No</th><th>Invoice</th><th>Tanggal</th><th>Cabang</th><th>Kasir</th>
                    <th class="num">Item</th><th class="num">Total</th><th class="num">HPP</th><th class="num">Laba</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($daftar as $t): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="num"><?= e($t['invoice']) ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($t['tanggal']))) ?></td>
                        <td><?= e($t['cabang']) ?></td>
                        <td><?= e($t['kasir'] ?: '-') ?></td>
                        <td class="num"><?= angka($t['qty']) ?></td>
                        <td class="num"><?= rupiah($t['total']) ?></td>
                        <td class="num"><?= rupiah($t['hpp']) ?></td>
                        <td class="num"><?= rupiah((float) $t['total'] - (float) $t['hpp']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">TOTAL</td>
                    <td class="num"><?= angka($totalUnit) ?></td>
                    <td class="num"><?= rupiah($ringkas['omset']) ?></td>
                    <td class="num"><?= rupiah($ringkas['hpp']) ?></td>
                    <td class="num"><?= rupiah($laba) ?></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <div class="signatures">
        <div class="sig-box">
            <div class="label">Dibuat Oleh</div>
            <div class="name"><?= e(current_user()['nama']) ?></div>
        </div>
        <div class="sig-box">
            <div class="label">Disetujui Oleh</div>
            <div class="name">________________</div>
        </div>
    </div>
</div>

<?php if ($auto): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
