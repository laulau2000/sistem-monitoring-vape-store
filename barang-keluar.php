<?php
/**
 * Barang Keluar — transfer barang dari toko induk ke unit cabang.
 * Saat transfer disimpan, stok produk di gudang pusat otomatis BERKURANG.
 * Bisa dicetak surat jalan (PDF) yang memuat Nama Owner & Staf Penerima.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

/* Kasir hanya memakai halaman POS. */
if (is_kasir()) {
    redirect('pos.php');
}
csrf_check();

$scope = scoped_branch_id();
$user  = current_user();

/* ---- Hapus / batalkan transfer (stok dikembalikan) --------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        require_superadmin();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            db()->beginTransaction();
            $it = db()->prepare('SELECT product_id, qty FROM stock_transfer_items WHERE transfer_id = ?');
            $it->execute([$id]);
            $add = db()->prepare('UPDATE products SET stok = stok + ? WHERE id = ?');
            foreach ($it->fetchAll() as $row) {
                $add->execute([(int) $row['qty'], (int) $row['product_id']]);
            }
            db()->prepare('DELETE FROM stock_transfers WHERE id = ?')->execute([$id]);
            db()->commit();
            flash_set('success', 'Transfer dibatalkan & stok dikembalikan.');
        } catch (Throwable $ex) {
            if (db()->inTransaction()) { db()->rollBack(); }
            flash_set('error', 'Gagal membatalkan: ' . $ex->getMessage());
        }
        redirect('barang-keluar.php');
    }

    /* ---- Simpan transfer baru ---------------------------------------- */
    $fromBranch  = (int) ($_POST['from_branch_id'] ?? 0);
    $toBranch    = (int) ($_POST['to_branch_id'] ?? 0);
    $tanggal     = trim($_POST['tanggal'] ?? '');
    $ownerName   = trim($_POST['owner_name'] ?? '');
    $receiver    = trim($_POST['receiver_name'] ?? '');
    $catatan     = trim($_POST['catatan'] ?? '');

    $pids  = (array) ($_POST['product_id'] ?? []);
    $qtys  = (array) ($_POST['qty'] ?? []);
    $belis = (array) ($_POST['harga_beli'] ?? []);

    $items = [];
    foreach ($pids as $i => $pid) {
        $pid  = (int) $pid;
        $qty  = (int) ($qtys[$i] ?? 0);
        $beli = (float) ($belis[$i] ?? 0);
        if ($pid <= 0 || $qty <= 0) { continue; }
        $items[] = ['id' => $pid, 'qty' => $qty, 'harga_beli' => max($beli, 0)];
    }

    if ($fromBranch <= 0 || $toBranch <= 0) {
        flash_set('error', 'Cabang asal & tujuan wajib dipilih.');
        redirect('barang-keluar.php');
    }
    if ($fromBranch === $toBranch) {
        flash_set('error', 'Cabang asal dan tujuan tidak boleh sama.');
        redirect('barang-keluar.php');
    }
    if (!$items) {
        flash_set('error', 'Tambahkan minimal satu produk yang dikirim.');
        redirect('barang-keluar.php');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $tanggal = date('Y-m-d');
    }

    try {
        db()->beginTransaction();

        $ins = db()->prepare(
            'INSERT INTO stock_transfers (from_branch_id, to_branch_id, tanggal, no_surat_jalan, owner_name, receiver_name, catatan, total_item, total_unit, total_nilai, user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, NOW())'
        );
        $ins->execute([$fromBranch, $toBranch, $tanggal, null, $ownerName ?: null, $receiver ?: null, $catatan ?: null, (int) $user['id']]);
        $transferId = (int) db()->lastInsertId();
        $suratJalan = 'SJ-TRF-' . date('Ymd', strtotime($tanggal)) . '-' . str_pad((string) $transferId, 5, '0', STR_PAD_LEFT);
        db()->prepare('UPDATE stock_transfers SET no_surat_jalan = ? WHERE id = ?')->execute([$suratJalan, $transferId]);

        $insItem = db()->prepare('INSERT INTO stock_transfer_items (transfer_id, product_id, qty, harga_beli, subtotal) VALUES (?, ?, ?, ?, ?)');
        $updStok = db()->prepare('UPDATE products SET stok = GREATEST(stok - ?, 0) WHERE id = ?');
        $getProd = db()->prepare('SELECT id, nama, stok FROM products WHERE id = ?');

        $totalItem = 0;
        $totalUnit = 0;
        $totalNilai = 0;

        foreach ($items as $it) {
            $getProd->execute([$it['id']]);
            $prod = $getProd->fetch();
            if (!$prod) {
                throw new RuntimeException('Produk tidak ditemukan pada master.');
            }
            if ($it['qty'] > (int) $prod['stok']) {
                throw new RuntimeException('Stok "' . $prod['nama'] . '" tidak mencukupi (tersisa ' . $prod['stok'] . ').');
            }
            $subtotal = $it['qty'] * $it['harga_beli'];
            $insItem->execute([$transferId, $it['id'], $it['qty'], $it['harga_beli'], $subtotal]);
            $updStok->execute([$it['qty'], $it['id']]);
            $totalItem++;
            $totalUnit  += $it['qty'];
            $totalNilai += $subtotal;
        }

        db()->prepare('UPDATE stock_transfers SET total_item = ?, total_unit = ?, total_nilai = ? WHERE id = ?')
            ->execute([$totalItem, $totalUnit, $totalNilai, $transferId]);

        db()->commit();
        flash_set('success', 'Transfer tersimpan. Stok gudang berkurang ' . angka($totalUnit) . ' unit.');
        redirect('barang-keluar.php?cetak=' . $transferId);
    } catch (Throwable $ex) {
        if (db()->inTransaction()) { db()->rollBack(); }
        flash_set('error', 'Gagal menyimpan transfer: ' . $ex->getMessage());
        redirect('barang-keluar.php');
    }
}

/* ---- Data tampilan ----------------------------------------------- */
$produk = db()->query(
    'SELECT id, kode, nama, harga_beli, stok FROM products WHERE stok > 0 ORDER BY nama'
)->fetchAll();

$produkJson = json_encode(array_map(function ($p) {
    return [
        'id'   => (int) $p['id'],
        'nama' => $p['nama'],
        'kode' => $p['kode'],
        'beli' => (float) $p['harga_beli'],
        'stok' => (int) $p['stok'],
    ];
}, $produk), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

/* Cabang asal = cabang user (atau pilih untuk superadmin). Cabang tujuan = selain asal. */
$cabangList = db()->query('SELECT id, nama, kode, is_pusat FROM branches ORDER BY is_pusat DESC, nama')->fetchAll();

$asalDefault = $scope !== null ? $scope : 1;
$nextTransferId = (int) db()->query('SELECT COALESCE(MAX(id), 0) + 1 FROM stock_transfers')->fetchColumn();
$suratPreview = 'SJ-TRF-' . date('Ymd') . '-' . str_pad((string) $nextTransferId, 5, '0', STR_PAD_LEFT);

/* Riwayat transfer */
$sql = 'SELECT s.*, bf.nama AS cabang_asal, bt.nama AS cabang_tujuan, u.nama AS petugas
        FROM stock_transfers s
        LEFT JOIN branches bf ON bf.id = s.from_branch_id
        LEFT JOIN branches bt ON bt.id = s.to_branch_id
        LEFT JOIN users u ON u.id = s.user_id';
$par = [];
/* Staf cabang hanya melihat transfer keluar dari cabangnya. */
if ($scope !== null) {
    $sql .= ' WHERE s.from_branch_id = ?';
    $par[] = $scope;
}
$sql .= ' ORDER BY s.tanggal DESC, s.id DESC LIMIT 100';
$stmt = db()->prepare($sql);
$stmt->execute($par);
$riwayat = $stmt->fetchAll();

$itemsByTransfer = [];
if ($riwayat) {
    $ids = array_map(function ($r) { return (int) $r['id']; }, $riwayat);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $q   = db()->prepare(
        "SELECT ti.transfer_id, ti.qty, ti.harga_beli, ti.subtotal, p.kode, p.nama
         FROM stock_transfer_items ti JOIN products p ON p.id = ti.product_id
         WHERE ti.transfer_id IN ($ph) ORDER BY ti.id"
    );
    $q->execute($ids);
    foreach ($q->fetchAll() as $row) {
        $itemsByTransfer[(int) $row['transfer_id']][] = $row;
    }
}

$totalKeluarBulan = 0;
foreach ($riwayat as $r) {
    if (substr($r['tanggal'], 0, 7) === date('Y-m')) {
        $totalKeluarBulan += (int) $r['total_unit'];
    }
}

$page_title = 'Barang Keluar';
$page_sub   = 'Transfer barang ke unit cabang';
$active     = 'barang-keluar';
$autoCetak  = (int) ($_GET['cetak'] ?? 0);
require __DIR__ . '/includes/header.php';
?>

<div class="grid-kpi">
    <div class="kpi">
        <div class="kpi__icon bg-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
        </div>
        <div class="kpi__label">Transfer Tercatat</div>
        <div class="kpi__value"><?= angka(count($riwayat)) ?></div>
        <div class="kpi__meta">Dokumen transfer keluar</div>
    </div>
    <div class="kpi">
        <div class="kpi__icon bg-amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 7H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="kpi__label">Unit Keluar Bulan Ini</div>
        <div class="kpi__value"><?= angka($totalKeluarBulan) ?></div>
        <div class="kpi__meta">Periode <?= e(date('F Y')) ?></div>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <div><h3>Form Transfer Barang</h3><p>Barang dikirim dari gudang pusat ke unit cabang. Stok gudang otomatis berkurang.</p></div>
        <span class="badge badge-primary">Pengiriman</span>
    </div>
    <div class="panel__body">
        <?php if (!$produk): ?>
            <div class="empty-state">Belum ada produk dengan stok > 0. Tambahkan stok melalui menu <strong>Barang Masuk</strong> dahulu.</div>
        <?php else: ?>
        <form method="post" id="formBarangKeluar" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">

            <div class="form-grid">
                <div class="form-row">
                    <label for="bkTanggal">Tanggal Kirim</label>
                    <input class="form-control" type="date" name="tanggal" id="bkTanggal" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <div class="form-row">
                    <label for="bkSurat">No. Surat Jalan</label>
                    <input class="form-control" type="text" id="bkSurat" value="<?= e($suratPreview) ?>" data-next-id="<?= $nextTransferId ?>" readonly>
                    <small class="form-hint">Nomor final dikunci otomatis saat transfer disimpan.</small>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-row">
                    <label>Cabang Asal</label>
                    <select class="form-control" name="from_branch_id" id="bkAsal" required>
                        <?php foreach ($cabangList as $c):
                            $sel = (int) $c['id'] === $asalDefault ? ' selected' : ''; ?>
                            <option value="<?= (int) $c['id'] ?>"<?= $sel ?>><?= e($c['nama']) ?><?= (int) $c['is_pusat'] === 1 ? ' (Pusat)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="bkTujuan">Cabang Tujuan</label>
                    <select class="form-control" name="to_branch_id" id="bkTujuan" required>
                        <option value="">— Pilih cabang tujuan —</option>
                        <?php foreach ($cabangList as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= e($c['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-row">
                    <label for="bkOwner">Nama Owner</label>
                    <input class="form-control" type="text" name="owner_name" id="bkOwner" placeholder="mis. Budi Santoso" required>
                </div>
                <div class="form-row">
                    <label for="bkPenerima">Staf Unit Penerima</label>
                    <input class="form-control" type="text" name="receiver_name" id="bkPenerima" placeholder="mis. Staf Cabang Sungailiat" required>
                </div>
            </div>

            <div class="bm-items">
                <div class="bm-items__head">
                    <span>Pilih Barang yang Dikirim</span>
                </div>
                <div class="form-row bm-picker-search">
                    <input class="form-control" type="search" id="bkCariProduk" placeholder="Cari nama atau kode produk...">
                </div>
                <div class="bk-product-picker" id="bkProductPicker">
                    <?php foreach ($produk as $p): ?>
                        <label class="bk-product-option">
                            <input type="checkbox" name="pilih_produk[]" value="<?= (int) $p['id'] ?>" data-product-id="<?= (int) $p['id'] ?>">
                            <span><strong><?= e($p['nama']) ?></strong><small><?= e($p['kode']) ?> · stok <?= angka($p['stok']) ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="bm-items__head bm-selected-head"><span>Daftar Barang Dikirim</span></div>
                <div class="bm-table-wrap">
                    <table class="data bm-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th style="width:90px;">Stok</th>
                                <th style="width:110px;">Jumlah</th>
                                <th style="width:160px;">Harga Beli</th>
                                <th class="text-right" style="width:150px;">Subtotal</th>
                                <th style="width:44px;"></th>
                            </tr>
                        </thead>
                        <tbody id="bkRows"></tbody>
                    </table>
                    <div class="empty-state" id="bkEmpty" style="padding:26px;">Klik beberapa produk di atas untuk memilih barang yang akan dikirim.</div>
                </div>
            </div>

            <div class="bm-total">
                <div><span>Total Unit</span><strong id="bkTotalUnit">0</strong></div>
                <div><span>Total Nilai (HPP)</span><strong id="bkTotalNilai">Rp 0</strong></div>
            </div>

            <div class="form-row" style="margin-top:6px;">
                <label for="bkCatatan">Catatan (opsional)</label>
                <input class="form-control" type="text" name="catatan" id="bkCatatan" placeholder="mis. kondisi barang baik">
            </div>

            <div class="flex gap-8" style="justify-content:flex-end;">
                <button type="button" class="btn btn-ghost" id="bkReset">Bersihkan</button>
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/></svg>
                    Simpan & Cetak Surat Jalan
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <div><h3>Riwayat Transfer Keluar</h3><p>Dokumen transfer tersimpan</p></div>
        <span class="badge badge-gray"><?= angka(count($riwayat)) ?> dokumen</span>
    </div>
    <div class="panel__body panel__body--flush">
        <?php if (!$riwayat): ?>
            <div class="empty-state">Belum ada transfer barang tercatat.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Tanggal</th><th>No. SJ</th><th>Dari</th><th>Ke</th>
                        <th class="text-right">Item</th><th class="text-right">Unit</th><th class="text-right">Nilai</th>
                        <th>Owner</th><th>Penerima</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($riwayat as $r):
                    $detail = [
                        'id'        => (int) $r['id'],
                        'tanggal'   => $r['tanggal'],
                        'surat'     => $r['no_surat_jalan'] ?: '-',
                        'asal'      => $r['cabang_asal'] ?: '-',
                        'tujuan'    => $r['cabang_tujuan'] ?: '-',
                        'owner'     => $r['owner_name'] ?: '-',
                        'penerima'  => $r['receiver_name'] ?: '-',
                        'petugas'   => $r['petugas'] ?: '-',
                        'catatan'   => $r['catatan'] ?: '-',
                        'items'     => array_map(function ($i) {
                            return [
                                'kode' => $i['kode'], 'nama' => $i['nama'], 'qty' => (int) $i['qty'],
                                'beli' => (float) $i['harga_beli'], 'sub' => (float) $i['subtotal'],
                            ];
                        }, $itemsByTransfer[(int) $r['id']] ?? []),
                    ]; ?>
                    <tr>
                        <td class="mono"><?= e(date('d/m/Y', strtotime($r['tanggal']))) ?></td>
                        <td class="mono"><?= e($r['no_surat_jalan'] ?: '-') ?></td>
                        <td><?= e($r['cabang_asal'] ?: '-') ?></td>
                        <td><?= e($r['cabang_tujuan'] ?: '-') ?></td>
                        <td class="text-right mono"><?= angka($r['total_item']) ?></td>
                        <td class="text-right"><span class="badge badge-amber"><?= angka($r['total_unit']) ?></span></td>
                        <td class="text-right mono"><?= rupiah($r['total_nilai']) ?></td>
                        <td><?= e($r['owner_name'] ?: '-') ?></td>
                        <td><?= e($r['receiver_name'] ?: '-') ?></td>
                        <td class="text-right">
                            <div class="flex gap-8" style="justify-content:flex-end;">
                                <a class="icon-btn" title="Cetak Surat Jalan" href="cetak-surat-jalan.php?id=<?= (int) $r['id'] ?>" onclick="window.open(this.href,'_blank','width=900,height=750');return false;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                </a>
                                <button type="button" class="icon-btn" title="Detail"
                                        data-bk-detail='<?= e(json_encode($detail, JSON_UNESCAPED_UNICODE)) ?>'>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <?php if ($scope === null): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Batalkan transfer ini? Stok akan dikembalikan.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" class="icon-btn danger" title="Batalkan">
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
        <?php endif; ?>
    </div>
</div>

<!-- Modal detail transfer -->
<div class="modal-backdrop" id="modalBkDetail">
    <div class="modal">
        <div class="modal__head">
            <h3>Detail Transfer Barang</h3>
            <button type="button" class="modal__close" data-close-modal="modalBkDetail">&times;</button>
        </div>
        <div class="modal__body">
            <div class="bm-detail-meta" id="bkDetailMeta"></div>
            <div class="table-wrap">
                <table class="data" id="bkDetailTable">
                    <thead><tr><th>Produk</th><th class="text-right">Jml</th><th class="text-right">Harga Beli</th><th class="text-right">Subtotal</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div class="modal__foot">
            <button type="button" class="btn btn-ghost" data-close-modal="modalBkDetail">Tutup</button>
        </div>
    </div>
</div>

<?php
$produkJsonSafe = $produkJson;
$extra_js = <<<JS
(function () {
    var PRODUK = $produkJsonSafe;
    var rowsBox   = document.getElementById('bkRows');
    var empty     = document.getElementById('bkEmpty');
    var totalUnit = document.getElementById('bkTotalUnit');
    var totalNilai= document.getElementById('bkTotalNilai');
    var picker    = document.getElementById('bkProductPicker');
    var tanggal   = document.getElementById('bkTanggal');
    var nomorSJ   = document.getElementById('bkSurat');

    if (!rowsBox || !picker) { return; }

    function rupiah(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }

    function hitungTotal() {
        var unit = 0, nilai = 0;
        rowsBox.querySelectorAll('tr').forEach(function (tr) {
            var qty  = parseInt(tr.querySelector('[name="qty[]"]').value, 10) || 0;
            var beli = parseFloat(tr.querySelector('[name="harga_beli[]"]').value) || 0;
            var sub  = qty * beli;
            tr.querySelector('.bk-sub').textContent = rupiah(sub);
            unit += qty;
            nilai += sub;
        });
        totalUnit.textContent = unit;
        totalNilai.textContent = rupiah(nilai);
        empty.style.display = rowsBox.children.length ? 'none' : 'block';
    }

    function tambahBaris(p) {
        var tr = document.createElement('tr');
        tr.dataset.productId = p.id;
        tr.innerHTML =
            '<td><input type="hidden" name="product_id[]"><strong class="bk-product-name"></strong><div class="bk-product-code mono"></div></td>' +
            '<td class="bk-stok mono"></td>' +
            '<td><input class="form-control" type="number" name="qty[]" min="1" value="1" inputmode="numeric" required></td>' +
            '<td><input class="form-control" type="number" name="harga_beli[]" min="0" step="500" value="0" inputmode="numeric"></td>' +
            '<td class="text-right mono bk-sub">Rp 0</td>' +
            '<td class="text-right"><button type="button" class="icon-btn danger bk-del" title="Hapus baris">&times;</button></td>';
        tr.querySelector('[name="product_id[]"]').value = p.id;
        tr.querySelector('.bk-product-name').textContent = p.nama;
        tr.querySelector('.bk-product-code').textContent = p.kode;
        tr.querySelector('.bk-stok').textContent = p.stok;
        tr.querySelector('[name="harga_beli[]"]').value = p.beli;
        rowsBox.appendChild(tr);
        tr.querySelector('[name="qty[]"]').addEventListener('input', hitungTotal);
        tr.querySelector('[name="harga_beli[]"]').addEventListener('input', hitungTotal);
        tr.querySelector('.bk-del').addEventListener('click', function () {
            tr.remove();
            var checkbox = picker.querySelector('[data-product-id="' + p.id + '"]');
            if (checkbox) { checkbox.checked = false; }
            hitungTotal();
        });
        hitungTotal();
    }

    picker.querySelectorAll('[data-product-id]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var product = PRODUK.find(function (p) { return String(p.id) === checkbox.dataset.productId; });
            if (!product) { return; }
            if (checkbox.checked) {
                tambahBaris(product);
            } else {
                var row = rowsBox.querySelector('tr[data-product-id="' + product.id + '"]');
                if (row) { row.remove(); }
                hitungTotal();
            }
        });
    });
    document.getElementById('bkCariProduk').addEventListener('input', function () {
        var query = this.value.trim().toLowerCase();
        picker.querySelectorAll('.bk-product-option').forEach(function (option) {
            option.hidden = option.textContent.toLowerCase().indexOf(query) === -1;
        });
    });
    tanggal.addEventListener('change', function () {
        nomorSJ.value = 'SJ-TRF-' + this.value.replace(/-/g, '') + '-' +
            String(nomorSJ.dataset.nextId).padStart(5, '0');
    });
    document.getElementById('bkReset').addEventListener('click', function () {
        rowsBox.innerHTML = '';
        document.getElementById('formBarangKeluar').reset();
        hitungTotal();
    });
    document.getElementById('formBarangKeluar').addEventListener('submit', function (event) {
        if (!rowsBox.children.length) {
            event.preventDefault();
            window.alert('Pilih minimal satu produk yang akan dikirim.');
        }
    });

    /* Detail transfer */
    document.querySelectorAll('[data-bk-detail]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var d = JSON.parse(btn.getAttribute('data-bk-detail'));
            var meta = document.getElementById('bkDetailMeta');
            meta.innerHTML =
                '<div><span>Tanggal</span><strong>' + d.tanggal + '</strong></div>' +
                '<div><span>No. Surat Jalan</span><strong>' + d.surat + '</strong></div>' +
                '<div><span>Dari</span><strong>' + d.asal + '</strong></div>' +
                '<div><span>Ke</span><strong>' + d.tujuan + '</strong></div>' +
                '<div><span>Owner</span><strong>' + d.owner + '</strong></div>' +
                '<div><span>Penerima</span><strong>' + d.penerima + '</strong></div>' +
                '<div><span>Petugas</span><strong>' + d.petugas + '</strong></div>' +
                '<div><span>Catatan</span><strong>' + d.catatan + '</strong></div>';
            var tb = document.querySelector('#bkDetailTable tbody');
            tb.innerHTML = '';
            d.items.forEach(function (i) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><strong>' + i.nama + '</strong><div class="mono" style="color:var(--text-muted);font-size:11px;">' + i.kode + '</div></td>' +
                    '<td class="text-right">' + i.qty + '</td>' +
                    '<td class="text-right mono">' + rupiah(i.beli) + '</td>' +
                    '<td class="text-right mono">' + rupiah(i.sub) + '</td>';
                tb.appendChild(tr);
            });
            window.openModal('modalBkDetail');
        });
    });

})();
JS;
if ($autoCetak > 0) {
    $extra_js .= "\nwindow.open('cetak-surat-jalan.php?id=" . $autoCetak . "', '_blank');";
}
require __DIR__ . '/includes/footer.php';
?>
