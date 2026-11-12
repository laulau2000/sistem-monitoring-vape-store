<?php
/**
 * Supplier — master data pemasok barang (khusus superadmin).
 * Dipakai sebagai pilihan pada menu Barang Masuk.
 */
require_once __DIR__ . '/includes/functions.php';
require_superadmin();
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = (int) ($_POST['id'] ?? 0);
        $nama    = trim($_POST['nama'] ?? '');
        $kontak  = trim($_POST['kontak'] ?? '');
        $telepon = trim($_POST['telepon'] ?? '');
        $alamat  = trim($_POST['alamat'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');

        if ($nama === '') {
            flash_set('error', 'Nama supplier wajib diisi.');
            redirect('supplier.php');
        }
        try {
            if ($id > 0) {
                db()->prepare('UPDATE suppliers SET nama=?, kontak=?, telepon=?, alamat=?, catatan=? WHERE id=?')
                    ->execute([$nama, $kontak, $telepon, $alamat, $catatan, $id]);
                flash_set('success', 'Supplier "' . $nama . '" berhasil diperbarui.');
            } else {
                db()->prepare('INSERT INTO suppliers (nama, kontak, telepon, alamat, catatan, created_at) VALUES (?, ?, ?, ?, ?, NOW())')
                    ->execute([$nama, $kontak, $telepon, $alamat, $catatan]);
                flash_set('success', 'Supplier "' . $nama . '" berhasil ditambahkan.');
            }
        } catch (Throwable $ex) {
            flash_set('error', 'Gagal menyimpan supplier: ' . $ex->getMessage());
        }
        redirect('supplier.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $q  = db()->prepare('SELECT COUNT(*) FROM goods_receipts WHERE supplier_id = ?');
        $q->execute([$id]);
        if ((int) $q->fetchColumn() > 0) {
            flash_set('error', 'Supplier tidak dapat dihapus karena masih dipakai pada riwayat barang masuk.');
        } else {
            db()->prepare('DELETE FROM suppliers WHERE id = ?')->execute([$id]);
            flash_set('success', 'Supplier berhasil dihapus.');
        }
        redirect('supplier.php');
    }
}

$supplier = db()->query(
    'SELECT s.*, (SELECT COUNT(*) FROM goods_receipts g WHERE g.supplier_id = s.id) AS jml_terima
     FROM suppliers s ORDER BY s.nama'
)->fetchAll();

$page_title = 'Supplier';
$page_sub   = count($supplier) . ' supplier terdaftar';
$active     = 'supplier';
require __DIR__ . '/includes/header.php';
?>

<div class="panel">
    <div class="panel__head">
        <div><h3>Daftar Supplier</h3><p>Pemasok barang yang tampil sebagai pilihan pada menu Barang Masuk.</p></div>
        <button class="btn btn-primary btn-sm" data-modal-target="modalSupplier" id="btnTambahSupplier">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tambah Supplier
        </button>
    </div>
    <div class="panel__body panel__body--flush">
        <?php if (!$supplier): ?>
            <div class="empty-state">Belum ada supplier. Tambahkan supplier pertama Anda.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Nama Supplier</th><th>Kontak</th><th>Telepon</th><th class="text-right">Penerimaan</th><th style="width:170px;"></th></tr>
                </thead>
                <tbody>
                <?php foreach ($supplier as $s): ?>
                    <tr>
                        <td>
                            <strong><?= e($s['nama']) ?></strong>
                            <?php if ($s['alamat']): ?><div style="color:var(--text-muted);font-size:11.5px;"><?= e($s['alamat']) ?></div><?php endif; ?>
                        </td>
                        <td><?= e($s['kontak'] ?: '-') ?></td>
                        <td class="mono"><?= e($s['telepon'] ?: '-') ?></td>
                        <td class="text-right"><span class="badge badge-gray"><?= angka($s['jml_terima']) ?> dok</span></td>
                        <td class="text-right">
                            <div class="flex gap-8" style="justify-content:flex-end;">
                                <button type="button" class="btn btn-ghost btn-sm"
                                        data-modal-target="modalSupplier"
                                        data-payload='<?= e(json_encode([
                                            'id' => (int) $s['id'], 'nama' => $s['nama'], 'kontak' => $s['kontak'],
                                            'telepon' => $s['telepon'], 'alamat' => $s['alamat'], 'catatan' => $s['catatan'],
                                        ])) ?>'>
                                    Edit
                                </button>
                                <form method="post" onsubmit="return confirm('Hapus supplier <?= e($s['nama']) ?>?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
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

<div class="modal-backdrop" id="modalSupplier">
    <div class="modal">
        <form method="post" id="formSupplier">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="spId" value="0">
            <div class="modal__head">
                <h3 id="spTitle">Tambah Supplier</h3>
                <button type="button" class="modal__close" data-close-modal="modalSupplier">&times;</button>
            </div>
            <div class="modal__body">
                <div class="form-row">
                    <label for="spNama">Nama Supplier</label>
                    <input class="form-control" type="text" name="nama" id="spNama" placeholder="mis. PT Vapor Nusantara" required>
                </div>
                <div class="form-grid">
                    <div class="form-row">
                        <label for="spKontak">Nama Kontak (PIC)</label>
                        <input class="form-control" type="text" name="kontak" id="spKontak" placeholder="mis. Budi">
                    </div>
                    <div class="form-row">
                        <label for="spTelepon">Telepon</label>
                        <input class="form-control" type="text" name="telepon" id="spTelepon" placeholder="mis. 0812-3456-7890">
                    </div>
                </div>
                <div class="form-row">
                    <label for="spAlamat">Alamat</label>
                    <input class="form-control" type="text" name="alamat" id="spAlamat" placeholder="Alamat supplier">
                </div>
                <div class="form-row">
                    <label for="spCatatan">Catatan</label>
                    <input class="form-control" type="text" name="catatan" id="spCatatan" placeholder="mis. syarat pembayaran 30 hari">
                </div>
            </div>
            <div class="modal__foot">
                <button type="button" class="btn btn-ghost" data-close-modal="modalSupplier">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Supplier</button>
            </div>
        </form>
    </div>
</div>

<?php
$extra_js = <<<'JS'
(function () {
    document.getElementById('btnTambahSupplier').addEventListener('click', function () {
        document.getElementById('formSupplier').reset();
        document.getElementById('spId').value = '0';
        document.getElementById('spTitle').textContent = 'Tambah Supplier';
    });
    document.querySelectorAll('[data-modal-target="modalSupplier"][data-payload]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('spTitle').textContent = 'Edit Supplier';
        });
    });
})();
JS;
require __DIR__ . '/includes/footer.php';
