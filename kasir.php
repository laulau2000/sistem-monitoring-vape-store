<?php
/**
 * Kasir — input transaksi penjualan (POS sederhana).
 * Fitur: metode bayar + kembalian, diskon, HPP/laba, draft keranjang di server,
 * dan cetak struk setelah transaksi tersimpan.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

$scope = scoped_branch_id();
$user  = current_user();

const METODE_BAYAR = ['tunai' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer'];

/* ---------------------------------------------------------------- */
/* Simpan draft keranjang di server (session)                       */
/* ---------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_draft') {
    header('Content-Type: application/json; charset=utf-8');
    $cart  = json_decode($_POST['cart'] ?? '[]', true);
    $clean = [];
    if (is_array($cart)) {
        foreach ($cart as $it) {
            $pid = (int) ($it['id'] ?? 0);
            $qty = (int) ($it['qty'] ?? 0);
            if ($pid > 0 && $qty > 0) {
                $clean[] = ['id' => $pid, 'qty' => min($qty, 9999)];
            }
        }
    }
    $_SESSION['draft_cart'] = $clean;
    echo json_encode(['ok' => true, 'items' => count($clean)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_draft') {
    header('Content-Type: application/json; charset=utf-8');
    unset($_SESSION['draft_cart']);
    echo json_encode(['ok' => true]);
    exit;
}

/* ---------------------------------------------------------------- */
/* Proses simpan transaksi                                          */
/* ---------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $branchId = $scope !== null ? $scope : (int) ($_POST['branch_id'] ?? 0);
    $catatan  = trim($_POST['catatan'] ?? '');
    $items    = json_decode($_POST['items_json'] ?? '[]', true);
    $metode   = (string) ($_POST['metode_bayar'] ?? 'tunai');
    $diskon   = (float) ($_POST['diskon'] ?? 0);
    $bayar    = (float) ($_POST['bayar'] ?? 0);

    if (!isset(METODE_BAYAR[$metode])) {
        $metode = 'tunai';
    }

    if ($branchId <= 0) {
        flash_set('error', 'Cabang penjualan belum dipilih.');
        redirect('kasir.php');
    }
    if (!is_array($items) || count($items) === 0) {
        flash_set('error', 'Keranjang masih kosong. Tambahkan produk terlebih dahulu.');
        redirect('kasir.php');
    }

    try {
        $saleId = simpan_penjualan($branchId, $items, $metode, $diskon, $bayar, $catatan, (int) $user['id']);
        unset($_SESSION['draft_cart']);
        flash_set('success', 'Transaksi berhasil disimpan.');
        redirect('struk.php?id=' . $saleId . '&auto=1');
    } catch (Throwable $ex) {
        flash_set('error', $ex->getMessage());
        redirect('kasir.php');
    }
}

/* ---------------------------------------------------------------- */
/* Data master untuk tampilan                                       */
/* ---------------------------------------------------------------- */
$produk = db()->query(
    'SELECT p.*, c.nama AS kategori FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.nama'
)->fetchAll();

/* Draft keranjang tersimpan (hanya item yang masih ada & stok cukup) */
$produkById = [];
foreach ($produk as $p) {
    $produkById[(int) $p['id']] = $p;
}
$draftCart = [];
foreach ($_SESSION['draft_cart'] ?? [] as $d) {
    $pid = (int) ($d['id'] ?? 0);
    $qty = (int) ($d['qty'] ?? 0);
    if (!isset($produkById[$pid]) || $qty < 1) {
        continue;
    }
    $p = $produkById[$pid];
    if ((int) $p['stok'] < 1) {
        continue;
    }
    $draftCart[] = [
        'id'    => $pid,
        'nama'  => $p['nama'],
        'harga' => (float) $p['harga'],
        'stok'  => (int) $p['stok'],
        'qty'   => min($qty, (int) $p['stok']),
    ];
}
$draftJson = json_encode($draftCart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$cabangList = [];
if ($scope === null) {
    $cabangList = db()->query('SELECT id, nama, kode FROM branches ORDER BY is_pusat DESC, nama')->fetchAll();
}

$page_title = 'Transaksi Baru';
$page_sub   = 'Input penjualan ' . ($scope !== null ? e($user['branch_nama']) : '(pilih cabang)');
$active     = 'kasir';
require __DIR__ . '/includes/header.php';
?>

<form method="post" id="kasirForm">
    <?= csrf_field() ?>
    <input type="hidden" name="items_json" id="itemsJson" value="[]">
    <input type="hidden" name="metode_bayar" id="metodeBayar" value="tunai">

    <div class="pos-grid">
        <!-- Kolom kiri: pilih produk -->
        <div class="panel">
            <div class="panel__head">
                <div><h3>Pilih Produk</h3><p>Klik produk untuk menambah ke keranjang</p></div>
                <div class="search-input" style="min-width:220px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input class="form-control" type="text" id="cariProduk" placeholder="Cari produk...">
                </div>
            </div>
            <div class="panel__body" id="produkList" style="max-height:560px;overflow-y:auto;">
                <?php if (!$produk): ?>
                    <div class="empty-state">Belum ada produk. Tambahkan produk terlebih dahulu di menu Produk.</div>
                <?php endif; ?>
                <?php foreach ($produk as $p):
                    $habis   = (int) $p['stok'] <= 0;
                    $fotoAda = !empty($p['foto']) && is_file(__DIR__ . '/' . $p['foto']); ?>
                    <div class="product-pick <?= $habis ? 'out' : '' ?>"
                         data-id="<?= (int) $p['id'] ?>"
                         data-nama="<?= e($p['nama']) ?>"
                         data-harga="<?= (float) $p['harga'] ?>"
                         data-stok="<?= (int) $p['stok'] ?>"
                         data-cari="<?= e(strtolower($p['nama'] . ' ' . $p['kode'] . ' ' . $p['kategori'])) ?>">
                        <span class="product-pick__thumb">
                            <?php if ($fotoAda): ?>
                                <img src="<?= e($p['foto']) ?>" alt="<?= e($p['nama']) ?>" loading="lazy">
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                            <?php endif; ?>
                        </span>
                        <div class="product-pick__info">
                            <div class="product-pick__name"><?= e($p['nama']) ?></div>
                            <div class="product-pick__sub"><?= e($p['kode']) ?> &middot; <?= e($p['kategori'] ?: 'Tanpa kategori') ?> &middot; stok <?= angka($p['stok']) ?></div>
                        </div>
                        <div class="product-pick__price"><?= rupiah($p['harga']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Kolom kanan: keranjang & pembayaran -->
        <div class="panel">
            <div class="panel__head">
                <div><h3>Keranjang &amp; Pembayaran</h3><p id="draftInfo">Draft tersimpan otomatis di server</p></div>
            </div>
            <div class="panel__body">
                <?php if ($scope === null): ?>
                    <div class="form-row">
                        <label for="branch_id">Cabang Penjualan</label>
                        <select class="form-control" name="branch_id" id="branch_id" required>
                            <option value="">— Pilih cabang —</option>
                            <?php foreach ($cabangList as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= e($c['nama']) ?> (<?= e($c['kode']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-row">
                        <label>Cabang Penjualan</label>
                        <input class="form-control" type="text" value="<?= e($user['branch_nama']) ?>" disabled>
                    </div>
                <?php endif; ?>

                <div id="cartEmpty" class="empty-state" style="padding:32px 10px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <div>Keranjang masih kosong</div>
                </div>
                <div id="cartItems"></div>

                <div class="cart-total">
                    <span>Subtotal</span>
                    <strong id="cartSubtotal">Rp 0</strong>
                </div>

                <div class="form-row mt-8">
                    <label for="diskon">Diskon (Rp)</label>
                    <input class="form-control" type="text" inputmode="numeric" name="diskon" id="diskon" value="0" data-raw="0" placeholder="0">
                </div>

                <div class="cart-total">
                    <span>Total Tagihan</span>
                    <strong id="cartTotal">Rp 0</strong>
                </div>

                <div class="form-row mt-8">
                    <label>Metode Bayar</label>
                    <div class="segmented" id="segMetode">
                        <?php foreach (METODE_BAYAR as $key => $label): ?>
                            <button type="button" class="segmented__item <?= $key === 'tunai' ? 'active' : '' ?>" data-metode="<?= $key ?>"><?= e($label) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-row" id="rowBayar">
                    <label for="bayar">Uang Diterima (Rp)</label>
                    <input class="form-control" type="text" inputmode="numeric" name="bayar" id="bayar" value="0" data-raw="0" placeholder="0">
                    <div class="quick-cash" id="quickCash">
                        <button type="button" data-nominal="uang-pas">Uang pas</button>
                        <button type="button" data-nominal="50000">50rb</button>
                        <button type="button" data-nominal="100000">100rb</button>
                        <button type="button" data-nominal="150000">150rb</button>
                        <button type="button" data-nominal="200000">200rb</button>
                    </div>
                </div>

                <div class="cart-total" id="rowKembalian">
                    <span>Kembalian</span>
                    <strong id="cartKembalian">Rp 0</strong>
                </div>

                <div class="form-row mt-8">
                    <label for="catatan">Catatan (opsional)</label>
                    <input class="form-control" type="text" name="catatan" id="catatan" placeholder="mis. pembeli member">
                </div>

                <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center;" id="btnSimpan" disabled>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Simpan &amp; Cetak Struk
                </button>
                <button class="btn btn-ghost btn-block mt-8" type="button" id="btnKosongkan">Kosongkan Keranjang</button>
            </div>
        </div>
    </div>
</form>

<?php
$extra_js = <<<JS
(function () {
    var cart = {$draftJson};

    var list       = document.getElementById('produkList');
    var cartBox    = document.getElementById('cartItems');
    var cartEmpty  = document.getElementById('cartEmpty');
    var cartTotal  = document.getElementById('cartTotal');
    var cartSubtot = document.getElementById('cartSubtotal');
    var kembalianEl= document.getElementById('cartKembalian');
    var itemsJson  = document.getElementById('itemsJson');
    var btnSimpan  = document.getElementById('btnSimpan');
    var cari       = document.getElementById('cariProduk');
    var inputDiskon= document.getElementById('diskon');
    var inputBayar = document.getElementById('bayar');
    var metodeInput= document.getElementById('metodeBayar');
    var rowBayar   = document.getElementById('rowBayar');
    var rowKembali = document.getElementById('rowKembalian');
    var draftInfo  = document.getElementById('draftInfo');

    function rupiah(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }
    function bruto() { return cart.reduce(function (a, i) { return a + i.harga * i.qty; }, 0); }

    /* ---- Format ribuan otomatis pada input uang ---- */
    function fmtRibuan(n) {
        n = String(n).replace(/[^0-9]/g, '');
        if (n === '') return '';
        return Number(n).toLocaleString('id-ID');
    }
    function rawUang(input) {
        return parseInt(input.dataset.raw || '0', 10) || 0;
    }
    function setUang(input, num) {
        num = Math.max(0, Math.round(Number(num) || 0));
        input.dataset.raw = String(num);
        input.value = fmtRibuan(num);
    }
    function bindUang(input) {
        input.dataset.raw = String(parseInt(String(input.value).replace(/[^0-9]/g, ''), 10) || 0);
        input.value = fmtRibuan(input.dataset.raw);
        input.addEventListener('input', function () {
            var raw = String(this.value).replace(/[^0-9]/g, '');
            this.dataset.raw = raw;
            this.value = raw === '' ? '' : Number(raw).toLocaleString('id-ID');
        });
        input.addEventListener('focus', function () { this.select(); });
    }

    function diskon() { var d = rawUang(inputDiskon); var b = bruto(); return Math.min(Math.max(d, 0), b); }

    /* --- Simpan draft ke server (dengan jeda agar tidak spam) --- */
    var draftTimer = null;
    function simpanDraft() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(function () {
            var body = new URLSearchParams();
            body.set('action', 'save_draft');
            body.set('cart', JSON.stringify(cart.map(function (i) { return { id: i.id, qty: i.qty }; })));
            fetch('kasir.php', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.ok && draftInfo) {
                        draftInfo.textContent = res.items > 0
                            ? 'Draft tersimpan di server - ' + res.items + ' produk'
                            : 'Draft tersimpan otomatis di server';
                    }
                })
                .catch(function () { /* offline: draft tetap aman di halaman */ });
        }, 600);
    }

    function render() {
        cartBox.innerHTML = '';
        var sub = 0;
        cart.forEach(function (item, idx) {
            sub += item.harga * item.qty;
            var line = document.createElement('div');
            line.className = 'cart-line';
            line.innerHTML =
                '<div><div class="cart-line__name">' + item.nama + '</div>' +
                '<div class="cart-line__sub">' + rupiah(item.harga) + ' &times; ' + item.qty + '</div></div>' +
                '<div class="cart-line__right">' +
                '<input class="qty-input" type="number" min="1" max="' + item.stok + '" value="' + item.qty + '" data-idx="' + idx + '">' +
                '<span class="cart-line__total">' + rupiah(item.harga * item.qty) + '</span>' +
                '<button type="button" class="icon-btn danger" data-del="' + idx + '">&times;</button>' +
                '</div>';
            cartBox.appendChild(line);
        });

        var d = diskon();
        var total = sub - d;
        cartSubtot.textContent = rupiah(sub);
        cartTotal.textContent = rupiah(total);
        cartEmpty.style.display = cart.length ? 'none' : 'block';
        btnSimpan.disabled = cart.length === 0;
        itemsJson.value = JSON.stringify(cart.map(function (i) { return { id: i.id, qty: i.qty }; }));

        /* Kembalian */
        var metode = metodeInput.value;
        var bayar = rawUang(inputBayar);
        if (metode === 'tunai') {
            rowBayar.style.display = '';
            rowKembali.style.display = '';
            var kembali = bayar - total;
            kembalianEl.textContent = rupiah(kembali > 0 ? kembali : 0);
            kembalianEl.style.color = (bayar > 0 && kembali < 0) ? 'var(--red)' : 'var(--primary-dark)';
        } else {
            rowBayar.style.display = 'none';
            rowKembali.style.display = 'none';
        }
        simpanDraft();
    }

    list.addEventListener('click', function (e) {
        var card = e.target.closest('.product-pick');
        if (!card) { return; }
        var id = parseInt(card.dataset.id, 10);
        var existing = cart.find(function (i) { return i.id === id; });
        var stok = parseInt(card.dataset.stok, 10);
        if (existing) {
            if (existing.qty < stok) { existing.qty++; }
        } else {
            cart.push({
                id: id,
                nama: card.dataset.nama,
                harga: parseFloat(card.dataset.harga),
                stok: stok,
                qty: 1
            });
        }
        render();
    });

    cartBox.addEventListener('click', function (e) {
        var del = e.target.closest('[data-del]');
        if (del) { cart.splice(parseInt(del.dataset.del, 10), 1); render(); }
    });

    cartBox.addEventListener('input', function (e) {
        if (!e.target.classList.contains('qty-input')) { return; }
        var idx = parseInt(e.target.dataset.idx, 10);
        var val = parseInt(e.target.value, 10);
        if (isNaN(val) || val < 1) { val = 1; }
        if (val > cart[idx].stok) { val = cart[idx].stok; }
        cart[idx].qty = val;
        render();
    });

    if (cari) {
        cari.addEventListener('input', function () {
            var q = this.value.toLowerCase();
            list.querySelectorAll('.product-pick').forEach(function (el) {
                el.style.display = el.dataset.cari.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    /* --- Metode bayar --- */
    document.getElementById('segMetode').addEventListener('click', function (e) {
        var btn = e.target.closest('.segmented__item');
        if (!btn) { return; }
        this.querySelectorAll('.segmented__item').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        metodeInput.value = btn.dataset.metode;
        render();
    });

    /* --- Tombol nominal cepat --- */
    document.getElementById('quickCash').addEventListener('click', function (e) {
        var btn = e.target.closest('button');
        if (!btn) { return; }
        var val = btn.dataset.nominal;
        var num = (val === 'uang-pas') ? Math.max(0, bruto() - diskon()) : parseInt(val, 10);
        setUang(inputBayar, num);
        render();
    });

    inputDiskon.addEventListener('input', render);
    inputBayar.addEventListener('input', render);

    /* --- Kosongkan keranjang --- */
    document.getElementById('btnKosongkan').addEventListener('click', function () {
        cart = [];
        setUang(inputDiskon, 0);
        setUang(inputBayar, 0);
        render();
    });

    /* Aktifkan format ribuan & kirim angka bersih saat submit */
    bindUang(inputDiskon);
    bindUang(inputBayar);
    var kasirForm = document.getElementById('kasirForm');
    if (kasirForm) {
        kasirForm.addEventListener('submit', function () {
            inputDiskon.value = inputDiskon.dataset.raw || '0';
            inputBayar.value = inputBayar.dataset.raw || '0';
        });
    }

    render();
})();
JS;
require __DIR__ . '/includes/footer.php';
