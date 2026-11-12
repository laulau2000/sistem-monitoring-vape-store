<?php
/**
 * Dashboard Monitoring — omset per cabang & konsolidasi toko induk.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

$scope  = scoped_branch_id();
$bulan  = $_GET['bulan'] ?? date('Y-m');
$allTime = ($bulan === 'all');

$start = $allTime ? null : $bulan . '-01';
$end   = $allTime ? null : date('Y-m-t', strtotime($start));

/* Bangun kondisi WHERE untuk tabel sales --------------------------------- */
$conds  = [];
$params = [];
if ($scope !== null) {
    $conds[] = 's.branch_id = :bid';
    $params['bid'] = $scope;
}
if (!$allTime) {
    $conds[] = 'DATE(s.tanggal) BETWEEN :start AND :end';
    $params['start'] = $start;
    $params['end']   = $end;
}
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

/* KPI utama -------------------------------------------------------------- */
$stmt = db()->prepare("SELECT COUNT(*) AS jml, COALESCE(SUM(s.total),0) AS omset, COALESCE(SUM(s.hpp),0) AS hpp FROM sales s $where");
$stmt->execute($params);
$kpi = $stmt->fetch();
$jumlahTrx  = (int) $kpi['jml'];
$totalOmset = (float) $kpi['omset'];
$totalHpp   = (float) $kpi['hpp'];
$labaKotor  = $totalOmset - $totalHpp;
$marginPct  = $totalOmset > 0 ? ($labaKotor / $totalOmset) * 100 : 0;
$rataRata   = $jumlahTrx > 0 ? $totalOmset / $jumlahTrx : 0;

$stmt = db()->prepare(
    "SELECT COALESCE(SUM(si.qty),0) AS qty
     FROM sale_items si JOIN sales s ON s.id = si.sale_id $where"
);
$stmt->execute($params);
$totalItem = (int) $stmt->fetchColumn();

/* Omset per cabang ------------------------------------------------------- */
$joinCond = $allTime ? '1=1' : 'DATE(s.tanggal) BETWEEN :start AND :end';
$scopeCond = $scope !== null ? 'AND b.id = :bid' : '';
$bp = [];
if (!$allTime) { $bp['start'] = $start; $bp['end'] = $end; }
if ($scope !== null) { $bp['bid'] = $scope; }

$stmt = db()->prepare(
    "SELECT b.id, b.nama, b.kode, b.is_pusat,
            COUNT(s.id) AS jml,
            COALESCE(SUM(s.total),0) AS omset,
            COALESCE(SUM(s.hpp),0) AS hpp
     FROM branches b
     LEFT JOIN sales s ON s.branch_id = b.id AND $joinCond
     WHERE 1=1 $scopeCond
     GROUP BY b.id, b.nama, b.kode, b.is_pusat
     ORDER BY b.is_pusat DESC, omset DESC"
);
$stmt->execute($bp);
$perCabang = $stmt->fetchAll();
$maxOmset  = 0;
foreach ($perCabang as $c) {
    $maxOmset = max($maxOmset, (float) $c['omset']);
}

/* Tren 14 hari terakhir -------------------------------------------------- */
$stmt = db()->prepare(
    "SELECT DATE(s.tanggal) AS tgl, COALESCE(SUM(s.total),0) AS omset
     FROM sales s
     WHERE DATE(s.tanggal) >= :from " . ($scope !== null ? 'AND s.branch_id = :bid' : '') . "
     GROUP BY DATE(s.tanggal) ORDER BY tgl"
);
$tp = ['from' => date('Y-m-d', strtotime('-13 days'))];
if ($scope !== null) { $tp['bid'] = $scope; }
$stmt->execute($tp);
$rawTrend  = $stmt->fetchAll();
$trendMap  = [];
foreach ($rawTrend as $r) { $trendMap[$r['tgl']] = (float) $r['omset']; }

$trendLabels = [];
$trendData   = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('d/m', strtotime($d));
    $trendData[]   = $trendMap[$d] ?? 0;
}

/* Produk terlaris -------------------------------------------------------- */
$stmt = db()->prepare(
    "SELECT p.nama, COALESCE(SUM(si.qty),0) AS qty, COALESCE(SUM(si.subtotal),0) AS omset
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     JOIN products p ON p.id = si.product_id
     $where
     GROUP BY p.id, p.nama ORDER BY qty DESC LIMIT 5"
);
$stmt->execute($where ? $params : []);
$topProduk = $stmt->fetchAll();

/* Transaksi terbaru ------------------------------------------------------ */
$stmt = db()->prepare(
    "SELECT s.invoice, s.tanggal, s.total, b.nama AS branch_nama
     FROM sales s JOIN branches b ON b.id = s.branch_id
     $where ORDER BY s.tanggal DESC, s.id DESC LIMIT 8"
);
$stmt->execute($where ? $params : []);
$trxTerbaru = $stmt->fetchAll();

/* Siapkan data untuk chart ----------------------------------------------- */
$branchChartLabels = [];
$branchChartData   = [];
foreach ($perCabang as $c) {
    $branchChartLabels[] = $c['kode'];
    $branchChartData[]   = (float) $c['omset'];
}

$page_title = 'Dashboard Monitoring';
$page_sub   = $allTime ? 'Seluruh periode' : 'Periode ' . tanggal_id($start);
$active     = 'dashboard';
$extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
require __DIR__ . '/includes/header.php';
?>

<!-- Filter periode -->
<div class="panel">
    <div class="panel__head">
        <div>
            <h3>Ringkasan Omset</h3>
            <p><?= $scope === null ? 'Konsolidasi seluruh cabang (Toko Induk)' : 'Data cabang: ' . e(current_user()['branch_nama']) ?></p>
        </div>
        <form class="flex items-center gap-8" method="get">
            <input class="form-control" type="month" name="bulan" value="<?= $allTime ? '' : e($bulan) ?>" style="width:auto;" onchange="this.form.submit()">
            <a class="btn btn-ghost btn-sm" href="?bulan=all">Semua Periode</a>
            <a class="btn btn-ghost btn-sm" href="?bulan=<?= e(date('Y-m')) ?>">Bulan Ini</a>
        </form>
    </div>
</div>

<!-- Kartu KPI -->
<div class="grid-kpi">
    <div class="kpi">
        <div class="kpi__icon bg-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="kpi__label">Total Omset</div>
        <div class="kpi__value"><?= rupiah($totalOmset) ?></div>
        <div class="kpi__meta"><?= $allTime ? 'Seluruh periode' : 'Periode ' . tanggal_id($start) ?></div>
    </div>
    <div class="kpi">
        <div class="kpi__icon bg-purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
        </div>
        <div class="kpi__label">Laba Kotor</div>
        <div class="kpi__value"><?= rupiah($labaKotor) ?></div>
        <div class="kpi__meta">Margin <span class="<?= $marginPct >= 0 ? 'up' : 'down' ?>"><?= number_format($marginPct, 1, ',', '.') ?>%</span> &middot; HPP <?= rupiah($totalHpp) ?></div>
    </div>
    <div class="kpi">
        <div class="kpi__icon bg-blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
        </div>
        <div class="kpi__label">Jumlah Transaksi</div>
        <div class="kpi__value"><?= angka($jumlahTrx) ?></div>
        <div class="kpi__meta">Nota penjualan tercatat</div>
    </div>
    <div class="kpi">
        <div class="kpi__icon bg-amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
        </div>
        <div class="kpi__label">Rata-rata / Transaksi</div>
        <div class="kpi__value"><?= rupiah($rataRata) ?></div>
        <div class="kpi__meta">Nilai belanja rata-rata</div>
    </div>
    <div class="kpi">
        <div class="kpi__icon bg-purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        </div>
        <div class="kpi__label">Total Item Terjual</div>
        <div class="kpi__value"><?= angka($totalItem) ?></div>
        <div class="kpi__meta">Unit produk keluar</div>
    </div>
</div>

<!-- Omset per cabang -->
<div class="panel">
    <div class="panel__head">
        <div>
            <h3>Omset per Cabang</h3>
            <p>Perbandingan kontribusi penjualan antar cabang</p>
        </div>
        <?php if ($scope === null): ?>
            <span class="badge badge-blue">Toko Induk &mdash; akses seluruh cabang</span>
        <?php endif; ?>
    </div>
    <div class="panel__body">
        <div class="grid-branch">
            <?php foreach ($perCabang as $c):
                $pct = $maxOmset > 0 ? round(((float) $c['omset'] / $maxOmset) * 100) : 0; ?>
                <div class="branch-card">
                    <div class="branch-card__top">
                        <div>
                            <div class="branch-card__name"><?= e($c['nama']) ?></div>
                            <div class="branch-card__code"><?= e($c['kode']) ?></div>
                        </div>
                        <?php if ((int) $c['is_pusat'] === 1): ?><span class="pusat-tag">Toko Induk</span><?php endif; ?>
                    </div>
                    <div class="branch-card__omset"><?= rupiah($c['omset']) ?></div>
                    <div class="branch-card__meta">
                        <span><?= angka($c['jml']) ?> transaksi</span>
                        <span>Laba <?= rupiah((float) $c['omset'] - (float) $c['hpp']) ?></span>
                    </div>
                    <div class="branch-card__bar"><span style="width:<?= $pct ?>%"></span></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Tren omset -->
    <div class="panel">
        <div class="panel__head">
            <div><h3>Tren Omset 14 Hari</h3><p>Pergerakan penjualan harian</p></div>
        </div>
        <div class="panel__body">
            <canvas id="chartTrend" height="120"></canvas>
        </div>
    </div>

    <!-- Produk terlaris -->
    <div class="panel">
        <div class="panel__head"><div><h3>Produk Terlaris</h3><p>Berdasarkan kuantitas terjual</p></div></div>
        <div class="panel__body panel__body--flush">
            <?php if (!$topProduk): ?>
                <div class="empty-state">Belum ada penjualan pada periode ini.</div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Produk</th><th class="text-right">Qty</th><th class="text-right">Omset</th></tr></thead>
                    <tbody>
                    <?php foreach ($topProduk as $p): ?>
                        <tr>
                            <td><?= e($p['nama']) ?></td>
                            <td class="text-right"><?= angka($p['qty']) ?></td>
                            <td class="text-right mono"><?= rupiah($p['omset']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart per cabang + transaksi terbaru -->
<div class="grid-2">
    <div class="panel">
        <div class="panel__head"><div><h3>Grafik Omset per Cabang</h3><p>Periode terpilih</p></div></div>
        <div class="panel__body"><canvas id="chartBranch" height="140"></canvas></div>
    </div>

    <div class="panel">
        <div class="panel__head"><div><h3>Transaksi Terbaru</h3><p>8 nota penjualan terakhir</p></div></div>
        <div class="panel__body panel__body--flush">
            <?php if (!$trxTerbaru): ?>
                <div class="empty-state">Belum ada transaksi.</div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Invoice</th><th>Cabang</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($trxTerbaru as $t): ?>
                        <tr>
                            <td class="mono"><?= e($t['invoice']) ?><div class="text-muted" style="font-size:11px;"><?= tanggal_id($t['tanggal'], true) ?></div></td>
                            <td><?= e($t['branch_nama']) ?></td>
                            <td class="text-right mono"><?= rupiah($t['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$trendLabelJson = json_encode($trendLabels);
$trendDataJson  = json_encode($trendData);
$branchLabelJson = json_encode($branchChartLabels);
$branchDataJson  = json_encode($branchChartData);

$extra_js = <<<JS
const trendCtx = document.getElementById('chartTrend');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: {$trendLabelJson},
        datasets: [{
            label: 'Omset',
            data: {$trendDataJson},
            borderColor: '#dc2626', backgroundColor: 'rgba(220, 38, 38, .10)', fill: true,
            tension: .38, borderWidth: 2.4, pointRadius: 2.5, pointBackgroundColor: '#dc2626'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false },
            tooltip: { callbacks: { label: c => 'Rp ' + c.parsed.y.toLocaleString('id-ID') } } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + (v/1000000).toFixed(1) + 'jt', color: '#94a3b8', font: { size: 11 } }, grid: { color: '#f1f5f9' } },
            x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { display: false } }
        }
    }
});

new Chart(document.getElementById('chartBranch'), {
    type: 'bar',
    data: {
        labels: {$branchLabelJson},
        datasets: [{
            data: {$branchDataJson},
            backgroundColor: ['#dc2626','#ef4444','#f87171','#fca5a5','#fecaca','#fee2e2'],
            borderRadius: 8, maxBarThickness: 46
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false },
            tooltip: { callbacks: { label: c => 'Rp ' + c.parsed.y.toLocaleString('id-ID') } } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + (v/1000000).toFixed(1) + 'jt', color: '#94a3b8', font: { size: 11 } }, grid: { color: '#f1f5f9' } },
            x: { ticks: { color: '#64748b', font: { size: 11 } }, grid: { display: false } }
        }
    }
});
JS;
require __DIR__ . '/includes/footer.php';

