<?php
/**
 * Owner — ringkasan cepat untuk superadmin/owner.
 * Berisi 4 tab: Ringkasan Harian, Laba per Produk, Stok Menipis & Habis, Aktivitas Pengguna.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();
require_superadmin();

/* ---------------------------------------------------------------- */
/* Data hari ini (semua cabang)                                      */
/* ---------------------------------------------------------------- */
$tanggalHariIni = date('Y-m-d');
$rowHari = db()->prepare(
    'SELECT COUNT(*) AS jml, COALESCE(SUM(total),0) AS omzet,
            COALESCE(SUM(hpp),0) AS hpp, COALESCE(SUM(diskon),0) AS diskon
     FROM sales WHERE DATE(tanggal) = ?'
);
$rowHari->execute([$tanggalHariIni]);
$hari = $rowHari->fetch() ?: ['jml' => 0, 'omzet' => 0, 'hpp' => 0, 'diskon' => 0];
$labaHari = (float) $hari['omzet'] - (float) $hari['hpp'];

/* Metode bayar hari ini */
$metodeHari = [];
$m = db()->prepare(
    'SELECT metode_bayar, COUNT(*) AS jml, COALESCE(SUM(total),0) AS total
     FROM sales WHERE DATE(tanggal) = ? GROUP BY metode_bayar ORDER BY total DESC'
);
$m->execute([$tanggalHariIni]);
$metodeHari = $m->fetchAll();
$labelMetode = ['tunai' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer'];

/* 5 produk terlaris hari ini */
$topHari = db()->prepare(
    'SELECT p.nama, p.kode, SUM(si.qty) AS qty, SUM(si.subtotal) AS omzet
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     JOIN products p ON p.id = si.product_id
     WHERE DATE(s.tanggal) = ?
     GROUP BY p.id ORDER BY qty DESC LIMIT 5'
);
$topHari->execute([$tanggalHariIni]);
$topHari = $topHari->fetchAll();

/* ---------------------------------------------------------------- */
/* Laba per Produk                                                   */
/* ---------------------------------------------------------------- */
$labaProduk = db()->query(
    'SELECT p.id, p.kode, p.nama, p.harga, p.harga_beli, p.stok,
            (p.harga - p.harga_beli) AS margin,
            CASE WHEN p.harga_beli > 0
                 THEN ROUND(((p.harga - p.harga_beli) / p.harga_beli) * 100, 1)
                 ELSE 0 END AS margin_persen,
            c.nama AS kategori
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY margin DESC'
)->fetchAll();

/* ---------------------------------------------------------------- */
/* Stok menipis & habis                                              */
/* ---------------------------------------------------------------- */
$stokAlert = db()->prepare(
    'SELECT p.id, p.kode, p.nama, p.stok, p.harga, c.nama AS kategori
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.stok <= ?
     ORDER BY p.stok ASC, p.nama ASC'
);
$stokAlert->execute([STOK_MINIMUM]);
$stokAlert = $stokAlert->fetchAll();

/* ---------------------------------------------------------------- */
/* Aktivitas pengguna (berdasarkan transaksi terakhir)              */
/* ---------------------------------------------------------------- */
$aktivitas = db()->query(
    'SELECT u.id, u.nama, u.username, u.role, u.aktif,
            b.nama AS cabang,
            (SELECT MAX(s.tanggal) FROM sales s WHERE s.user_id = u.id) AS terakhir,
            (SELECT COUNT(*) FROM sales s WHERE s.user_id = u.id AND DATE(s.tanggal) = CURDATE()) AS transaksi_hari_ini,
            (SELECT COUNT(*) FROM sales s WHERE s.user_id = u.id) AS total_transaksi
     FROM users u LEFT JOIN branches b ON b.id = u.branch_id
     ORDER BY u.role ASC, u.nama ASC'
)->fetchAll();

$labelRole = ['superadmin' => 'Super Admin', 'cabang' => 'Staf Cabang', 'kasir' => 'Kasir'];

$page_title = 'Ringkasan Owner';
$page_sub   = 'Pantau cepat omzet, laba, stok, dan aktivitas tim';
$active     = 'owner';
require __DIR__ . '/includes/header.php';
?>

<!-- Tab pemilih -->
<div class="owner-tabs" id="ownerTabs">
    <button type="button" class="owner-tab active" data-tab="harian">Ringkasan Harian</button>
    <button type="button" class="owner-tab" data-tab="laba">Laba per Produk</button>
    <button type="button" class="owner-tab" data-tab="stok">Stok Menipis &amp; Habis</button>
    <button type="button" class="owner-tab" data-tab="aktivitas">Aktivitas Pengguna</button>
</div>

<!-- TAB 1: Ringkasan Harian -->
<section class="owner-panel active" data-panel="harian">
    <div class="grid-kpi">
        <div class="kpi">
            <div class="kpi__icon bg-blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div class="kpi__label">Omzet Hari Ini</div>
            <div class="kpi__value"><?= rupiah($hari['omzet']) ?></div>
            <div class="kpi__meta"><?= angka($hari['jml']) ?> transaksi</div>
        </div>
        <div class="kpi">
            <div class="kpi__icon bg-purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="kpi__label">Estimasi Laba (Kotor)</div>
            <div class="kpi__value"><?= rupiah($labaHari) ?></div>
            <div class="kpi__meta">HPP <?= rupiah($hari['hpp']) ?> · diskon <?= rupiah($hari['diskon']) ?></div>
        </div>
        <div class="kpi">
            <div class="kpi__icon bg-amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </div>
            <div class="kpi__label">Jumlah Transaksi</div>
            <div class="kpi__value"><?= angka($hari['jml']) ?></div>
            <div class="kpi__meta">Rata-rata <?= rupiah($hari['jml'] > 0 ? $hari['omzet'] / $hari['jml'] : 0) ?>/trx</div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head"><div><h3>Metode Pembayaran Hari Ini</h3><p>Distribusi berdasarkan cara bayar</p></div></div>
        <div class="panel__body">
            <?php if (!$metodeHari): ?>
                <div class="empty-state">Belum ada transaksi hari ini.</div>
            <?php else: ?>
                <div class="owner-methods">
                    <?php foreach ($metodeHari as $mh):
                        $pct = $hari['omzet'] > 0 ? round((($mh['total'] / $hari['omzet']) * 100), 1) : 0; ?>
                        <div class="owner-method">
                            <div class="owner-method__top">
                                <strong><?= e($labelMetode[$mh['metode_bayar']] ?? $mh['metode_bayar']) ?></strong>
                                <span><?= angka($mh['jml']) ?> trx</span>
                            </div>
                            <div class="owner-method__bar"><span style="width:<?= $pct ?>%"></span></div>
                            <div class="owner-method__bottom">
                                <span><?= rupiah($mh['total']) ?></span>
                                <em><?= $pct ?>%</em>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head"><div><h3>Produk Terlaris Hari Ini</h3><p>5 produk dengan penjualan unit terbanyak</p></div></div>
        <div class="panel__body">
            <?php if (!$topHari): ?>
                <div class="empty-state">Belum ada penjualan hari ini.</div>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Produk</th><th>Kode</th><th class="r">Unit Terjual</th><th class="r">Omzet</th></tr></thead>
                    <tbody>
                        <?php foreach ($topHari as $t): ?>
                            <tr>
                                <td><?= e($t['nama']) ?></td>
                                <td class="mono"><?= e($t['kode']) ?></td>
                                <td class="r"><?= angka($t['qty']) ?></td>
                                <td class="r"><?= rupiah($t['omzet']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- TAB 2: Laba per Produk -->
<section class="owner-panel" data-panel="laba">
    <div class="panel">
        <div class="panel__head"><div><h3>Margin Laba per Produk</h3><p>Diurutkan dari margin terbesar</p></div></div>
        <div class="panel__body">
            <?php if (!$labaProduk): ?>
                <div class="empty-state">Belum ada produk.</div>
            <?php else: ?>
                <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th class="r">HPP</th>
                            <th class="r">Harga Jual</th>
                            <th class="r">Margin/Unit</th>
                            <th class="r">%</th>
                            <th class="r">Stok</th>
                            <th class="r">Est. Laba Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($labaProduk as $lp):
                            $estLaba = (float) $lp['margin'] * (int) $lp['stok']; ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;"><?= e($lp['nama']) ?></div>
                                    <div class="mono muted" style="font-size:11px;"><?= e($lp['kode']) ?></div>
                                </td>
                                <td><?= e($lp['kategori'] ?: '-') ?></td>
                                <td class="r muted"><?= rupiah($lp['harga_beli']) ?></td>
                                <td class="r"><?= rupiah($lp['harga']) ?></td>
                                <td class="r"><strong style="color:#16a34a;"><?= rupiah($lp['margin']) ?></strong></td>
                                <td class="r"><?= e($lp['margin_persen']) ?>%</td>
                                <td class="r"><?= angka($lp['stok']) ?></td>
                                <td class="r"><?= rupiah($estLaba) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- TAB 3: Stok Menipis & Habis -->
<section class="owner-panel" data-panel="stok">
    <div class="panel">
        <div class="panel__head">
            <div><h3>Stok Perlu Perhatian</h3><p>Produk dengan stok &le; <?= STOK_MINIMUM ?> unit (ambang minimum)</p></div>
            <a class="btn btn-ghost btn-sm" href="produk.php">Kelola Produk</a>
        </div>
        <div class="panel__body">
            <?php if (!$stokAlert): ?>
                <div class="empty-state">Semua stok aman. Tidak ada produk yang menipis atau habis.</div>
            <?php else: ?>
                <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Produk</th><th>Kode</th><th>Kategori</th><th class="r">Harga</th><th class="r">Stok</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stokAlert as $sa):
                            $qty = (int) $sa['stok'];
                            if ($qty <= 0) { $cls = 'badge-red'; $lbl = 'Habis'; }
                            else { $cls = 'badge-amber'; $lbl = 'Menipis'; } ?>
                            <tr>
                                <td><strong><?= e($sa['nama']) ?></strong></td>
                                <td class="mono"><?= e($sa['kode']) ?></td>
                                <td><?= e($sa['kategori'] ?: '-') ?></td>
                                <td class="r"><?= rupiah($sa['harga']) ?></td>
                                <td class="r"><strong><?= angka($qty) ?></strong></td>
                                <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- TAB 4: Aktivitas Pengguna -->
<section class="owner-panel" data-panel="aktivitas">
    <div class="panel">
        <div class="panel__head">
            <div><h3>Aktivitas Tim</h3><p>Transaksi terakhir &amp; kontribusi setiap pengguna</p></div>
            <a class="btn btn-ghost btn-sm" href="users.php">Kelola Pengguna</a>
        </div>
        <div class="panel__body">
            <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Nama</th><th>Username</th><th>Peran</th><th>Cabang</th><th class="r">Trx Hari Ini</th><th class="r">Total Trx</th><th>Transaksi Terakhir</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($aktivitas as $a): ?>
                        <tr>
                            <td><strong><?= e($a['nama']) ?></strong></td>
                            <td class="mono"><?= e($a['username']) ?></td>
                            <td><span class="badge <?= $a['role'] === 'superadmin' ? 'badge-blue' : ($a['role'] === 'kasir' ? 'badge-success' : '') ?>"><?= e($labelRole[$a['role']] ?? $a['role']) ?></span></td>
                            <td><?= e($a['cabang'] ?: 'Semua cabang') ?></td>
                            <td class="r"><?= angka($a['transaksi_hari_ini']) ?></td>
                            <td class="r"><?= angka($a['total_transaksi']) ?></td>
                            <td><?= $a['terakhir'] ? tanggal_id($a['terakhir'], true) : '<span class="muted">Belum pernah</span>' ?></td>
                            <td>
                                <?php if ($a['aktif']): ?><span class="badge badge-success">Aktif</span><?php else: ?><span class="badge badge-red">Nonaktif</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var tabs   = document.querySelectorAll('.owner-tab');
    var panels = document.querySelectorAll('.owner-panel');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var key = tab.dataset.tab;
            tabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
            panels.forEach(function (p) { p.classList.toggle('active', p.dataset.panel === key); });
        });
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
