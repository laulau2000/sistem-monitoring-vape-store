<?php
/**
 * Kategori — master data kategori produk (khusus superadmin).
 * Kategori menentukan awalan kode produk otomatis pada menu Barang Masuk.
 */
require_once __DIR__ . '/includes/functions.php';
require_superadmin();
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id   = (int) ($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');

        if ($nama === '') {
            flash_set('error', 'Nama kategori wajib diisi.');
            redirect('kategori.php');
        }
        try {
            if ($id > 0) {
                db()->prepare('UPDATE categories SET nama = ? WHERE id = ?')->execute([$nama, $id]);
                flash_set('success', 'Kategori "' . $nama . '" berhasil diperbarui.');
            } else {
                db()->prepare('INSERT INTO categories (nama) VALUES (?)')->execute([$nama]);
                flash_set('success', 'Kategori "' . $nama . '" berhasil ditambahkan.');
            }
        } catch (Throwable $ex) {
            flash_set('error', 'Gagal menyimpan kategori: ' . $ex->getMessage());
        }
        redirect('kategori.php');
    }

    if ($action === 'delete') {
        $id  = (int) ($_POST['id'] ?? 0);
        $q   = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $q->execute([$id]);
        if ((int) $q->fetchColumn() > 0) {
            flash_set('error', 'Kategori tidak dapat dihapus karena masih dipakai oleh produk.');
        } else {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash_set('success', 'Kategori berhasil dihapus.');
        }
        redirect('kategori.php');
    }
}

$kategori = db()->query(
    'SELECT c.id, c.nama,
        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS jml_produk
     FROM categories c ORDER BY c.nama'
)->fetchAll();

$page_title = 'Kategori';
$page_sub   = count($kategori) . ' kategori produk';
$active     = 'kategori';
require __DIR__ . '/includes/header.php';
?>

<div class="panel">
    <div class="panel__head">
        <div><h3>Kategori Produk</h3><p>Kategori menentukan awalan kode produk otomatis (mis. Liquid → LIQ-001).</p></div>
        <button class="btn btn-primary btn-sm" data-modal-target="modalKategori" id="btnTambahKategori">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tambah Kategori
        </button>
    </div>
    <div class="panel__body panel__body--flush">
        <?php if (!$kategori): ?>
            <div class="empty-state">Belum ada kategori. Tambahkan kategori pertama Anda.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th style="width:60px;">#</th><th>Nama Kategori</th><th class="text-right">Jumlah Produk</th><th style="width:170px;"></th></tr>
                </thead>
                <tbody>
                <?php $no = 1; foreach ($kategori as $k): ?>
                    <tr>
                        <td class="mono"><?= $no++ ?></td>
                        <td><strong><?= e($k['nama']) ?></strong></td>
                        <td class="text-right"><span class="badge badge-gray"><?= angka($k['jml_produk']) ?> produk</span></td>
                        <td class="text-right">
                            <div class="flex gap-8" style="justify-content:flex-end;">
                                <button type="button" class="btn btn-ghost btn-sm"
                                        data-modal-target="modalKategori"
                                        data-payload='<?= e(json_encode(['id' => (int) $k['id'], 'nama' => $k['nama']])) ?>'>
                                    Edit
                                </button>
                                <form method="post" onsubmit="return confirm('Hapus kategori <?= e($k['nama']) ?>?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
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

<div class="modal-backdrop" id="modalKategori">
    <div class="modal">
        <form method="post" id="formKategori">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="ktId" value="0">
            <div class="modal__head">
                <h3 id="ktTitle">Tambah Kategori</h3>
                <button type="button" class="modal__close" data-close-modal="modalKategori">&times;</button>
            </div>
            <div class="modal__body">
                <div class="form-row">
                    <label for="ktNama">Nama Kategori</label>
                    <input class="form-control" type="text" name="nama" id="ktNama" placeholder="mis. Liquid" required>
                    <div class="form-hint">Awalan kode otomatis diambil dari nama kategori ini.</div>
                </div>
            </div>
            <div class="modal__foot">
                <button type="button" class="btn btn-ghost" data-close-modal="modalKategori">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Kategori</button>
            </div>
        </form>
    </div>
</div>

<?php
$extra_js = <<<'JS'
(function () {
    document.getElementById('btnTambahKategori').addEventListener('click', function () {
        document.getElementById('formKategori').reset();
        document.getElementById('ktId').value = '0';
        document.getElementById('ktTitle').textContent = 'Tambah Kategori';
    });
    document.querySelectorAll('[data-modal-target="modalKategori"][data-payload]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('ktTitle').textContent = 'Edit Kategori';
        });
    });
})();
JS;
require __DIR__ . '/includes/footer.php';
