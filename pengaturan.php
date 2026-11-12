<?php
/**
 * Pengaturan Toko — profil toko (nama, alamat, kontak, logo, footer struk).
 * Nilai disimpan di tabel app_settings (key-value) dan dipakai di seluruh aplikasi.
 * Khusus superadmin.
 */
require_once __DIR__ . '/includes/functions.php';
require_superadmin();
csrf_check();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    /* Kembalikan logo ke bawaan */
    if ($action === 'hapus_logo') {
        setting_set('logo_path', '');
        flash_set('success', 'Logo toko dikembalikan ke logo bawaan.');
        redirect('pengaturan.php');
    }

    $nama    = trim($_POST['nama_toko'] ?? '');
    $alamat  = trim($_POST['alamat_toko'] ?? '');
    $telepon = trim($_POST['telepon_toko'] ?? '');
    $email   = trim($_POST['email_toko'] ?? '');
    $footer  = trim($_POST['footer_struk'] ?? '');
    $newLogo = null;

    if ($nama === '') {
        $errors[] = 'Nama toko wajib diisi.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Alamat email tidak valid.';
    }

    /* Unggah logo (opsional) */
    if (!empty($_FILES['logo']['name'])) {
        $file = $_FILES['logo'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Logo gagal diunggah (kode ' . (int) $file['error'] . ').';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Ukuran logo maksimal 2 MB.';
        } else {
            $info = @getimagesize($file['tmp_name']);
            if (!$info || !isset($allowed[$info['mime']])) {
                $errors[] = 'Format logo harus JPG, PNG, WEBP, atau GIF.';
            } else {
                $fname = 'logo-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $allowed[$info['mime']];
                if (!@move_uploaded_file($file['tmp_name'], __DIR__ . '/img/' . $fname)) {
                    $errors[] = 'Gagal menyimpan file logo. Periksa izin folder img/.';
                } else {
                    $newLogo = 'img/' . $fname;
                }
            }
        }
    }

    if (!$errors) {
        setting_set('nama_toko', $nama);
        setting_set('alamat_toko', $alamat);
        setting_set('telepon_toko', $telepon);
        setting_set('email_toko', $email);
        setting_set('footer_struk', $footer);
        if ($newLogo !== null) {
            setting_set('logo_path', $newLogo);
        }
        flash_set('success', 'Pengaturan toko berhasil disimpan.');
        redirect('pengaturan.php');
    }
}

$logoPath = app_logo();
$logoAda  = $logoPath !== '' && is_file(__DIR__ . '/' . $logoPath);

$page_title = 'Pengaturan Toko';
$page_sub   = 'Identitas toko yang tampil pada struk, surat jalan, dan aplikasi';
$active     = 'pengaturan';
require __DIR__ . '/includes/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-danger" style="margin-bottom:16px;">
        <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <div class="grid-2" style="align-items:start;">
        <div class="panel">
            <div class="panel__head">
                <div><h3>Identitas Toko</h3><p>Nama dan kontak ini dipakai di seluruh dokumen.</p></div>
            </div>
            <div class="panel__body">
                <div class="form-row">
                    <label for="namaToko">Nama Toko</label>
                    <input class="form-control" type="text" name="nama_toko" id="namaToko"
                           value="<?= e(setting('nama_toko', APP_NAME)) ?>" required>
                    <div class="form-hint">Tampil pada judul halaman, struk, dan surat jalan.</div>
                </div>
                <div class="form-row">
                    <label for="alamatToko">Alamat Toko</label>
                    <input class="form-control" type="text" name="alamat_toko" id="alamatToko"
                           value="<?= e(setting('alamat_toko', '')) ?>" placeholder="Alamat pusat / toko induk">
                </div>
                <div class="form-grid">
                    <div class="form-row">
                        <label for="teleponToko">Telepon</label>
                        <input class="form-control" type="text" name="telepon_toko" id="teleponToko"
                               value="<?= e(setting('telepon_toko', '')) ?>" placeholder="mis. 0717-422001">
                    </div>
                    <div class="form-row">
                        <label for="emailToko">Email</label>
                        <input class="form-control" type="text" name="email_toko" id="emailToko"
                               value="<?= e(setting('email_toko', '')) ?>" placeholder="mis. info@toko.id">
                    </div>
                </div>
                <div class="form-row">
                    <label for="footerStruk">Catatan Kaki Struk</label>
                    <input class="form-control" type="text" name="footer_struk" id="footerStruk"
                           value="<?= e(setting('footer_struk', 'Barang yang sudah dibeli tidak dapat ditukar/dikembalikan')) ?>">
                    <div class="form-hint">Teks yang tercetak di bagian bawah struk belanja.</div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel__head">
                <div><h3>Logo Toko</h3><p>Gambar tampil pada struk dan surat jalan.</p></div>
            </div>
            <div class="panel__body">
                <div class="logo-preview">
                    <?php if ($logoAda): ?>
                        <img src="<?= e($logoPath) ?>" alt="Logo toko">
                    <?php else: ?>
                        <div class="logo-preview__empty">Belum ada logo</div>
                    <?php endif; ?>
                </div>
                <div class="form-row" style="margin-top:14px;">
                    <label for="logoFile">Ganti Logo</label>
                    <input class="form-control" type="file" name="logo" id="logoFile" accept="image/png,image/jpeg,image/webp,image/gif">
                    <div class="form-hint">Format JPG/PNG/WEBP/GIF, maksimal 2 MB. Disarankan gambar persegi.</div>
                </div>
                <?php if ($logoAda): ?>
                    <button type="button" class="btn btn-ghost btn-sm" style="margin-top:6px;"
                            onclick="if(confirm('Kembalikan ke logo bawaan?')){document.getElementById('formHapusLogo').submit();}">
                        Kembalikan ke Logo Bawaan
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="flex gap-8" style="justify-content:flex-end;margin-top:16px;">
        <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Simpan Pengaturan
        </button>
    </div>
</form>

<form method="post" id="formHapusLogo" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="hapus_logo">
</form>

<?php
require __DIR__ . '/includes/footer.php';
