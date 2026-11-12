<?php
/**
 * Cetak Surat Jalan — dokumen transfer barang antar cabang.
 * Dibuka di tab baru; user tekan Ctrl+P untuk simpan sebagai PDF.
 * Memuat: logo, identitas toko, data transfer, Nama Owner, Staf Penerima,
 * daftar barang, dan kolom tanda tangan.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

if (is_kasir()) { redirect('pos.php'); }

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Transfer tidak ditemukan.');
}

$scope = scoped_branch_id();

$stmt = db()->prepare(
    'SELECT s.*, bf.nama AS nama_asal, bf.alamat AS alamat_asal, bf.telepon AS telepon_asal,
            bt.nama AS nama_tujuan, bt.alamat AS alamat_tujuan, bt.telepon AS telepon_tujuan,
            u.nama AS petugas
     FROM stock_transfers s
     LEFT JOIN branches bf ON bf.id = s.from_branch_id
     LEFT JOIN branches bt ON bt.id = s.to_branch_id
     LEFT JOIN users u ON u.id = s.user_id
     WHERE s.id = ?'
);
$stmt->execute([$id]);
$tr = $stmt->fetch();

if (!$tr) {
    die('Transfer tidak ditemukan.');
}

/* Staf cabang hanya boleh melihat transfer dari cabangnya sendiri. */
if ($scope !== null && (int) $tr['from_branch_id'] !== $scope) {
    die('Anda tidak berhak melihat dokumen ini.');
}

$items = db()->prepare(
    'SELECT ti.qty, ti.harga_beli, ti.subtotal, p.kode, p.nama
     FROM stock_transfer_items ti JOIN products p ON p.id = ti.product_id
     WHERE ti.transfer_id = ? ORDER BY ti.id'
);
$items->execute([$id]);
$barang = $items->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan #<?= str_pad((string) $id, 5, '0', STR_PAD_LEFT) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 24px;
            background: #fff;
            font-size: 12px;
            line-height: 1.45;
        }
        .doc { max-width: 780px; margin: 0 auto; }
        .head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 14px;
            border-bottom: 2px solid #1e293b;
            margin-bottom: 18px;
        }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand img { width: 48px; height: 48px; object-fit: contain; }
        .brand h1 { font-size: 20px; font-weight: 800; margin: 0; letter-spacing: -.3px; }
        .brand p { font-size: 11px; color: #64748b; margin: 2px 0 0; }
        .doc-title { text-align: right; }
        .doc-title h2 {
            font-size: 18px; font-weight: 800; margin: 0;
            color: #b91c1c; text-transform: uppercase; letter-spacing: .5px;
        }
        .doc-title p { font-size: 11px; color: #64748b; margin: 3px 0 0; }

        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 18px;
        }
        .meta-box {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
        }
        .meta-box h3 {
            font-size: 11px; text-transform: uppercase; letter-spacing: .5px;
            color: #64748b; margin: 0 0 6px;
        }
        .meta-row { display: flex; justify-content: space-between; padding: 2px 0; }
        .meta-row span:first-child { color: #64748b; }
        .meta-row strong { font-weight: 600; }

        .parties {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 12px;
            align-items: stretch;
            margin-bottom: 18px;
        }
        .party {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
        }
        .party h3 {
            font-size: 11px; text-transform: uppercase; letter-spacing: .5px;
            color: #64748b; margin: 0 0 6px;
        }
        .party .nama { font-weight: 700; font-size: 13px; }
        .party .alamat { font-size: 11px; color: #64748b; margin-top: 2px; }
        .arrow {
            display: flex; align-items: center; justify-content: center;
            color: #b91c1c; font-size: 22px; font-weight: 800;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 12px;
        }
        table.items th {
            background: #1e293b;
            color: #fff;
            text-align: left;
            padding: 8px 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        table.items td {
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        table.items tbody tr:nth-child(even) { background: #f8fafc; }
        table.items .num { text-align: right; font-family: 'JetBrains Mono', monospace; }
        table.items .no { width: 40px; text-align: center; }
        table.items .qty { width: 80px; }

        .total-box {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        .total-box table { border-collapse: collapse; }
        .total-box td { padding: 4px 14px; font-size: 12px; }
        .total-box td:first-child { color: #64748b; }
        .total-box td:last-child { font-family: 'JetBrains Mono', monospace; font-weight: 700; text-align: right; }
        .total-box .grand td { font-size: 14px; color: #b91c1c; border-top: 2px solid #1e293b; }

        .signatures {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-top: 30px;
        }
        .sig-box { text-align: center; }
        .sig-box .label {
            font-size: 11px; color: #64748b;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 6px; margin-bottom: 60px;
        }
        .sig-box .name {
            font-size: 12px; font-weight: 700;
            border-top: 1px solid #1e293b;
            padding-top: 6px;
        }

        .note {
            margin-top: 18px;
            padding: 8px 12px;
            background: #f8fafc;
            border-left: 3px solid #b91c1c;
            font-size: 11px;
            color: #64748b;
        }

        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
        .toolbar {
            position: fixed; bottom: 20px; right: 20px;
            display: flex; gap: 10px; z-index: 100;
        }
        .toolbar button {
            padding: 10px 18px; border: none; border-radius: 8px;
            font-size: 13px; font-weight: 600; cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
        }
        .btn-print { background: #b91c1c; color: #fff; }
        .btn-close { background: #f1f5f9; color: #1e293b; }
    </style>
</head>
<body>
<div class="toolbar no-print">
    <button class="btn-close" onclick="tutupSJ()">Tutup</button>
    <button class="btn-print" onclick="window.print()">Cetak / Simpan PDF</button>
</div>

<script>
    /* Tutup jendela; jika tab tidak dibuka oleh script, kembali ke halaman sebelumnya. */
    function tutupSJ() {
        window.close();
        setTimeout(function () {
            if (!window.closed) {
                window.location.href = document.referrer || 'barang-keluar.php';
            }
        }, 250);
    }
</script>

<div class="doc">
    <div class="head">
        <div class="brand">
            <img src="<?= e(app_logo()) ?>" alt="Logo">
            <div>
                <h1><?= e(app_name()) ?></h1>
                <p><?= e($tr['alamat_asal'] ?: setting('alamat_toko', '')) ?> · Telp: <?= e($tr['telepon_asal'] ?: setting('telepon_toko', '') ?: '-') ?></p>
            </div>
        </div>
        <div class="doc-title">
            <h2>Surat Jalan</h2>
            <p>No: <?= e($tr['no_surat_jalan'] ?: 'SJ-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT)) ?></p>
            <p>Tanggal: <?= e(date('d/m/Y', strtotime($tr['tanggal']))) ?></p>
        </div>
    </div>

    <div class="parties">
        <div class="party">
            <h3>Dikirim Dari</h3>
            <div class="nama"><?= e($tr['nama_asal']) ?></div>
            <div class="alamat"><?= e($tr['alamat_asal'] ?: '-') ?></div>
            <div class="alamat">Telp: <?= e($tr['telepon_asal'] ?: '-') ?></div>
        </div>
        <div class="arrow">➜</div>
        <div class="party">
            <h3>Dikirim Ke</h3>
            <div class="nama"><?= e($tr['nama_tujuan']) ?></div>
            <div class="alamat"><?= e($tr['alamat_tujuan'] ?: '-') ?></div>
            <div class="alamat">Telp: <?= e($tr['telepon_tujuan'] ?: '-') ?></div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th class="no">No</th>
                <th>Kode Produk</th>
                <th>Nama Produk</th>
                <th class="num qty">Jumlah</th>
                <th class="num">Harga Beli</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; $totalUnit = 0; $totalNilai = 0; foreach ($barang as $b):
                $totalUnit += (int) $b['qty'];
                $totalNilai += (float) $b['subtotal']; ?>
                <tr>
                    <td class="no"><?= $no++ ?></td>
                    <td><?= e($b['kode']) ?></td>
                    <td><?= e($b['nama']) ?></td>
                    <td class="num"><?= angka($b['qty']) ?></td>
                    <td class="num"><?= rupiah($b['harga_beli']) ?></td>
                    <td class="num"><?= rupiah($b['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total-box">
        <table>
            <tr>
                <td>Total Item</td>
                <td><?= angka(count($barang)) ?> jenis</td>
            </tr>
            <tr>
                <td>Total Unit</td>
                <td><?= angka($totalUnit) ?> pcs</td>
            </tr>
            <tr class="grand">
                <td>Total Nilai (HPP)</td>
                <td><?= rupiah($totalNilai) ?></td>
            </tr>
        </table>
    </div>

    <?php if (!empty($tr['catatan'])): ?>
        <div class="note"><strong>Catatan:</strong> <?= e($tr['catatan']) ?></div>
    <?php endif; ?>

    <div class="signatures">
        <div class="sig-box">
            <div class="label">Pemilik / Owner</div>
            <div class="name"><?= e($tr['owner_name'] ?: '-') ?></div>
        </div>
        <div class="sig-box">
            <div class="label">Petugas Pengirim</div>
            <div class="name"><?= e($tr['petugas'] ?: '-') ?></div>
        </div>
        <div class="sig-box">
            <div class="label">Staf Unit Penerima</div>
            <div class="name"><?= e($tr['receiver_name'] ?: '-') ?></div>
        </div>
        <div class="sig-box">
            <div class="label">Mengetahui (Admin)</div>
            <div class="name">________________</div>
        </div>
    </div>
</div>

<script>
    window.onload = function () {
        setTimeout(function () { window.print(); }, 400);
    };
</script>
</body>
</html>
