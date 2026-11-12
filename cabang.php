<?php
/**
 * Cabang — master data cabang (khusus superadmin).
 */
require_once __DIR__ . '/includes/functions.php';
require_superadmin();
csrf_check();

/* ---- Simpan / ubah / hapus --------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = (int) ($_POST['id'] ?? 0);
        $kode    = strtoupper(trim($_POST['kode'] ?? ''));
        $nama    = trim($_POST['nama'] ?? '');
        $alamat  = trim($_POST['alamat'] ?? '');
        $telepon = trim($_POST['telepon'] ?? '');
        $isPusat = isset($_POST['is_pusat']) ? 1 : 0;

        if ($kode === '' || $nama === '') {
            flash_set('error', 'Kode dan nama cabang wajib diisi.');
            redirect('cabang.php');
        }
        try {
            if ($id > 0) {
                db()->prepare('UPDATE branches SET kode=?, nama=?, alamat=?, telepon=?, is_pusat=? WHERE id=?')
                    ->execute([$kode, $nama, $alamat, $telepon, $isPusat, $id]);
                flash_set('success', 'Cabang "' . $nama . '" berhasil diperbarui.');
            } else {
                db()->prepare('INSERT INTO branches (kode, nama, alamat, telepon, is_pusat, created_at) VALUES (?, ?, ?, ?, ?, NOW())')
                    ->execute([$kode, $nama, $alamat, $telepon, $isPusat]);
                flash_set('success', 'Cabang "' . $nama . '" berhasil ditambahkan.');
            }
        } catch (Throwable $ex) {
            flash_set('error', 'Gagal menyimpan cabang: ' . $ex->getMessage());
        }
        redirect('cabang.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $sale = db()->prepare('SELECT COUNT(*) FROM sales WHERE branch_id = ?');
        $sale->execute([$id]);
        $usr = db()->prepare('SELECT COUNT(*) FROM users WHERE branch_id = ?');
        $usr->execute([$id]);
        if ((int) $sale->fetchColumn() > 0 || (int) $usr->fetchColumn() > 0) {
            flash_set('error', 'Cabang tidak dapat dihapus karena masih memiliki transaksi atau pengguna.');
        } else {
            db()->prepare('DELETE FROM branches WHERE id = ?')->execute([$id]);
            flash_set('success', 'Cabang berhasil dihapus.');
        }
        redirect('cabang.php');
    }
}

/* ---- Data -------------------------------------------------------- */
$cabang = db()->query(
    'SELECT b.*,
        (SELECT COUNT(*) FROM sales s WHERE s.branch_id = b.id) AS jml_trx,
        (SELECT COALESCE(SUM(s.total),0) FROM sales s WHERE s.branch_id = b.id) AS omset,
        (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.id) AS jml_user
     FROM branches b ORDER BY b.is_pusat DESC, b.nama'
)->fetchAll();

$page_title = 'Cabang';
$page_sub   = count($cabang) . ' cabang terdaftar';
$active     = 'cabang';
require __DIR__ . '/includes/header.php';
?>

<div class="panel">
    <div class="panel__head">
        <div><h3>Jaringan Cabang Vape Store</h3><p>Ringkasan omset dan pengguna tiap cabang</p></div>
        <button class="btn btn-primary btn-sm" data-modal-target="modalCabang" id="btnTambahCabang">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tambah Cabang
        </button>
    </div>
    <div class="panel__body">
        <div class="grid-branch">
            <?php foreach ($cabang as $c): ?>
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
                        <span>Total omset</span>
                    </div>
                    <div class="branch-card__meta mt-8">
                        <span><?= angka($c['jml_trx']) ?> transaksi</span>
                        <span><?= angka($c['jml_user']) ?> pengguna</span>
                    </div>
                    <?php if ($c['alamat']): ?>
                        <p class="text-muted" style="font-size:12px;margin-top:10px;"><?= e($c['alamat']) ?></p>
                    <?php endif; ?>
                    <div class="flex gap-8 mt-16">
                        <button type="button" class="btn btn-ghost btn-sm"
                                data-modal-target="modalCabang"
                                data-payload='<?= e(json_encode([
                                    'id' => $c['id'], 'kode' => $c['kode'], 'nama' => $c['nama'],
                                    'alamat' => $c['alamat'], 'telepon' => $c['telepon'], 'is_pusat' => $c['is_pusat'],
                                ])) ?>'
                                onclick="document.getElementById('cbPusat').checked = <?= (int) $c['is_pusat'] === 1 ? 'true' : 'false' ?>;">
                            Edit
                        </button>
                        <form method="post" onsubmit="return confirm('Hapus cabang <?= e($c['nama']) ?>?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="modalCabang">
    <div class="modal">
        <form method="post" id="formCabang">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="cbId" value="0">
            <div class="modal__head">
                <h3 id="cbTitle">Tambah Cabang</h3>
                <button type="button" class="modal__close" data-close-modal="modalCabang">&times;</button>
            </div>
            <div class="modal__body">
                <div class="form-grid">
                    <div class="form-row">
                        <label for="cbKode">Kode Cabang</label>
                        <input class="form-control" type="text" name="kode" id="cbKode" placeholder="mis. SGLT" required>
                    </div>
                    <div class="form-row">
                        <label for="cbTelepon">Telepon</label>
                        <input class="form-control" type="text" name="telepon" id="cbTelepon" placeholder="mis. 0717-123456">
                    </div>
                </div>
                <div class="form-row">
                    <label for="cbNama">Nama Cabang</label>
                    <input class="form-control" type="text" name="nama" id="cbNama" placeholder="mis. Cabang Sungailiat" required>
                </div>
                <div class="form-row">
                    <label for="cbAlamat">Alamat</label>
                    <input class="form-control" type="text" name="alamat" id="cbAlamat" placeholder="Alamat lengkap cabang">
                </div>
                <div class="form-row">
                    <label class="flex items-center gap-8" style="cursor:pointer;">
                        <input type="checkbox" name="is_pusat" id="cbPusat" value="1">
                        Tandai sebagai Toko Induk (pusat konsolidasi)
                    </label>
                    <div class="form-hint">Toko induk menjadi pusat monitoring seluruh cabang.</div>
                </div>
            </div>
            <div class="modal__foot">
                <button type="button" class="btn btn-ghost" data-close-modal="modalCabang">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Cabang</button>
            </div>
        </form>
    </div>
</div>

<?php
$extra_js = <<<'JS'
(function () {
    document.getElementById('btnTambahCabang').addEventListener('click', function () {
        document.getElementById('formCabang').reset();
        document.getElementById('cbId').value = '0';
        document.getElementById('cbPusat').checked = false;
        document.getElementById('cbTitle').textContent = 'Tambah Cabang';
    });
    document.querySelectorAll('[data-modal-target="modalCabang"][data-payload]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('cbTitle').textContent = 'Edit Cabang';
        });
    });
})();
JS;
require __DIR__ . '/includes/footer.php';
