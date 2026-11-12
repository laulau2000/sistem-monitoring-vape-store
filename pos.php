<?php
/**
 * POS — halaman penjualan sederhana khusus kasir.
 * Fokus: cari nama produk, masukkan ke keranjang, bayar, lalu cetak struk.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

$scope = scoped_branch_id();
$user  = current_user();

const METODE_BAYAR = ['tunai' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer'];

/* ---------------------------------------------------------------- */
/* Draft keranjang (agar tidak hilang saat halaman dimuat ulang)    */
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
/* Simpan transaksi                                                 */
/* ---------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $branchId = $scope !== null ? $scope : (int) ($_POST['branch_id'] ?? 0);
    $items    = json_decode($_POST['items_json'] ?? '[]', true);
    $metode   = (string) ($_POST['metode_bayar'] ?? 'tunai');
    $diskon   = (float) ($_POST['diskon'] ?? 0);
    $bayar    = (float) ($_POST['bayar'] ?? 0);

    if (!isset(METODE_BAYAR[$metode])) {
        $metode = 'tunai';
    }

    try {
        $saleId = simpan_penjualan($branchId, is_array($items) ? $items : [], $metode, $diskon, $bayar, '', (int) $user['id']);
        unset($_SESSION['draft_cart']);
        flash_set('success', 'Transaksi berhasil disimpan.');
        redirect('struk.php?id=' . $saleId . '&auto=1');
    } catch (Throwable $ex) {
        flash_set('error', $ex->getMessage());
        redirect('pos.php');
    }
}

/* ---------------------------------------------------------------- */
/* Data untuk tampilan                                              */
/* ---------------------------------------------------------------- */
$produk = db()->query(
    'SELECT p.id, p.kode, p.nama, p.harga, p.stok, p.foto, c.nama AS kategori
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.nama'
)->fetchAll();

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

$flashes    = flash_all();
$namaCabang = $scope !== null ? $user['branch_nama'] : 'Belum pilih cabang';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Kasir &middot; <?= e(app_name()) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pos-body">
<div class="pos-app">

    <header class="pos-top">
        <div class="pos-brand">
            <div class="pos-logo">WG</div>
            <div class="pos-brand__text">
                <strong><?= e(app_name()) ?></strong>
                <span>Kasir / POS</span>
            </div>
        </div>
        <div class="pos-top__right">
            <div class="pos-me">
                <strong><?= e($user['nama']) ?></strong>
                <span><?= e($namaCabang) ?></span>
            </div>
            <a class="btn-logout" href="profil.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Profil
            </a>
            <a class="btn-logout" href="logout.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Keluar
            </a>
        </div>
    </header>

    <main class="pos-main">
        <form method="post" id="posForm" class="pos-form" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="items_json" id="itemsJson" value="[]">
            <input type="hidden" name="metode_bayar" id="metodeBayar" value="tunai">

            <!-- KIRI: pencarian & daftar produk -->
            <section class="pos-left">
                <div class="pos-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="cariProduk" placeholder="Ketik nama produk lalu tekan Enter…" autocomplete="off" autofocus>
                    <button type="button" class="pos-search__clear" id="btnClearCari" title="Bersihkan">&times;</button>
                </div>

                <?php if (!$produk): ?>
                    <div class="empty-state">Belum ada produk. Tambahkan produk dahulu melalui menu Produk.</div>
                <?php else: ?>
                    <div class="pos-prodlist" id="produkList">
                        <?php foreach ($produk as $p):
                            $habis   = (int) $p['stok'] <= 0;
                            $fotoAda = !empty($p['foto']) && is_file(__DIR__ . '/' . $p['foto']); ?>
                            <button type="button" class="pos-card <?= $habis ? 'out' : '' ?>"
                                    data-id="<?= (int) $p['id'] ?>"
                                    data-nama="<?= e($p['nama']) ?>"
                                    data-harga="<?= (float) $p['harga'] ?>"
                                    data-stok="<?= (int) $p['stok'] ?>"
                                    data-cari="<?= e(strtolower($p['nama'] . ' ' . $p['kode'] . ' ' . $p['kategori'])) ?>">
                                <span class="pos-card__thumb">
                                    <?php if ($fotoAda): ?>
                                        <img src="<?= e($p['foto']) ?>" alt="<?= e($p['nama']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                                    <?php endif; ?>
                                </span>
                                <span class="pos-card__info">
                                    <span class="pos-card__name"><?= e($p['nama']) ?></span>
                                    <span class="pos-card__sub"><?= e($p['kode']) ?> &middot; <?= e($p['kategori'] ?: 'Tanpa kategori') ?></span>
                                    <span class="pos-card__foot">
                                        <span class="pos-card__price"><?= rupiah($p['harga']) ?></span>
                                        <span class="pos-card__stock <?= $habis ? 'is-out' : '' ?>"><?= $habis ? 'Stok habis' : 'Stok ' . angka($p['stok']) ?></span>
                                    </span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="pos-noresult" id="produkKosong" style="display:none;">Produk tidak ditemukan.</div>
                <?php endif; ?>
            </section>

            <!-- KANAN: keranjang & pembayaran -->
            <aside class="pos-right">
                <div class="pos-cart">
                    <div class="pos-cart__head">
                        <h3>Keranjang</h3>
                        <span class="pos-cart__count" id="cartCount">0 item</span>
                    </div>

                    <div class="pos-cart__body">
                        <?php if ($scope === null): ?>
                            <div class="form-row" style="padding:12px 0 4px;">
                                <label for="branch_id">Cabang Penjualan</label>
                                <select class="form-control" name="branch_id" id="branch_id" required>
                                    <option value="">— Pilih cabang —</option>
                                    <?php foreach ($cabangList as $c): ?>
                                        <option value="<?= (int) $c['id'] ?>"><?= e($c['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="pos-cart__empty" id="cartEmpty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            <div>Belum ada produk dipilih</div>
                            <span>Cari produk di sebelah kiri lalu klik untuk menambah.</span>
                        </div>
                        <div id="cartItems"></div>
                    </div>

                    <div class="pos-cart__foot">
                        <div class="pos-sum">
                            <span>Subtotal</span>
                            <strong id="cartSubtotal">Rp 0</strong>
                        </div>

                        <div class="pos-diskon">
                            <label for="diskon">Diskon (Rp)</label>
                            <input class="form-control" type="text" inputmode="numeric" name="diskon" id="diskon" value="0" data-raw="0" placeholder="0">
                        </div>

                        <div class="pos-sum pos-sum--total">
                            <span>TOTAL</span>
                            <strong id="cartTotal">Rp 0</strong>
                        </div>

                        <div class="pos-metode" id="segMetode">
                            <?php foreach (METODE_BAYAR as $key => $label): ?>
                                <button type="button" class="pos-metode__item <?= $key === 'tunai' ? 'active' : '' ?>" data-metode="<?= $key ?>"><?= e($label) ?></button>
                            <?php endforeach; ?>
                        </div>

                        <div id="rowBayar">
                            <label class="pos-label" for="bayar">Uang Diterima</label>
                            <input class="form-control pos-bayar" type="text" inputmode="numeric" name="bayar" id="bayar" value="0" data-raw="0" placeholder="0">
                            <div class="quick-cash" id="quickCash">
                                <button type="button" data-nominal="uang-pas">Uang pas</button>
                                <button type="button" data-nominal="50000">50rb</button>
                                <button type="button" data-nominal="100000">100rb</button>
                                <button type="button" data-nominal="150000">150rb</button>
                                <button type="button" data-nominal="200000">200rb</button>
                            </div>
                        </div>

                        <div class="pos-sum pos-sum--kembali" id="rowKembalian">
                            <span>Kembalian</span>
                            <strong id="cartKembalian">Rp 0</strong>
                        </div>

                        <button class="btn btn-primary pos-submit" type="submit" id="btnSimpan" disabled>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            Simpan &amp; Cetak Struk
                        </button>
                        <button class="btn btn-ghost pos-clear" type="button" id="btnKosongkan">Kosongkan</button>
                    </div>
                </div>
            </aside>
        </form>
    </main>
</div>

<div class="toast-wrap" id="toastWrap">
    <?php foreach ($flashes as $f):
        $type   = in_array($f['type'], ['success', 'error', 'info'], true) ? $f['type'] : 'info';
        $titles = ['success' => 'Berhasil', 'error' => 'Gagal', 'info' => 'Informasi']; ?>
        <div class="toast <?= $type ?>">
            <div>
                <strong><?= $titles[$type] ?></strong>
                <p><?= e($f['msg']) ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script src="assets/js/app.js"></script>
<script>
(function () {
    var cart = <?= $draftJson ?>;

    var list        = document.getElementById('produkList');
    var cartBox     = document.getElementById('cartItems');
    var cartEmpty   = document.getElementById('cartEmpty');
    var cartCount   = document.getElementById('cartCount');
    var cartTotal   = document.getElementById('cartTotal');
    var cartSubtot  = document.getElementById('cartSubtotal');
    var kembalianEl = document.getElementById('cartKembalian');
    var itemsJson   = document.getElementById('itemsJson');
    var btnSimpan   = document.getElementById('btnSimpan');
    var cari        = document.getElementById('cariProduk');
    var btnClear    = document.getElementById('btnClearCari');
    var produkKosong= document.getElementById('produkKosong');
    var inputDiskon = document.getElementById('diskon');
    var inputBayar  = document.getElementById('bayar');
    var metodeInput = document.getElementById('metodeBayar');
    var rowBayar    = document.getElementById('rowBayar');
    var rowKembali  = document.getElementById('rowKembalian');

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
        /* Saat difokuskan, tampilkan angka mentah agar mudah diedit */
        input.addEventListener('focus', function () {
            this.select();
        });
    }

    function diskon() { var d = rawUang(inputDiskon); var b = bruto(); return Math.min(Math.max(d, 0), b); }

    var draftTimer = null;
    function simpanDraft() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(function () {
            var body = new URLSearchParams();
            body.set('action', 'save_draft');
            body.set('cart', JSON.stringify(cart.map(function (i) { return { id: i.id, qty: i.qty }; })));
            fetch('pos.php', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(function () {});
        }, 600);
    }

    function render() {
        cartBox.innerHTML = '';
        var sub = 0;
        var qtyTotal = 0;
        cart.forEach(function (item, idx) {
            sub += item.harga * item.qty;
            qtyTotal += item.qty;
            var line = document.createElement('div');
            line.className = 'pos-line';
            line.innerHTML =
                '<div class="pos-line__info">' +
                    '<span class="pos-line__name">' + item.nama + '</span>' +
                    '<span class="pos-line__price">' + rupiah(item.harga) + '</span>' +
                '</div>' +
                '<div class="pos-line__qty">' +
                    '<button type="button" data-dec="' + idx + '">&minus;</button>' +
                    '<span>' + item.qty + '</span>' +
                    '<button type="button" data-inc="' + idx + '">+</button>' +
                '</div>' +
                '<span class="pos-line__total">' + rupiah(item.harga * item.qty) + '</span>' +
                '<button type="button" class="pos-line__del" data-del="' + idx + '" title="Hapus">&times;</button>';
            cartBox.appendChild(line);
        });

        var d = diskon();
        var total = sub - d;
        cartSubtot.textContent = rupiah(sub);
        cartTotal.textContent = rupiah(total);
        cartCount.textContent = qtyTotal + ' item';
        cartEmpty.style.display = cart.length ? 'none' : 'block';
        btnSimpan.disabled = cart.length === 0;
        itemsJson.value = JSON.stringify(cart.map(function (i) { return { id: i.id, qty: i.qty }; }));

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

    function tambah(card) {
        var id = parseInt(card.dataset.id, 10);
        var stok = parseInt(card.dataset.stok, 10);
        var existing = cart.find(function (i) { return i.id === id; });
        if (existing) {
            if (existing.qty < stok) { existing.qty++; }
        } else {
            cart.push({ id: id, nama: card.dataset.nama, harga: parseFloat(card.dataset.harga), stok: stok, qty: 1 });
        }
        render();
    }

    if (list) {
        list.addEventListener('click', function (e) {
            var card = e.target.closest('.pos-card');
            if (card) { tambah(card); }
        });
    }

    cartBox.addEventListener('click', function (e) {
        var dec = e.target.closest('[data-dec]');
        var inc = e.target.closest('[data-inc]');
        var del = e.target.closest('[data-del]');
        if (dec) {
            var i1 = parseInt(dec.dataset.dec, 10);
            if (cart[i1].qty > 1) { cart[i1].qty--; } else { cart.splice(i1, 1); }
            render();
        } else if (inc) {
            var i2 = parseInt(inc.dataset.inc, 10);
            if (cart[i2].qty < cart[i2].stok) { cart[i2].qty++; }
            render();
        } else if (del) {
            cart.splice(parseInt(del.dataset.del, 10), 1);
            render();
        }
    });

    /* Pencarian produk → sembunyikan yang tidak cocok */
    function filterProduk() {
        var q = cari.value.toLowerCase().trim();
        var visible = 0;
        if (list) {
            list.querySelectorAll('.pos-card').forEach(function (el) {
                var cocok = el.dataset.cari.indexOf(q) !== -1;
                el.style.display = cocok ? '' : 'none';
                if (cocok) { visible++; }
            });
        }
        if (produkKosong) { produkKosong.style.display = (q !== '' && visible === 0) ? 'block' : 'none'; }
        return visible;
    }

    cari.addEventListener('input', filterProduk);

    /* Enter = tambahkan produk pertama yang cocok (biar cepat untuk kasir) */
    cari.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') { return; }
        e.preventDefault();
        if (!list) { return; }
        var first = null;
        list.querySelectorAll('.pos-card').forEach(function (el) {
            if (!first && el.style.display !== 'none' && !el.classList.contains('out')) { first = el; }
        });
        if (first) {
            tambah(first);
            cari.value = '';
            filterProduk();
            cari.focus();
        }
    });

    btnClear.addEventListener('click', function () {
        cari.value = '';
        filterProduk();
        cari.focus();
    });

    document.getElementById('segMetode').addEventListener('click', function (e) {
        var btn = e.target.closest('.pos-metode__item');
        if (!btn) { return; }
        this.querySelectorAll('.pos-metode__item').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        metodeInput.value = btn.dataset.metode;
        render();
    });

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

    document.getElementById('btnKosongkan').addEventListener('click', function () {
        if (cart.length && !confirm('Kosongkan keranjang?')) { return; }
        cart = [];
        setUang(inputDiskon, 0);
        setUang(inputBayar, 0);
        render();
    });

    /* Aktifkan format ribuan & kirim angka bersih saat submit */
    bindUang(inputDiskon);
    bindUang(inputBayar);
    var posForm = document.getElementById('posForm');
    if (posForm) {
        posForm.addEventListener('submit', function () {
            inputDiskon.value = inputDiskon.dataset.raw || '0';
            inputBayar.value = inputBayar.dataset.raw || '0';
        });
    }

    filterProduk();
    render();
})();
</script>
</body>
</html>
