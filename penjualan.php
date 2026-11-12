<?php
/**
 * Riwayat Penjualan — daftar transaksi per cabang beserta detail nota.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();
csrf_check();

$scope = scoped_branch_id();

/* ---- Hapus transaksi (superadmin) -------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_superadmin();
    $id = (int) ($_POST['id'] ?? 0);
    try {
        db()->beginTransaction();
        $items = db()->prepare('SELECT product_id, qty FROM sale_items WHERE sale_id = ?');
        $items->execute([$id]);
        $restore = db()->prepare('UPDATE products SET stok = stok + ? WHERE id = ?');
        foreach ($items->fetchAll() as $it) {
            $restore->execute([$it['qty'], $it['product_id']]);
        }
        db()->prepare('DELETE FROM sale_items WHERE sale_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM sales WHERE id = ?')->execute([$id]);
        db()->commit();
        flash_set('success', 'Transaksi dihapus dan stok produk dikembalikan.');
    } catch (Throwable $ex) {
        if (db()->inTransaction()) { db()->rollBack(); }
        flash_set('error', 'Gagal menghapus transaksi: ' . $ex->getMessage());
    }
    redirect('penjualan.php');
}

/* ---- Filter ------------------------------------------------------ */
$filterBranch = $scope !== null ? $scope : (int) ($_GET['cabang'] ?? 0);
$dari   = trim($_GET['dari'] ?? '');
$sampai = trim($_GET['sampai'] ?? '');
$cari   = trim($_GET['cari'] ?? '');

$conds = [];
$params = [];
if ($filterBranch > 0) { $conds[] = 's.branch_id = :bid'; $params['bid'] = $filterBranch; }
if ($dari !== '')      { $conds[] = 'DATE(s.tanggal) >= :dari'; $params['dari'] = $dari; }
if ($sampai !== '')    { $conds[] = 'DATE(s.tanggal) <= :sampai'; $params['sampai'] = $sampai; }
if ($cari !== '')      { $conds[] = 's.invoice LIKE :cari'; $params['cari'] = '%' . $cari . '%'; }
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

/* ---- Paginasi ---------------------------------------------------- */
$perPage = 25;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$countStmt = db()->prepare("SELECT COUNT(*) FROM sales s $where");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPage = max(1, (int) ceil($totalRows / $perPage));

$sql = "SELECT s.id, s.invoice, s.tanggal, s.total, s.catatan,
               b.nama AS branch_nama, b.kode AS branch_kode, u.nama AS kasir
        FROM sales s
        JOIN branches b ON b.id = s.branch_id
        LEFT JOIN users u ON u.id = s.user_id
        $where
        ORDER BY s.tanggal DESC, s.id DESC
        LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

/* Total omset sesuai filter (tanpa paginasi) */
$sumStmt = db()->prepare("SELECT COALESCE(SUM(s.total),0) FROM sales s $where");
$sumStmt->execute($params);
$totalFilter = (float) $sumStmt->fetchColumn();

/* ---- Ambil item untuk nota yang tampil --------------------------- */
$itemsBySale = [];
if ($sales) {
    $ids = array_column($sales, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $it = db()->prepare(
        "SELECT si.sale_id, p.nama, p.kode, si.qty, si.harga, si.subtotal
         FROM sale_items si JOIN products p ON p.id = si.product_id
         WHERE si.sale_id IN ($in) ORDER BY si.id"
    );
    $it->execute($ids);
    foreach ($it->fetchAll() as $row) {
        $itemsBySale[$row['sale_id']][] = $row;
    }
}

$cabangList = is_superadmin()
    ? db()->query('SELECT id, nama, kode FROM branches ORDER BY is_pusat DESC, nama')->fetchAll()
    : [];

/* ---- Ekspor CSV (mengikuti filter aktif) ------------------------- */
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = db()->prepare(
        "SELECT s.invoice, s.tanggal, s.metode_bayar, s.diskon, s.total, s.hpp, s.bayar, s.kembalian,
                b.nama AS branch_nama, b.kode AS branch_kode, u.nama AS kasir,
                (SELECT COALESCE(SUM(si.qty),0) FROM sale_items si WHERE si.sale_id = s.id) AS qty_total
         FROM sales s
         JOIN branches b ON b.id = s.branch_id
         LEFT JOIN users u ON u.id = s.user_id
         $where
         ORDER BY s.tanggal ASC, s.id ASC"
    );
    $stmt->execute($params);
    $csv = [];
    foreach ($stmt->fetchAll() as $r) {
        $csv[] = [
            $r['invoice'],
            date('d/m/Y H:i', strtotime($r['tanggal'])),
            $r['branch_nama'] . ' (' . $r['branch_kode'] . ')',
            $r['kasir'] ?: '-',
            ucfirst((string) $r['metode_bayar']),
            (int) $r['qty_total'],
            (float) $r['diskon'],
            (float) $r['total'],
            (float) $r['hpp'],
            (float) $r['total'] - (float) $r['hpp'],
        ];
    }
    export_csv(
        'laporan-penjualan-' . $dari . '_' . $sampai . '.csv',
        ['Invoice', 'Tanggal', 'Cabang', 'Kasir', 'Metode', 'Item', 'Diskon', 'Total', 'HPP', 'Laba Kotor'],
        $csv
    );
}

$filterQS = http_build_query(array_filter([
    'cabang' => $filterBranch > 0 ? $filterBranch : '',
    'dari'   => $dari,
    'sampai' => $sampai,
    'cari'   => $cari,
], static function ($v) { return $v !== '' && $v !== null; }));

$page_title = 'Riwayat Penjualan';
$page_sub   = 'Omset terfilter: ' . rupiah($totalFilter) . ' dari ' . angka($totalRows) . ' transaksi';
$active     = 'penjualan';
require __DIR__ . '/includes/header.php';
?>

<div class="panel">
    <div class="panel__head">
        <div><h3>Filter Transaksi</h3><p>Persempit data berdasarkan cabang, tanggal, atau nomor nota</p></div>
        <div class="flex gap-8" style="flex-wrap:wrap;justify-content:flex-end;">
            <a class="btn btn-ghost btn-sm" href="penjualan.php?<?= e($filterQS !== '' ? $filterQS . '&' : '') ?>export=csv">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Ekspor CSV
            </a>
            <a class="btn btn-ghost btn-sm" href="laporan.php?<?= e($filterQS !== '' ? $filterQS . '&' : '') ?>auto=1" onclick="window.open(this.href,'_blank','width=1100,height=800');return false;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Cetak Laporan
            </a>
            <a class="btn btn-primary btn-sm" href="pos.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Transaksi Baru
            </a>
        </div>
    </div>
    <div class="panel__body">
        <form method="get" class="flex items-center gap-12" style="flex-wrap:wrap;">
            <?php if (is_superadmin()): ?>
                <div style="min-width:200px;">
                    <select class="form-control" name="cabang">
                        <option value="">Semua Cabang</option>
                        <?php foreach ($cabangList as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $filterBranch === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <input class="form-control" type="date" name="dari" value="<?= e($dari) ?>" style="width:auto;">
            <input class="form-control" type="date" name="sampai" value="<?= e($sampai) ?>" style="width:auto;">
            <input class="form-control" type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari no. invoice..." style="width:auto;min-width:180px;">
            <button class="btn btn-primary btn-sm" type="submit">Terapkan</button>
            <a class="btn btn-ghost btn-sm" href="penjualan.php">Reset</a>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <div><h3>Daftar Transaksi</h3><p>Total omset sesuai filter: <strong style="color:var(--primary-dark)"><?= rupiah($totalFilter) ?></strong></p></div>
    </div>
    <div class="panel__body panel__body--flush">
        <?php if (!$sales): ?>
            <div class="empty-state">Tidak ada transaksi pada filter ini.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Invoice</th><th>Tanggal</th><th>Cabang</th><th>Kasir</th>
                        <th class="text-right">Item</th><th class="text-right">Total</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sales as $s):
                    $items = $itemsBySale[$s['id']] ?? [];
                    $qtyTotal = 0;
                    foreach ($items as $i) { $qtyTotal += (int) $i['qty']; }
                    $payload = [
                        'invoice' => $s['invoice'],
                        'tanggal' => tanggal_id($s['tanggal'], true),
                        'cabang'  => $s['branch_nama'] . ' (' . $s['branch_kode'] . ')',
                        'kasir'   => $s['kasir'] ?: '-',
                        'total'   => rupiah($s['total']),
                        'catatan' => $s['catatan'] ?: '-',
                        'items'   => array_map(function ($i) {
                            return [
                                'nama'     => $i['nama'],
                                'qty'      => (int) $i['qty'],
                                'harga'    => rupiah($i['harga']),
                                'subtotal' => rupiah($i['subtotal']),
                            ];
                        }, $items),
                    ];
                ?>
                    <tr>
                        <td class="mono"><?= e($s['invoice']) ?></td>
                        <td><?= tanggal_id($s['tanggal'], true) ?></td>
                        <td><?= e($s['branch_nama']) ?></td>
                        <td><?= e($s['kasir'] ?: '-') ?></td>
                        <td class="text-right"><?= angka($qtyTotal) ?></td>
                        <td class="text-right mono"><strong><?= rupiah($s['total']) ?></strong></td>
                        <td class="text-right">
                            <div class="flex gap-8" style="justify-content:flex-end;">
                                <button type="button" class="icon-btn" title="Detail"
                                        data-modal-target="modalDetail"
                                        data-payload='<?= e(json_encode($payload)) ?>'>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <a class="icon-btn" title="Cetak struk" href="struk.php?id=<?= (int) $s['id'] ?>" target="_blank" rel="noopener">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                </a>
                                <?php if (is_superadmin()): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Hapus transaksi <?= e($s['invoice']) ?>? Stok produk akan dikembalikan.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <button type="submit" class="icon-btn danger" title="Hapus">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPage > 1):
            $qs = $_GET; ?>
            <div class="flex items-center justify-between" style="padding:14px 20px;">
                <span class="text-muted" style="font-size:12.5px;">Halaman <?= $page ?> dari <?= $totalPage ?></span>
                <div class="flex gap-8">
                    <?php $qs['page'] = max(1, $page - 1); ?>
                    <a class="btn btn-ghost btn-sm" href="?<?= e(http_build_query($qs)) ?>">Sebelumnya</a>
                    <?php $qs['page'] = min($totalPage, $page + 1); ?>
                    <a class="btn btn-ghost btn-sm" href="?<?= e(http_build_query($qs)) ?>">Berikutnya</a>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal detail nota -->
<div class="modal-backdrop" id="modalDetail">
    <div class="modal modal--wide">
        <div class="modal__head">
            <h3>Detail Nota</h3>
            <button class="modal__close" data-close-modal="modalDetail">&times;</button>
        </div>
        <div class="modal__body" id="detailBody"></div>
        <div class="modal__foot">
            <button class="btn btn-ghost" data-close-modal="modalDetail">Tutup</button>
        </div>
    </div>
</div>

<?php
$extra_js = <<<'JS'
(function () {
    document.querySelectorAll('[data-modal-target="modalDetail"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var d = JSON.parse(this.getAttribute('data-payload'));
            var rows = d.items.map(function (i) {
                return '<tr><td>' + i.nama + '</td><td class="text-right">' + i.qty +
                    '</td><td class="text-right">' + i.harga +
                    '</td><td class="text-right mono">' + i.subtotal + '</td></tr>';
            }).join('');
            document.getElementById('detailBody').innerHTML =
                '<div class="grid-2" style="gap:12px;margin-bottom:16px;">' +
                '<div><div class="text-muted" style="font-size:11.5px;">Invoice</div><strong class="mono">' + d.invoice + '</strong></div>' +
                '<div><div class="text-muted" style="font-size:11.5px;">Tanggal</div><strong>' + d.tanggal + '</strong></div>' +
                '<div><div class="text-muted" style="font-size:11.5px;">Cabang</div><strong>' + d.cabang + '</strong></div>' +
                '<div><div class="text-muted" style="font-size:11.5px;">Kasir</div><strong>' + d.kasir + '</strong></div>' +
                '</div>' +
                '<div class="table-wrap" style="border:1px solid var(--border);border-radius:10px;">' +
                '<table class="data"><thead><tr><th>Produk</th><th class="text-right">Qty</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr></thead>' +
                '<tbody>' + rows + '</tbody></table></div>' +
                '<div class="cart-total"><span>Total Omset</span><strong>' + d.total + '</strong></div>' +
                '<p class="text-muted" style="font-size:12px;">Catatan: ' + d.catatan + '</p>';
        });
    });
})();
JS;
require __DIR__ . '/includes/footer.php';
