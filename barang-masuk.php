<?php
/**
 * Barang Masuk — penerimaan barang dari expedisi.
 * Input dicatat TERPISAH dari master produk, namun stok produk otomatis bertambah.
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

function bm_kode_prefix(string $categoryName): string
{
    $words = preg_split('/[^A-Z0-9]+/', strtoupper($categoryName), -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) {
        return 'PRD';
    }
    if (count($words) === 1) {
        return substr($words[0], 0, 3);
    }
    return substr(implode('', array_map(function ($word) {
        return $word[0];
    }, $words)), 0, 4);
}

function bm_kode_produk(int $categoryId, array &$nextCode): string
{
    static $categoryNames = [];
    if (!array_key_exists($categoryId, $categoryNames)) {
        $query = db()->prepare('SELECT nama FROM categories WHERE id = ?');
        $query->execute([$categoryId]);
        $categoryNames[$categoryId] = (string) ($query->fetchColumn() ?: '');
    }

    $prefix = bm_kode_prefix($categoryNames[$categoryId]);
    if (!isset($nextCode[$prefix])) {
        $query = db()->prepare('SELECT kode FROM products WHERE kode LIKE ?');
        $query->execute([$prefix . '-%']);
        $max = 0;
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $existingCode) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $existingCode, $match)) {
                $max = max($max, (int) $match[1]);
            }
        }
        $nextCode[$prefix] = $max;
    }

    $nextCode[$prefix]++;
    return $prefix . '-' . str_pad((string) $nextCode[$prefix], 3, '0', STR_PAD_LEFT);
}

/* ---- Simpan / hapus penerimaan ----------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        require_superadmin();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            db()->beginTransaction();
            $it = db()->prepare('SELECT product_id, qty FROM goods_receipt_items WHERE receipt_id = ?');
            $it->execute([$id]);
            $rev = db()->prepare('UPDATE products SET stok = GREATEST(stok - ?, 0) WHERE id = ?');
            foreach ($it->fetchAll() as $row) {
                $rev->execute([(int) $row['qty'], (int) $row['product_id']]);
            }
            db()->prepare('DELETE FROM goods_receipts WHERE id = ?')->execute([$id]);
            db()->commit();
            flash_set('success', 'Data barang masuk dibatalkan & stok dikembalikan.');
        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash_set('error', 'Gagal membatalkan: ' . $ex->getMessage());
        }
        redirect('barang-masuk.php');
    }

    $branchId   = $scope !== null ? $scope : (int) ($_POST['branch_id'] ?? 0);
    $supplierId = (int) ($_POST['supplier_id'] ?? 0);
    $tanggal    = trim($_POST['tanggal'] ?? '');
    $expedisi   = trim($_POST['expedisi'] ?? '');
    $suratJalan = trim($_POST['no_surat_jalan'] ?? '');
    $catatan    = trim($_POST['catatan'] ?? '');

    $pids   = (array) ($_POST['product_id'] ?? []);
    $qtys   = (array) ($_POST['qty'] ?? []);
    $belis  = (array) ($_POST['harga_beli'] ?? []);
    $isNew  = (array) ($_POST['is_new'] ?? []);
    $newNama= (array) ($_POST['new_nama'] ?? []);
    $newKat = (array) ($_POST['new_category_id'] ?? []);
    $newHarga=(array) ($_POST['new_harga'] ?? []);

    $items = [];
    foreach ($pids as $i => $pid) {
        $qty  = (int) ($qtys[$i] ?? 0);
        $beli = (float) ($belis[$i] ?? 0);
        if ($qty <= 0) { continue; }

        $baru = !empty($isNew[$i]);
        if ($baru) {
            $nama = trim($newNama[$i] ?? '');
            if ($nama === '') {
                continue;
            }
            $items[] = [
                'new'        => true,
                'nama'       => $nama,
                'category_id'=> (int) ($newKat[$i] ?? 0) ?: null,
                'harga'      => (float) ($newHarga[$i] ?? 0),
                'qty'        => $qty,
                'harga_beli' => max($beli, 0),
            ];
        } else {
            $pid = (int) $pid;
            if ($pid <= 0) { continue; }
            $items[] = [
                'new'        => false,
                'id'         => $pid,
                'qty'        => $qty,
                'harga_beli' => max($beli, 0),
            ];
        }
    }

    if ($branchId <= 0) {
        flash_set('error', 'Cabang penerimaan belum dipilih.');
        redirect('barang-masuk.php');
    }
    if (!$items) {
        flash_set('error', 'Tambahkan minimal satu produk yang diterima.');
        redirect('barang-masuk.php');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $tanggal = date('Y-m-d');
    }

    try {
        db()->beginTransaction();

        $ins = db()->prepare(
            'INSERT INTO goods_receipts (branch_id, supplier_id, tanggal, expedisi, no_surat_jalan, catatan, total_item, total_unit, total_nilai, user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, ?, NOW())'
        );
        $ins->execute([$branchId, $supplierId > 0 ? $supplierId : null, $tanggal, $expedisi ?: null, $suratJalan ?: null, $catatan ?: null, (int) $user['id']]);
        $receiptId = (int) db()->lastInsertId();

        $insItem  = db()->prepare('INSERT INTO goods_receipt_items (receipt_id, product_id, qty, harga_beli, subtotal) VALUES (?, ?, ?, ?, ?)');
        $updStok  = db()->prepare('UPDATE products SET stok = stok + ? WHERE id = ?');
        $updHpp   = db()->prepare('UPDATE products SET harga_beli = ? WHERE id = ?');
        $getProd  = db()->prepare('SELECT id, nama FROM products WHERE id = ?');
        $insProd  = db()->prepare('INSERT INTO products (kode, nama, category_id, harga, harga_beli, stok, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())');

        $totalItem = 0;
        $totalUnit = 0;
        $totalNilai = 0;
        $nextCode = [];

        foreach ($items as $it) {
            if (!empty($it['new'])) {
                if (!$it['category_id']) {
                    throw new RuntimeException('Pilih kategori untuk setiap produk baru agar kode dapat dibuat otomatis.');
                }
                $kode = bm_kode_produk((int) $it['category_id'], $nextCode);
                $insProd->execute([$kode, $it['nama'], $it['category_id'], $it['harga'], $it['harga_beli']]);
                $productId = (int) db()->lastInsertId();
            } else {
                $getProd->execute([$it['id']]);
                $prod = $getProd->fetch();
                if (!$prod) {
                    throw new RuntimeException('Produk tidak ditemukan pada master.');
                }
                $productId = (int) $it['id'];
            }
            $subtotal = $it['qty'] * $it['harga_beli'];
            $insItem->execute([$receiptId, $productId, $it['qty'], $it['harga_beli'], $subtotal]);
            $updStok->execute([$it['qty'], $productId]);
            if ($it['harga_beli'] > 0) {
                $updHpp->execute([$it['harga_beli'], $productId]);
            }
            $totalItem++;
            $totalUnit  += $it['qty'];
            $totalNilai += $subtotal;
        }

        db()->prepare('UPDATE goods_receipts SET total_item = ?, total_unit = ?, total_nilai = ? WHERE id = ?')
            ->execute([$totalItem, $totalUnit, $totalNilai, $receiptId]);

        db()->commit();
        flash_set('success', 'Barang masuk tersimpan. Stok produk otomatis bertambah ' . angka($totalUnit) . ' unit.');
    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash_set('error', 'Gagal menyimpan barang masuk: ' . $ex->getMessage());
    }
    redirect('barang-masuk.php');
}

/* ---- Data tampilan ----------------------------------------------- */
$produk = db()->query(
    'SELECT p.id, p.kode, p.nama, p.category_id, p.harga_beli, p.stok, c.nama AS kategori
     FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.nama'
)->fetchAll();

$produkJson = json_encode(array_map(function ($p) {
    return [
        'id'    => (int) $p['id'],
        'nama'  => $p['nama'],
        'kode'  => $p['kode'],
        'kategori' => $p['kategori'] ?: 'Tanpa kategori',
        'beli'  => (float) $p['harga_beli'],
        'stok'  => (int) $p['stok'],
    ];
}, $produk), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$cabangList = [];
if ($scope === null) {
    $cabangList = db()->query('SELECT id, nama, kode FROM branches ORDER BY is_pusat DESC, nama')->fetchAll();
}

$kategoriList = db()->query('SELECT id, nama FROM categories ORDER BY nama')->fetchAll();
$kategoriJson = json_encode(array_map(function ($k) {
    return ['id' => (int) $k['id'], 'nama' => $k['nama']];
}, $kategoriList), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$supplierList = db()->query('SELECT id, nama, kontak FROM suppliers ORDER BY nama')->fetchAll();

$sql = 'SELECT g.*, sp.nama AS supplier, b.nama AS cabang, u.nama AS petugas
        FROM goods_receipts g
        LEFT JOIN suppliers sp ON sp.id = g.supplier_id
        LEFT JOIN branches b ON b.id = g.branch_id
        LEFT JOIN users u ON u.id = g.user_id';
$par = [];
if ($scope !== null) {
    $sql .= ' WHERE g.branch_id = ?';
    $par[] = $scope;
}
$sql .= ' ORDER BY g.tanggal DESC, g.id DESC LIMIT 100';
$stmt = db()->prepare($sql);
$stmt->execute($par);
$riwayat = $stmt->fetchAll();

/* Ambil item untuk seluruh riwayat yang tampil */
$itemsByReceipt = [];
if ($riwayat) {
    $ids = array_map(function ($r) { return (int) $r['id']; }, $riwayat);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $q   = db()->prepare(
        "SELECT gi.receipt_id, gi.qty, gi.harga_beli, gi.subtotal, p.kode, p.nama
         FROM goods_receipt_items gi JOIN products p ON p.id = gi.product_id
         WHERE gi.receipt_id IN ($ph) ORDER BY gi.id"
    );
    $q->execute($ids);
    foreach ($q->fetchAll() as $row) {
        $itemsByReceipt[(int) $row['receipt_id']][] = $row;
    }
}

$totalPenerimaanBulan = 0;
foreach ($riwayat as $r) {
    if (substr($r['tanggal'], 0, 7) === date('Y-m')) {
        $totalPenerimaanBulan += (int) $r['total_unit'];
    }
}

$page_title = 'Barang Masuk';
$page_sub   = 'Penerimaan barang dari expedisi';
$active     = 'barang-masuk';
require __DIR__ . '/includes/header.php';
?>

<div class="grid-kpi">
    <div class="kpi">
        <div class="kpi__icon bg-blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
        </div>
        <div class="kpi__label">Penerimaan Tercatat</div>
        <div class="kpi__value"><?= angka(count($riwayat)) ?></div>
        <div class="kpi__meta">Dokumen barang masuk tersimpan</div>
    </div>
    <div class="kpi">
        <div class="kpi__icon bg-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 7H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="kpi__label">Unit Masuk Bulan Ini</div>
        <div class="kpi__value"><?= angka($totalPenerimaanBulan) ?></div>
        <div class="kpi__meta">Periode <?= e(date('F Y')) ?></div>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <div><h3>Input Barang Masuk</h3><p>Pilih beberapa produk terdaftar sekaligus, atau tambahkan produk baru. Kode produk baru dibuat otomatis mengikuti kategori.</p></div>
        <span class="badge badge-blue">Penerimaan</span>
    </div>
    <div class="panel__body">
        <form method="post" id="formBarangMasuk" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">

            <div class="form-grid">
                <div class="form-row">
                    <label for="bmTanggal">Tanggal Terima</label>
                    <input class="form-control" type="date" name="tanggal" id="bmTanggal" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <?php if ($scope === null): ?>
                <div class="form-row">
                    <label for="bmCabang">Cabang Penerima</label>
                    <select class="form-control" name="branch_id" id="bmCabang" required>
                        <option value="">— Pilih cabang —</option>
                        <?php foreach ($cabangList as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= e($c['nama']) ?> (<?= e($c['kode']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div class="form-row">
                    <label>Cabang Penerima</label>
                    <input class="form-control" type="text" value="<?= e($user['branch_nama']) ?>" disabled>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-grid">
                <div class="form-row">
                    <label for="bmSupplier">Supplier</label>
                    <select class="form-control" name="supplier_id" id="bmSupplier">
                        <option value="">— Tanpa supplier —</option>
                        <?php foreach ($supplierList as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['nama']) ?><?= $s['kontak'] ? ' — ' . e($s['kontak']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">Kelola daftar supplier di menu <strong>Supplier</strong>.</div>
                </div>
                <div class="form-row">
                    <label for="bmExpedisi">Ekspedisi</label>
                    <input class="form-control" type="text" name="expedisi" id="bmExpedisi" placeholder="mis. JNE, SiCepat">
                </div>
                <div class="form-row">
                    <label for="bmSurat">No. Surat Jalan</label>
                    <input class="form-control" type="text" name="no_surat_jalan" id="bmSurat" placeholder="mis. SJ-00123">
                </div>
            </div>

            <div class="bm-items">
                <div class="bm-items__head">
                    <span>Pilih Produk yang Diterima</span>
                    <button type="button" class="btn btn-ghost btn-sm" id="bmTambahProdukBaru">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Produk Baru
                    </button>
                </div>
                <div class="form-row bm-picker-search">
                    <input class="form-control" type="search" id="bmCariProduk" placeholder="Cari nama atau kode produk...">
                </div>
                <div class="bm-product-picker" id="bmProductPicker"></div>
                <div class="bm-items__head bm-selected-head"><span>Daftar Barang Diterima</span></div>
                <div class="bm-table-wrap">
                    <table class="data bm-table">
                        <thead>
                            <tr>
                                <th>Detail Produk</th>
                                <th style="width:110px;">Jumlah</th>
                                <th style="width:160px;">Harga Beli</th>
                                <th class="text-right" style="width:150px;">Subtotal</th>
                                <th style="width:44px;"></th>
                            </tr>
                        </thead>
                        <tbody id="bmRows"></tbody>
                    </table>
                    <div class="empty-state" id="bmEmpty" style="padding:26px;">Pilih produk di atas atau klik <strong>Produk Baru</strong>.</div>
                </div>
            </div>

            <div class="bm-total">
                <div><span>Total Unit</span><strong id="bmTotalUnit">0</strong></div>
                <div><span>Total Nilai (HPP)</span><strong id="bmTotalNilai">Rp 0</strong></div>
            </div>

            <div class="form-row" style="margin-top:6px;">
                <label for="bmCatatan">Catatan (opsional)</label>
                <input class="form-control" type="text" name="catatan" id="bmCatatan" placeholder="mis. kondisi barang baik, 1 dus penyok">
            </div>

            <div class="flex gap-8" style="justify-content:flex-end;">
                <button type="button" class="btn btn-ghost" id="bmReset">Bersihkan</button>
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Simpan Barang Masuk
                </button>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <div><h3>Riwayat Barang Masuk</h3><p>Dokumen penerimaan tersimpan terpisah dari master produk</p></div>
        <span class="badge badge-gray"><?= angka(count($riwayat)) ?> dokumen</span>
    </div>
    <div class="panel__body panel__body--flush">
        <?php if (!$riwayat): ?>
            <div class="empty-state">Belum ada penerimaan barang tercatat.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Tanggal</th><th>Supplier / Ekspedisi</th><th>No. Surat Jalan</th><th>Cabang</th>
                        <th class="text-right">Item</th><th class="text-right">Unit</th><th class="text-right">Nilai (HPP)</th>
                        <th>Petugas</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($riwayat as $r):
                    $detail = [
                        'id'        => (int) $r['id'],
                        'tanggal'   => $r['tanggal'],
                        'supplier'  => $r['supplier'] ?: '-',
                        'expedisi'  => $r['expedisi'] ?: '-',
                        'surat'     => $r['no_surat_jalan'] ?: '-',
                        'cabang'    => $r['cabang'] ?: '-',
                        'petugas'   => $r['petugas'] ?: '-',
                        'catatan'   => $r['catatan'] ?: '-',
                        'items'     => array_map(function ($i) {
                            return [
                                'kode' => $i['kode'], 'nama' => $i['nama'], 'qty' => (int) $i['qty'],
                                'beli' => (float) $i['harga_beli'], 'sub' => (float) $i['subtotal'],
                            ];
                        }, $itemsByReceipt[(int) $r['id']] ?? []),
                    ]; ?>
                    <tr>
                        <td class="mono"><?= e(date('d/m/Y', strtotime($r['tanggal']))) ?></td>
                        <td><strong><?= e($r['supplier'] ?: ($r['expedisi'] ?: '-')) ?></strong><?php if ($r['supplier'] && $r['expedisi']): ?><div style="color:var(--text-muted);font-size:11.5px;"><?= e($r['expedisi']) ?></div><?php endif; ?></td>
                        <td class="mono"><?= e($r['no_surat_jalan'] ?: '-') ?></td>
                        <td><?= e($r['cabang'] ?: '-') ?></td>
                        <td class="text-right mono"><?= angka($r['total_item']) ?></td>
                        <td class="text-right"><span class="badge badge-success"><?= angka($r['total_unit']) ?></span></td>
                        <td class="text-right mono"><?= rupiah($r['total_nilai']) ?></td>
                        <td><?= e($r['petugas'] ?: '-') ?></td>
                        <td class="text-right">
                            <div class="flex gap-8" style="justify-content:flex-end;">
                                <button type="button" class="icon-btn" title="Detail"
                                        data-bm-detail='<?= e(json_encode($detail, JSON_UNESCAPED_UNICODE)) ?>'>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <?php if ($scope === null): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Batalkan penerimaan ini? Stok akan dikurangi kembali.');">
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

<!-- Modal detail penerimaan -->
<div class="modal-backdrop" id="modalBmDetail">
    <div class="modal">
        <div class="modal__head">
            <h3>Detail Barang Masuk</h3>
            <button type="button" class="modal__close" data-close-modal="modalBmDetail">&times;</button>
        </div>
        <div class="modal__body">
            <div class="bm-detail-meta" id="bmDetailMeta"></div>
            <div class="table-wrap">
                <table class="data" id="bmDetailTable">
                    <thead><tr><th>Produk</th><th class="text-right">Jml</th><th class="text-right">Harga Beli</th><th class="text-right">Subtotal</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div class="modal__foot">
            <button type="button" class="btn btn-ghost" data-close-modal="modalBmDetail">Tutup</button>
        </div>
    </div>
</div>

<?php
$produkJsonSafe = $produkJson;
$kategoriJsonSafe = $kategoriJson;
$extra_js = <<<JS
(function () {
    var PRODUK = $produkJsonSafe;
    var KATEGORI = $kategoriJsonSafe;
    var rowsBox   = document.getElementById('bmRows');
    var empty     = document.getElementById('bmEmpty');
    var totalUnit = document.getElementById('bmTotalUnit');
    var totalNilai= document.getElementById('bmTotalNilai');
    var picker    = document.getElementById('bmProductPicker');

    if (!rowsBox || !picker) { return; }

    function rupiah(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }

    function prefixKategori(nama) {
        var kata = String(nama || '').toUpperCase().match(/[A-Z0-9]+/g) || [];
        if (!kata.length) { return 'PRD'; }
        return kata.length === 1 ? kata[0].slice(0, 3) : kata.map(function (k) { return k.charAt(0); }).join('').slice(0, 4);
    }

    function kodePreview(categoryId) {
        var kategori = KATEGORI.find(function (k) { return String(k.id) === String(categoryId); });
        var prefix = prefixKategori(kategori ? kategori.nama : '');
        var max = 0;
        PRODUK.forEach(function (p) {
            var match = String(p.kode).match(new RegExp('^' + prefix + '-(\\\\d+)$'));
            if (match) { max = Math.max(max, parseInt(match[1], 10) || 0); }
        });
        rowsBox.querySelectorAll('.bm-code-preview').forEach(function (input) {
            var match = String(input.value).match(new RegExp('^' + prefix + '-(\\\\d+)$'));
            if (match) { max = Math.max(max, parseInt(match[1], 10) || 0); }
        });
        return prefix + '-' + String(max + 1).padStart(3, '0');
    }

    function refreshPicker() {
        picker.innerHTML = '';
        if (!PRODUK.length) {
            picker.innerHTML = '<div class="empty-state">Belum ada produk terdaftar. Tambahkan produk baru terlebih dahulu.</div>';
            return;
        }
        PRODUK.forEach(function (p) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'bm-product-option';
            button.dataset.productId = p.id;
            button.setAttribute('aria-pressed', 'false');
            var name = document.createElement('strong');
            name.textContent = p.nama;
            var meta = document.createElement('span');
            meta.textContent = p.kode + ' · ' + p.kategori + ' · stok ' + p.stok;
            button.appendChild(name);
            button.appendChild(meta);
            button.addEventListener('click', function () {
                var row = rowsBox.querySelector('tr[data-product-id="' + p.id + '"]');
                if (row) {
                    row.remove();
                    button.classList.remove('selected');
                    button.setAttribute('aria-pressed', 'false');
                } else {
                    tambahProdukTerdaftar(p);
                    button.classList.add('selected');
                    button.setAttribute('aria-pressed', 'true');
                }
                hitungTotal();
            });
            picker.appendChild(button);
        });
    }

    function hitungTotal() {
        var unit = 0, nilai = 0;
        rowsBox.querySelectorAll('tr').forEach(function (tr) {
            var qty  = parseInt(tr.querySelector('[name="qty[]"]').value, 10) || 0;
            var beli = parseFloat(tr.querySelector('[name="harga_beli[]"]').value) || 0;
            var sub  = qty * beli;
            tr.querySelector('.bm-sub').textContent = rupiah(sub);
            unit += qty;
            nilai += sub;
        });
        totalUnit.textContent = unit;
        totalNilai.textContent = rupiah(nilai);
        empty.style.display = rowsBox.children.length ? 'none' : 'block';
    }

    function pasangEventBaris(tr) {
        tr.querySelector('[name="qty[]"]').addEventListener('input', hitungTotal);
        tr.querySelector('[name="harga_beli[]"]').addEventListener('input', hitungTotal);
        tr.querySelector('.bm-del').addEventListener('click', function () {
            var productId = tr.dataset.productId;
            tr.remove();
            if (productId) {
                var button = picker.querySelector('[data-product-id="' + productId + '"]');
                if (button) {
                    button.classList.remove('selected');
                    button.setAttribute('aria-pressed', 'false');
                }
            }
            hitungTotal();
        });
    }

    function tambahProdukTerdaftar(p) {
        var tr = document.createElement('tr');
        tr.dataset.productId = p.id;
        tr.innerHTML =
            '<td><input type="hidden" name="product_id[]"><input type="hidden" name="is_new[]"><input type="hidden" name="new_nama[]" value=""><input type="hidden" name="new_category_id[]" value=""><input type="hidden" name="new_harga[]" value="0"><strong class="bm-product-name"></strong><div class="bm-product-meta"></div></td>' +
            '<td><input class="form-control" type="number" name="qty[]" min="1" value="1" inputmode="numeric" required></td>' +
            '<td><input class="form-control" type="number" name="harga_beli[]" min="0" step="500" value="0" inputmode="numeric"></td>' +
            '<td class="text-right mono bm-sub">Rp 0</td>' +
            '<td class="text-right"><button type="button" class="icon-btn danger bm-del" title="Hapus baris">&times;</button></td>';
        tr.querySelector('[name="product_id[]"]').value = p.id;
        tr.querySelector('.bm-product-name').textContent = p.nama;
        tr.querySelector('.bm-product-meta').textContent = p.kode + ' · stok ' + p.stok;
        tr.querySelector('[name="harga_beli[]"]').value = p.beli;
        rowsBox.appendChild(tr);
        pasangEventBaris(tr);
        hitungTotal();
    }

    function tambahProdukBaru() {
        var tr = document.createElement('tr');
        tr.className = 'bm-new-product-row';
        tr.innerHTML =
            '<td>' +
                '<input type="hidden" name="product_id[]" value="">' +
                '<input type="hidden" name="is_new[]" value="1">' +
                '<input class="form-control" type="text" name="new_nama[]" placeholder="Nama produk baru" required>' +
                '<div class="bm-new-product-fields">' +
                    '<select class="form-control" name="new_category_id[]" required><option value="">— Pilih kategori —</option></select>' +
                    '<input class="form-control bm-code-preview" type="text" readonly placeholder="Kode otomatis">' +
                    '<input class="form-control" type="number" name="new_harga[]" min="0" step="500" value="0" placeholder="Harga jual (opsional)">' +
                '</div>' +
            '</td>' +
            '<td><input class="form-control" type="number" name="qty[]" min="1" value="1" inputmode="numeric" required></td>' +
            '<td><input class="form-control" type="number" name="harga_beli[]" min="0" step="500" value="0" inputmode="numeric"></td>' +
            '<td class="text-right mono bm-sub">Rp 0</td>' +
            '<td class="text-right"><button type="button" class="icon-btn danger bm-del" title="Hapus baris">&times;</button></td>';
        var category = tr.querySelector('[name="new_category_id[]"]');
        KATEGORI.forEach(function (k) {
            var option = document.createElement('option');
            option.value = k.id;
            option.textContent = k.nama;
            category.appendChild(option);
        });
        category.addEventListener('change', function () {
            var codeInput = tr.querySelector('.bm-code-preview');
            codeInput.value = '';
            codeInput.value = category.value ? kodePreview(category.value) : '';
        });
        rowsBox.appendChild(tr);
        pasangEventBaris(tr);
        hitungTotal();
    }

    document.getElementById('bmTambahProdukBaru').addEventListener('click', tambahProdukBaru);
    document.getElementById('bmCariProduk').addEventListener('input', function () {
        var query = this.value.trim().toLowerCase();
        picker.querySelectorAll('.bm-product-option').forEach(function (button) {
            button.hidden = button.textContent.toLowerCase().indexOf(query) === -1;
        });
    });
    document.getElementById('bmReset').addEventListener('click', function () {
        rowsBox.innerHTML = '';
        document.getElementById('formBarangMasuk').reset();
        picker.querySelectorAll('.bm-product-option').forEach(function (button) {
            button.classList.remove('selected');
            button.setAttribute('aria-pressed', 'false');
        });
        hitungTotal();
    });
    document.getElementById('formBarangMasuk').addEventListener('submit', function (event) {
        if (!rowsBox.children.length) {
            event.preventDefault();
            window.alert('Pilih atau tambahkan minimal satu produk yang diterima.');
        }
    });

    /* Detail penerimaan */
    document.querySelectorAll('[data-bm-detail]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var d = JSON.parse(btn.getAttribute('data-bm-detail'));
            var meta = document.getElementById('bmDetailMeta');
            meta.innerHTML =
                '<div><span>Tanggal</span><strong>' + d.tanggal + '</strong></div>' +
                '<div><span>Supplier</span><strong>' + d.supplier + '</strong></div>' +
                '<div><span>Ekspedisi</span><strong>' + d.expedisi + '</strong></div>' +
                '<div><span>No. Surat Jalan</span><strong>' + d.surat + '</strong></div>' +
                '<div><span>Cabang</span><strong>' + d.cabang + '</strong></div>' +
                '<div><span>Petugas</span><strong>' + d.petugas + '</strong></div>' +
                '<div><span>Catatan</span><strong>' + d.catatan + '</strong></div>';
            var tb = document.querySelector('#bmDetailTable tbody');
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
            window.openModal('modalBmDetail');
        });
    });

    refreshPicker();
    hitungTotal();
})();
JS;
require __DIR__ . '/includes/footer.php';
?>
