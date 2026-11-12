<?php
/**
 * Installer Vape Store
 * Membuat database, tabel, dan mengisi data awal (cabang, produk, pengguna, contoh penjualan).
 * Jalankan sekali melalui browser: http://localhost/vape%20store/install.php
 */

require_once __DIR__ . '/config/database.php';

$log        = [];
$resetReq   = isset($_GET['reset']);
$reset      = $resetReq && hash_equals(INSTALL_KEY, (string) ($_GET['key'] ?? ''));
$hasError   = false;
$lockFile   = __DIR__ . '/install.lock';

/**
 * Kunci pengaman: setelah instalasi sukses, file install.lock dibuat.
 * Selama file itu ada, installer menolak dijalankan (kecuali mode reset
 * dengan kunci yang benar) agar tidak bisa dipakai ulang oleh orang lain.
 */
if (is_file($lockFile) && !$reset) {
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalasi Terkunci</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card" style="max-width:560px;">
        <div class="login-logo">WG</div>
        <h1>Instalasi Terkunci</h1>
        <p class="sub">Aplikasi sudah terpasang.</p>
        <p class="form-hint" style="text-align:left;margin-top:14px;">
            Demi keamanan, installer menonaktifkan diri otomatis setelah instalasi selesai.
            Silakan hapus file <code>install.php</code> dan <code>install.lock</code> dari server.
        </p>
        <a class="btn btn-primary" href="login.php" style="width:100%;justify-content:center;margin-top:10px;">Masuk ke Aplikasi</a>
    </div>
</div>
</body>
</html>
    <?php
    exit;
}

function step(&$log, $msg, $ok = true)
{
    $log[] = ['msg' => $msg, 'ok' => $ok];
}

if ($resetReq && !$reset) {
    step($log, 'Permintaan reset diabaikan: kunci keamanan tidak sesuai.', false);
}

try {
    // 1. Koneksi ke server MySQL (tanpa memilih database dulu)
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    step($log, 'Terhubung ke server MySQL.');

    // 2. Buat database
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');
    step($log, 'Database `' . DB_NAME . '` siap.');

    // 3. Reset bila diminta
    if ($reset) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['stock_transfer_items', 'stock_transfers', 'goods_receipt_items', 'goods_receipts', 'sale_items', 'sales', 'products', 'categories', 'suppliers', 'app_settings', 'users', 'branches'] as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `$t`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        step($log, 'Tabel lama dihapus (mode reset).');
    }

    // 4. Buat tabel
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS branches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kode VARCHAR(20) NOT NULL UNIQUE,
            nama VARCHAR(100) NOT NULL,
            alamat VARCHAR(255) NULL,
            telepon VARCHAR(40) NULL,
            is_pusat TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(100) NOT NULL,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('superadmin','cabang','kasir') NOT NULL DEFAULT 'cabang',
            branch_id INT NULL,
            aktif TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(60) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(120) NOT NULL,
            kontak VARCHAR(100) NULL,
            telepon VARCHAR(40) NULL,
            alamat VARCHAR(255) NULL,
            catatan VARCHAR(255) NULL,
            created_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kode VARCHAR(30) NOT NULL UNIQUE,
            nama VARCHAR(120) NOT NULL,
            category_id INT NULL,
            harga DECIMAL(14,2) NOT NULL DEFAULT 0,
            harga_beli DECIMAL(14,2) NOT NULL DEFAULT 0,
            stok INT NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            CONSTRAINT fk_products_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice VARCHAR(40) NOT NULL UNIQUE,
            branch_id INT NOT NULL,
            user_id INT NULL,
            tanggal DATETIME NOT NULL,
            total DECIMAL(16,2) NOT NULL DEFAULT 0,
            metode_bayar VARCHAR(20) NOT NULL DEFAULT 'tunai',
            diskon DECIMAL(16,2) NOT NULL DEFAULT 0,
            hpp DECIMAL(16,2) NOT NULL DEFAULT 0,
            bayar DECIMAL(16,2) NOT NULL DEFAULT 0,
            kembalian DECIMAL(16,2) NOT NULL DEFAULT 0,
            catatan VARCHAR(255) NULL,
            created_at DATETIME NULL,
            KEY idx_sales_branch (branch_id),
            KEY idx_sales_tanggal (tanggal),
            CONSTRAINT fk_sales_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
            CONSTRAINT fk_sales_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sale_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT NOT NULL,
            product_id INT NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            harga DECIMAL(14,2) NOT NULL DEFAULT 0,
            harga_beli DECIMAL(14,2) NOT NULL DEFAULT 0,
            subtotal DECIMAL(16,2) NOT NULL DEFAULT 0,
            KEY idx_items_sale (sale_id),
            CONSTRAINT fk_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS goods_receipts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            branch_id INT NOT NULL,
            supplier_id INT NULL,
            tanggal DATE NOT NULL,
            expedisi VARCHAR(100) NULL,
            no_surat_jalan VARCHAR(60) NULL,
            catatan VARCHAR(255) NULL,
            total_item INT NOT NULL DEFAULT 0,
            total_unit INT NOT NULL DEFAULT 0,
            total_nilai DECIMAL(16,2) NOT NULL DEFAULT 0,
            user_id INT NULL,
            created_at DATETIME NULL,
            KEY idx_gr_branch (branch_id),
            KEY idx_gr_tanggal (tanggal),
            KEY idx_gr_supplier (supplier_id),
            CONSTRAINT fk_gr_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
            CONSTRAINT fk_gr_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
            CONSTRAINT fk_gr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS goods_receipt_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            receipt_id INT NOT NULL,
            product_id INT NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            harga_beli DECIMAL(14,2) NOT NULL DEFAULT 0,
            subtotal DECIMAL(16,2) NOT NULL DEFAULT 0,
            KEY idx_gri_receipt (receipt_id),
            CONSTRAINT fk_gri_receipt FOREIGN KEY (receipt_id) REFERENCES goods_receipts(id) ON DELETE CASCADE,
            CONSTRAINT fk_gri_product FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stock_transfers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_branch_id INT NOT NULL,
            to_branch_id INT NOT NULL,
            tanggal DATE NOT NULL,
            no_surat_jalan VARCHAR(60) NULL,
            owner_name VARCHAR(100) NULL,
            receiver_name VARCHAR(100) NULL,
            catatan VARCHAR(255) NULL,
            total_item INT NOT NULL DEFAULT 0,
            total_unit INT NOT NULL DEFAULT 0,
            total_nilai DECIMAL(16,2) NOT NULL DEFAULT 0,
            user_id INT NULL,
            created_at DATETIME NULL,
            KEY idx_st_from (from_branch_id),
            KEY idx_st_to (to_branch_id),
            KEY idx_st_tgl (tanggal),
            CONSTRAINT fk_st_from FOREIGN KEY (from_branch_id) REFERENCES branches(id),
            CONSTRAINT fk_st_to FOREIGN KEY (to_branch_id) REFERENCES branches(id),
            CONSTRAINT fk_st_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stock_transfer_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transfer_id INT NOT NULL,
            product_id INT NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            harga_beli DECIMAL(14,2) NOT NULL DEFAULT 0,
            subtotal DECIMAL(16,2) NOT NULL DEFAULT 0,
            KEY idx_sti_transfer (transfer_id),
            CONSTRAINT fk_sti_transfer FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
            CONSTRAINT fk_sti_product FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    step($log, 'Seluruh tabel berhasil dibuat.');

    /* ---- Migrasi kolom: pemasangan lama ikut diperbarui otomatis ---- */
    $columnExists = function (PDO $pdo, string $table, string $column): bool {
        $q = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $q->execute([DB_NAME, $table, $column]);
        return (int) $q->fetchColumn() > 0;
    };

    $migrasi = [
        'sales' => [
            'metode_bayar' => "VARCHAR(20) NOT NULL DEFAULT 'tunai' AFTER `total`",
            'diskon'       => "DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER `metode_bayar`",
            'hpp'          => "DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER `diskon`",
            'bayar'        => "DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER `hpp`",
            'kembalian'    => "DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER `bayar`",
        ],
        'sale_items' => [
            'harga_beli' => "DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `harga`",
        ],
        'goods_receipts' => [
            'supplier_id' => "INT NULL AFTER `branch_id`",
        ],
    ];

    $migrated = 0;
    foreach ($migrasi as $tabel => $kolom) {
        foreach ($kolom as $nama => $definisi) {
            if (!$columnExists($pdo, $tabel, $nama)) {
                $pdo->exec("ALTER TABLE `$tabel` ADD COLUMN `$nama` $definisi");
                $migrated++;
            }
        }
    }
    if ($migrated > 0) {
        step($log, "$migrated kolom baru ditambahkan (metode bayar, diskon, HPP, kembalian).");
    }

    /* Pastikan peran 'kasir' tersedia pada ENUM role (pemasangan versi lama) */
    $roleType = $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'"
    )->fetchColumn();
    if ($roleType !== false && stripos((string) $roleType, "'kasir'") === false) {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin','cabang','kasir') NOT NULL DEFAULT 'cabang'");
        step($log, "Peran 'kasir' ditambahkan ke tabel pengguna.");
    }

    /* Isi ulang data lama agar laporan laba langsung akurat */
    $pdo->exec(
        'UPDATE sale_items si JOIN products p ON p.id = si.product_id
         SET si.harga_beli = p.harga_beli
         WHERE si.harga_beli = 0 AND p.harga_beli > 0'
    );
    $pdo->exec(
        'UPDATE sales s
         SET s.hpp = (SELECT COALESCE(SUM(si.qty * si.harga_beli), 0) FROM sale_items si WHERE si.sale_id = s.id)
         WHERE s.hpp = 0'
    );
    $pdo->exec('UPDATE sales SET bayar = total WHERE bayar = 0');

    // 5. Isi data awal hanya bila masih kosong
    $cabangCount = (int) $pdo->query('SELECT COUNT(*) FROM branches')->fetchColumn();

    if ($cabangCount === 0) {
        $pdo->beginTransaction();
        /* --- Cabang --- */
        $cabang = [
            ['PGK', 'Toko Induk Pangkalpinang', 'Jl. Merdeka No. 1, Pangkalpinang', '0717-422001', 1],
            ['SGLT', 'Cabang Sungailiat', 'Jl. Sudirman No. 45, Sungailiat', '0717-922010', 0],
            ['TBL', 'Cabang Toboali', 'Jl. Jenderal Sudirman No. 12, Toboali', '0718-210022', 0],
            ['TJP', 'Cabang Tanjung Pandan', 'Jl. Srijaya No. 8, Tanjung Pandan', '0719-233011', 0],
            ['MGR', 'Cabang Manggar', 'Jl. Soekarno Hatta No. 20, Manggar', '0719-914033', 0],
        ];
        $ins = $pdo->prepare('INSERT INTO branches (kode, nama, alamat, telepon, is_pusat, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        foreach ($cabang as $c) {
            $ins->execute($c);
        }
        $branchIds = $pdo->query('SELECT kode, id FROM branches')->fetchAll(PDO::FETCH_KEY_PAIR);
        step($log, count($cabang) . ' cabang ditambahkan.');

        /* --- Kategori --- */
        $kategori = ['Liquid', 'Pod & Device', 'Coil & Atomizer', 'Mod & Battery', 'Aksesoris'];
        $insCat = $pdo->prepare('INSERT INTO categories (nama) VALUES (?)');
        foreach ($kategori as $k) { $insCat->execute([$k]); }
        $catIds = $pdo->query('SELECT nama, id FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);
        step($log, count($kategori) . ' kategori ditambahkan.');

        /* --- Produk --- */
        // [kode, nama, kategori, harga jual, harga beli, stok]
        $produk = [
            ['LQ-001', 'Liquid Saltnic Blueberry 30ml', 'Liquid', 95000, 62000, 400],
            ['LQ-002', 'Liquid Freebase Mango Ice 60ml', 'Liquid', 120000, 80000, 350],
            ['LQ-003', 'Liquid Saltnic Vanilla Custard 30ml', 'Liquid', 98000, 65000, 320],
            ['PD-001', 'Pod Caliburn G3', 'Pod & Device', 320000, 240000, 120],
            ['PD-002', 'Pod Xros 4 Mini', 'Pod & Device', 285000, 210000, 140],
            ['PD-003', 'Device Voopoo Drag X', 'Pod & Device', 550000, 430000, 60],
            ['CL-001', 'Coil Caliburn G3 0.6ohm', 'Coil & Atomizer', 45000, 28000, 500],
            ['CL-002', 'Coil Xros Series 1.0ohm', 'Coil & Atomizer', 40000, 25000, 520],
            ['AT-001', 'Atomizer RDA Dead Rabbit V3', 'Coil & Atomizer', 380000, 290000, 45],
            ['MD-001', 'Battery 18650 Sony VTC6', 'Mod & Battery', 85000, 58000, 260],
            ['MD-002', 'Mod Geekvape Aegis Legend 3', 'Mod & Battery', 720000, 580000, 40],
            ['AK-001', 'Cotton Bacon Prime', 'Aksesoris', 55000, 35000, 240],
            ['AK-002', 'Drip Tip Resin 810', 'Aksesoris', 35000, 18000, 320],
            ['AK-003', 'Vape Band Silicone', 'Aksesoris', 20000, 9000, 420],
            ['AK-004', 'Case Vape Waterproof', 'Aksesoris', 75000, 45000, 150],
        ];
        $insProd = $pdo->prepare('INSERT INTO products (kode, nama, category_id, harga, harga_beli, stok, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        foreach ($produk as $p) {
            $insProd->execute([$p[0], $p[1], $catIds[$p[2]] ?? null, $p[3], $p[4], $p[5]]);
        }
        step($log, count($produk) . ' produk ditambahkan.');

        /* --- Pengguna --- */
        $insUser = $pdo->prepare('INSERT INTO users (nama, username, password, role, branch_id, aktif, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())');
        $insUser->execute(['Super Admin', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'superadmin', $branchIds['PGK'], ]);
        $insUser->execute(['Kasir Pangkalpinang', 'kasir', password_hash('kasir123', PASSWORD_DEFAULT), 'kasir', $branchIds['PGK']]);
        $staf = [
            ['Staf Pangkalpinang', 'pgk', 'PGK'],
            ['Staf Sungailiat', 'sglt', 'SGLT'],
            ['Staf Toboali', 'tbl', 'TBL'],
            ['Staf Tanjung Pandan', 'tjp', 'TJP'],
            ['Staf Manggar', 'mgr', 'MGR'],
        ];
        foreach ($staf as $s) {
            $insUser->execute([$s[0], $s[1], password_hash('cabang123', PASSWORD_DEFAULT), 'cabang', $branchIds[$s[2]]]);
        }
        step($log, (count($staf) + 2) . ' pengguna ditambahkan (admin / admin123, kasir / kasir123, staf cabang / cabang123).');

        /* --- Contoh penjualan 45 hari terakhir --- */
        $produkRows = $pdo->query('SELECT id, harga, harga_beli, stok FROM products')->fetchAll();

        $insSale = $pdo->prepare(
            'INSERT INTO sales (invoice, branch_id, user_id, tanggal, total, metode_bayar, diskon, hpp, bayar, kembalian, catatan, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, NULL, NOW())'
        );
        $insItem = $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, qty, harga, harga_beli, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
        $updStok  = $pdo->prepare('UPDATE products SET stok = GREATEST(stok - ?, 0) WHERE id = ?');
        $updTotal = $pdo->prepare('UPDATE sales SET total = ? WHERE id = ?');
        $uidSel   = $pdo->prepare('SELECT id FROM users WHERE branch_id = ? ORDER BY id LIMIT 1');

        $metodeList = ['tunai', 'tunai', 'tunai', 'qris', 'qris', 'transfer'];

        $seq = 1;
        $trxCount = 0;
        foreach ($branchIds as $kode => $bid) {
            $uidSel->execute([$bid]);
            $userId = (int) $uidSel->fetchColumn() ?: null;

            for ($d = 44; $d >= 0; $d--) {
                $numTrx = random_int(1, 4);
                for ($t = 0; $t < $numTrx; $t++) {
                    $tanggal = date('Y-m-d', strtotime("-$d days")) . ' ' . sprintf('%02d:%02d:00', random_int(9, 20), random_int(0, 59));
                    $invoice = 'WG-' . date('Ymd', strtotime("-$d days")) . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
                    $seq++;

                    $total = 0;
                    $hpp   = 0;
                    $lines = [];
                    $nItem = random_int(1, 3);
                    $picked = (array) array_rand($produkRows, $nItem);
                    foreach ($picked as $pi) {
                        $pr  = $produkRows[$pi];
                        $qty = random_int(1, 3);
                        $sub = (float) $pr['harga'] * $qty;
                        $total += $sub;
                        $hpp   += (float) $pr['harga_beli'] * $qty;
                        $lines[] = [$pr['id'], $qty, $pr['harga'], $pr['harga_beli'], $sub];
                    }

                    $metode = $metodeList[array_rand($metodeList)];
                    if ($metode === 'tunai') {
                        $bayar = (int) (ceil($total / 50000) * 50000);
                        if ($bayar === (int) $total) { $bayar = (int) $total; }
                    } else {
                        $bayar = (int) $total;
                    }
                    $kembalian = max(0, $bayar - $total);

                    $insSale->execute([$invoice, $bid, $userId, $tanggal, $total, $metode, $hpp, $bayar, $kembalian]);
                    $saleId = (int) $pdo->lastInsertId();

                    foreach ($lines as $ln) {
                        $insItem->execute([$saleId, $ln[0], $ln[1], $ln[2], $ln[3], $ln[4]]);
                        $updStok->execute([$ln[1], $ln[0]]);
                    }
                    $updTotal->execute([$total, $saleId]);
                    $trxCount++;
                }
            }
        }
        // Jaga stok produk demo tetap tersedia agar POS dapat langsung diuji
        $pdo->exec('UPDATE products SET stok = 60 WHERE stok < 60');
        $pdo->commit();
        step($log, $trxCount . ' contoh transaksi penjualan dibuat untuk 45 hari terakhir.');
    } else {
        step($log, 'Data awal sudah ada — proses seeding dilewati. Gunakan ?reset=1 untuk membangun ulang.', true);
    }

    step($log, 'Instalasi selesai. Anda dapat masuk ke aplikasi.');
} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $hasError = true;
    step($log, 'ERROR: ' . $ex->getMessage(), false);
}

/* Buat file kunci agar installer tidak bisa dijalankan ulang oleh orang lain. */
if (!$hasError) {
    @file_put_contents($lockFile, 'Terpasang pada: ' . date('c') . PHP_EOL);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalasi &middot; <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card" style="max-width:560px;">
        <div class="login-logo">WG</div>
        <h1>Instalasi <?= APP_NAME ?></h1>
        <p class="sub">Penyiapan database &amp; data awal</p>

        <div style="display:flex;flex-direction:column;gap:10px;margin:20px 0;">
            <?php foreach ($log as $l): ?>
                <div style="display:flex;gap:10px;align-items:flex-start;font-size:13px;">
                    <span style="color:<?= $l['ok'] ? 'var(--primary)' : 'var(--red)' ?>;font-weight:700;"><?= $l['ok'] ? '&#10003;' : '&#10007;' ?></span>
                    <span><?= htmlspecialchars($l['msg']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$hasError): ?>
            <a class="btn btn-primary" href="login.php" style="width:100%;justify-content:center;">Masuk ke Aplikasi</a>
            <p class="form-hint" style="text-align:center;margin-top:14px;">
                Instalasi selesai &amp; installer otomatis terkunci. Demi keamanan,
                hapus file <code>install.php</code> dan <code>install.lock</code> dari server.
            </p>
        <?php else: ?>
            <p class="login-error">Periksa konfigurasi koneksi pada <code>config/database.php</code> lalu muat ulang halaman.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
