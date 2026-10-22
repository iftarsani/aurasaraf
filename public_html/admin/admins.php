<?php
/**
 * Urus akaun admin. Hanya Pemilik.
 *
 * Reset kata laluan tidak menghantar e-mel — ia menjana kata laluan sementara,
 * memaparkannya SEKALI kepada pemilik untuk diserahkan, dan menaikkan bendera
 * must_change_password supaya pemiliknya wajib menukarnya sebelum guna panel.
 */
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_layout.php';
$admin = require_owner();

/** Bilangan pemilik — dipakai untuk menghalang pemilik terakhir hilang. */
$owner_count = fn(): int => (int)db()->query("SELECT COUNT(*) FROM admins WHERE role = 'owner'")->fetchColumn();

if (is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    // Sasaran mesti wujud untuk semua tindakan kecuali cipta.
    $target = null;
    if ($action !== 'create') {
        $st = db()->prepare('SELECT id, username, role FROM admins WHERE id = ?');
        $st->execute([$id]);
        $target = $st->fetch() ?: null;
        if (!$target) { flash('error', 'Admin tidak dijumpai.'); redirect('admin/admins.php'); }
    }

    if ($action === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $pass     = (string)($_POST['password'] ?? '');
        $nama     = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
        $emel     = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 190);
        $fon      = mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 30);
        $role     = ($_POST['role'] ?? 'staff') === 'owner' ? 'owner' : 'staff';

        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            flash('error', 'Username 3–50 aksara (huruf, nombor, _ . -).');
        } elseif (strlen($pass) < 10) {
            flash('error', 'Kata laluan sekurang-kurangnya 10 aksara.');
        } elseif ($emel !== '' && !filter_var($emel, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'E-mel tidak sah.');
        } else {
            $dup = db()->prepare('SELECT 1 FROM admins WHERE username = ?');
            $dup->execute([$username]);
            if ($dup->fetchColumn()) {
                flash('error', 'Username itu sudah digunakan.');
            } else {
                db()->prepare('INSERT INTO admins (username, password_hash, name, email, phone, role)
                               VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$username, password_hash($pass, PASSWORD_DEFAULT), $nama, $emel, $fon, $role]);
                flash('success', 'Admin ' . $username . ' ditambah.');
            }
        }

    } elseif ($action === 'update') {
        $nama = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
        $emel = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 190);
        $fon  = mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 30);
        $role = ($_POST['role'] ?? 'staff') === 'owner' ? 'owner' : 'staff';

        if ($emel !== '' && !filter_var($emel, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'E-mel tidak sah — tiada perubahan disimpan.');
        } elseif ($target['role'] === 'owner' && $role === 'staff' && $owner_count() <= 1) {
            flash('error', 'Tidak boleh menurunkan pemilik terakhir — lantik pemilik lain dahulu.');
        } else {
            db()->prepare('UPDATE admins SET name = ?, email = ?, phone = ?, role = ? WHERE id = ?')
                ->execute([$nama, $emel, $fon, $role, $target['id']]);
            flash('success', 'Admin ' . $target['username'] . ' dikemas kini.');
        }

    } elseif ($action === 'reset') {
        $baharu = admin_temp_password();
        db()->prepare('UPDATE admins SET password_hash = ?, must_change_password = 1 WHERE id = ?')
            ->execute([password_hash($baharu, PASSWORD_DEFAULT), $target['id']]);
        // Buang sekatan kadar supaya pemiliknya boleh terus log masuk.
        db()->prepare('DELETE FROM login_attempts WHERE username = ?')->execute([$target['username']]);
        flash('success', 'Kata laluan sementara untuk ' . $target['username'] . ' — SALIN SEKARANG, ia tidak dipapar lagi: ' . $baharu);

    } elseif ($action === 'delete') {
        if ((int)$target['id'] === (int)$admin['id']) {
            flash('error', 'Anda tidak boleh memadam akaun sendiri.');
        } elseif ($target['role'] === 'owner' && $owner_count() <= 1) {
            flash('error', 'Tidak boleh memadam pemilik terakhir.');
        } else {
            db()->prepare('DELETE FROM admins WHERE id = ?')->execute([$target['id']]);
            flash('success', 'Admin ' . $target['username'] . ' dipadam.');
        }
    }
    redirect('admin/admins.php');
}

$senarai = db()->query('SELECT id, username, name, email, phone, role, must_change_password, last_login, created_at
                          FROM admins ORDER BY role = "owner" DESC, username')->fetchAll();
$roles = admin_roles();
admin_header('Admin', 'admins.php');
?>
<p class="text-sm text-slate-600 mb-4">Pemilik boleh menambah, mengemas kini, reset kata laluan dan memadam admin. Staf hanya mengurus pesanan, produk dan stok, serta menukar kata laluan sendiri.</p>

<div class="grid lg:grid-cols-3 gap-4">
  <div class="lg:col-span-2 space-y-3">
    <?php foreach ($senarai as $a):
        $saya  = (int)$a['id'] === (int)$admin['id'];
        $label = trim((string)$a['name']) !== '' ? $a['name'] : $a['username']; ?>
      <div class="bg-white rounded-xl border p-5">
        <div class="flex items-start justify-between gap-3 mb-3">
          <div>
            <h2 class="font-semibold"><?= e($label) ?>
              <span class="text-xs font-normal rounded-full px-2 py-0.5 <?= $a['role'] === 'owner' ? 'bg-brand-gold/20 text-brand-gold' : 'bg-slate-200 text-slate-700' ?>"><?= e($roles[$a['role']] ?? $a['role']) ?></span>
              <?php if ($saya): ?><span class="text-xs font-normal rounded-full px-2 py-0.5 bg-emerald-100 text-emerald-800">Anda</span><?php endif; ?>
              <?php if (!empty($a['must_change_password'])): ?><span class="text-xs font-normal rounded-full px-2 py-0.5 bg-amber-100 text-amber-900">Perlu tukar kata laluan</span><?php endif; ?>
            </h2>
            <p class="text-xs text-slate-500"><?= e($a['username']) ?> · dicipta <?= e(date('d/m/Y', strtotime((string)$a['created_at']))) ?>
              · <?= $a['last_login'] ? 'log masuk terakhir ' . e(date('d/m/Y H:i', strtotime((string)$a['last_login']))) : 'belum pernah log masuk' ?></p>
          </div>
        </div>

        <form method="post" class="grid sm:grid-cols-2 gap-3"><?= csrf_field() ?>
          <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <div><label class="lbl">Nama penuh</label><input name="name" class="inp" maxlength="100" value="<?= e($a['name']) ?>"></div>
          <div><label class="lbl">E-mel</label><input name="email" type="email" class="inp" maxlength="190" value="<?= e($a['email']) ?>"></div>
          <div><label class="lbl">No. telefon</label><input name="phone" class="inp" maxlength="30" value="<?= e($a['phone']) ?>"></div>
          <div><label class="lbl">Peranan</label>
            <select name="role" class="inp">
              <?php foreach ($roles as $k => $v): ?><option value="<?= e($k) ?>" <?= $a['role'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select></div>
          <div class="sm:col-span-2"><button class="btn bg-brand text-white">Simpan</button></div>
        </form>

        <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t">
          <form method="post" data-confirm="Reset kata laluan <?= e($a['username']) ?>? Kata laluan semasa akan dibatalkan serta-merta." data-confirm-ok="Reset"><?= csrf_field() ?>
            <input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <button class="btn bg-amber-500 text-white">Reset kata laluan</button></form>
          <?php if (!$saya): ?>
            <form method="post" data-confirm="Padam admin <?= e($a['username']) ?>? Tindakan ini tidak boleh dibatalkan." data-confirm-ok="Padam"><?= csrf_field() ?>
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <button class="btn bg-rose-600 text-white">Padam</button></form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <form method="post" class="bg-white rounded-xl border p-5 space-y-3 self-start"><?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <h2 class="font-semibold">Tambah admin</h2>
    <div><label class="lbl">Username</label><input name="username" class="inp" required maxlength="50" pattern="[A-Za-z0-9_.\-]{3,50}" placeholder="cth. aisyah"></div>
    <div><label class="lbl">Nama penuh</label><input name="name" class="inp" maxlength="100"></div>
    <div><label class="lbl">E-mel</label><input name="email" type="email" class="inp" maxlength="190"></div>
    <div><label class="lbl">No. telefon</label><input name="phone" class="inp" maxlength="30"></div>
    <div><label class="lbl">Peranan</label>
      <select name="role" class="inp"><option value="staff">Staf</option><option value="owner">Pemilik</option></select></div>
    <div><label class="lbl">Kata laluan (min 10)</label><?= pw_input('password', 'Kata laluan permulaan') ?></div>
    <button class="btn bg-brand text-white">Tambah</button>
  </form>
</div>
<?php admin_footer();
