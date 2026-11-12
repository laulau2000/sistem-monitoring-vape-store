<?php
/**
 * Profil Saya — ubah data pribadi & ganti password sendiri.
 * Dapat diakses semua peran, termasuk kasir.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

$user   = current_user();
$errors = [];

function profil_refresh(): array
{
    $stmt = db()->prepare(
        'SELECT u.nama, u.username, u.role, b.nama AS branch_nama, b.kode AS branch_kode
         FROM users u LEFT JOIN branches b ON b.id = u.branch_id
         WHERE u.id = ? LIMIT 1'
    );
    $stmt->execute([(int) current_user()['id']]);
    return $stmt->fetch() ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    /* Ubah nama tampilan */
    if ($action === 'profil') {
        $nama = trim($_POST['nama'] ?? '');
        if ($nama === '') {
            $errors[] = 'Nama wajib diisi.';
        } else {
            db()->prepare('UPDATE users SET nama = ? WHERE id = ?')->execute([$nama, (int) $user['id']]);
            $_SESSION['user']['nama'] = $nama;
            flash_set('success', 'Nama berhasil diperbarui.');
            redirect('profil.php');
        }
    }

    /* Ganti password */
    if ($action === 'password') {
        $lama   = $_POST['password_lama'] ?? '';
        $baru   = $_POST['password_baru'] ?? '';
        $ulang  = $_POST['password_ulang'] ?? '';

        $stmt = db()->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $user['id']]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($lama, $hash)) {
            $errors[] = 'Password lama tidak sesuai.';
        }
        if (strlen($baru) < 6) {
            $errors[] = 'Password baru minimal 6 karakter.';
        }
        if ($baru !== $ulang) {
            $errors[] = 'Konfirmasi password baru tidak sama.';
        }

        if (!$errors) {
            $upd = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
            $upd->execute([password_hash($baru, PASSWORD_DEFAULT), (int) $user['id']]);
            flash_set('success', 'Password berhasil diganti.');
            redirect('profil.php');
        }
    }
}

$info = profil_refresh();
$roleLabel = ['superadmin' => 'Super Admin', 'cabang' => 'Staf Cabang', 'kasir' => 'Kasir'][$user['role']] ?? $user['role'];

$page_title = 'Profil Saya';
$page_sub   = 'Kelola data pribadi dan keamanan akun Anda';
$active     = 'profil';
require __DIR__ . '/includes/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-danger" style="margin-bottom:16px;">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="grid-2" style="align-items:start;">
    <div class="panel">
        <div class="panel__head">
            <div><h3>Informasi Akun</h3><p>Detail akun yang sedang Anda gunakan.</p></div>
        </div>
        <div class="panel__body">
            <div class="info-list">
                <div class="info-list__row"><span>Username</span><strong><?= e($user['username']) ?></strong></div>
                <div class="info-list__row"><span>Peran</span><strong><?= e($roleLabel) ?></strong></div>
                <div class="info-list__row"><span>Cabang</span><strong><?= e($info['branch_nama'] ?? 'Semua Cabang') ?></strong></div>
            </div>

            <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profil">
                <div class="form-row">
                    <label for="prNama">Nama Tampilan</label>
                    <input class="form-control" type="text" name="nama" id="prNama" value="<?= e($user['nama']) ?>" required>
                    <div class="form-hint">Nama ini tercetak sebagai nama kasir pada struk.</div>
                </div>
                <button type="submit" class="btn btn-primary">Simpan Nama</button>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <div><h3>Ganti Password</h3><p>Gunakan password yang kuat dan mudah Anda ingat.</p></div>
        </div>
        <div class="panel__body">
            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <div class="form-row">
                    <label for="pwLama">Password Lama</label>
                    <input class="form-control" type="password" name="password_lama" id="pwLama" required>
                </div>
                <div class="form-row">
                    <label for="pwBaru">Password Baru</label>
                    <input class="form-control" type="password" name="password_baru" id="pwBaru" minlength="6" required>
                    <div class="form-hint">Minimal 6 karakter.</div>
                </div>
                <div class="form-row">
                    <label for="pwUlang">Ulangi Password Baru</label>
                    <input class="form-control" type="password" name="password_ulang" id="pwUlang" minlength="6" required>
                </div>
                <button type="submit" class="btn btn-primary">Ganti Password</button>
            </form>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/includes/footer.php';
