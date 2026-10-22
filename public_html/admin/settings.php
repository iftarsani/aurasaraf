<?php
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_layout.php';
$admin = require_admin();
// Nota: testimonials_show ialah checkbox — bila tidak ditanda ia langsung tidak
// dihantar, jadi gelung di bawah menyimpannya sebagai '' iaitu MATI. Itu memang
// kelakuan yang dikehendaki.
$keys = ['shop_name', 'company', 'shop_address', 'shop_email', 'whatsapp', 'whatsapp2', 'notify_email', 'ship_sm', 'ship_ss', 'free_min', 'bank_name', 'bank_acc', 'bank_holder', 'ssm_no', 'mal_no', 'kkliu_no', 'testimonials_show', 'pay_transfer', 'pay_whatsapp', ...array_keys(socials()),
    'spx_sender_name', 'spx_sender_phone', 'spx_sender_address', 'spx_sender_postcode', 'spx_sender_city', 'spx_sender_state',
    'spx_collect', 'spx_pack_g', 'rate_courier', 'rate_markup', 'jnt_service', 'jnt_paytype'];

if (is_post()) {
    csrf_check();
    if (($_POST['action'] ?? '') === 'password') {
        $st = db()->prepare('SELECT password_hash FROM admins WHERE id = ?'); $st->execute([$admin['id']]);
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify((string)($_POST['current_password'] ?? ''), (string)$st->fetchColumn())) flash('error', 'Kata laluan semasa salah.');
        elseif (strlen($new) < 10)                       flash('error', 'Kata laluan baharu sekurang-kurangnya 10 aksara.');
        elseif ($new !== ($_POST['new_password2'] ?? '')) flash('error', 'Kata laluan baharu tidak sepadan.');
        else {
            // Menukar kata laluan juga melepaskan paksaan selepas reset oleh pemilik.
            db()->prepare('UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            session_regenerate_id(true);
            flash('success', 'Kata laluan ditukar.');
        }
    } elseif (($_POST['action'] ?? '') === 'spx_verify') {
        try {
            spx_verify_account() ? flash('success', 'SPX: kelayakan sah — sambungan berjaya.') : flash('error', 'SPX: user_id / user_secret tidak sepadan.');
        } catch (Throwable $ex) {
            flash('error', $ex->getMessage());
        }
    } elseif (($_POST['action'] ?? '') === 'jnt_verify') {
        try {
            jnt_verify_account();
            flash('success', 'J&T: kelayakan sah — sambungan berjaya' . (jnt_sandbox() ? ' (sandbox)' : '') . '.');
        } catch (Throwable $ex) {
            flash('error', $ex->getMessage());
        }
    } elseif (($_POST['action'] ?? '') === 'profile') {
        $nama  = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
        $emel  = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 190);
        $fon   = mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 30);
        if ($emel !== '' && !filter_var($emel, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'E-mel tidak sah — profil tidak disimpan.');
        } else {
            db()->prepare('UPDATE admins SET name = ?, email = ?, phone = ? WHERE id = ?')
                ->execute([$nama, $emel, $fon, $admin['id']]);
            flash('success', 'Profil disimpan.');
        }
    } else {
        foreach ($keys as $k) {
            $v = mb_substr(trim((string)($_POST[$k] ?? '')), 0, 255);
            if (in_array($k, ['ship_sm', 'ship_ss', 'free_min', 'rate_markup'], true)) $v = (string)max(0, round((float)$v, 2));
            if ($k === 'spx_pack_g')  $v = (string)max(0, min(5000, (int)$v));
            if ($k === 'spx_collect') $v = $v === '1' ? '1' : '2';
            if ($k === 'jnt_service') $v = $v === '1' ? '1' : '6';
            if ($k === 'jnt_paytype') $v = $v === 'PP_CASH' ? 'PP_CASH' : 'PP_PM';
            if ($k === 'rate_courier' && !in_array($v, ['spx', 'jnt'], true)) $v = '';
            if ($k === 'spx_sender_state' && $v !== '' && !isset(states()[$v])) $v = '';
            if ($k === 'spx_sender_postcode' && $v !== '' && !preg_match('/^\d{5}$/', $v)) {
                flash('error', 'Poskod pengirim mesti 5 digit — nilai lama dikekalkan.');
                continue;
            }
            // Kedua-dua medan e-mel disahkan; shop_email dipapar pada invois/resit.
            if (in_array($k, ['notify_email', 'shop_email'], true) && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                flash('error', ($k === 'notify_email' ? 'E-mel notifikasi' : 'E-mel kedai') . ' tidak sah — nilai lama dikekalkan.');
                continue;
            }
            // Pautan media sosial mesti https ke platform itu sendiri.
            if (isset(socials()[$k]) && $v !== '' && !social_url_ok($v, socials()[$k][1])) {
                flash('error', 'Pautan ' . socials()[$k][0] . ' tidak sah — mesti bermula https:// dan menuju ke ' . socials()[$k][1][0] . '. Nilai lama dikekalkan.');
                continue;
            }
            setting_set($k, $v);
        }
        try {
            $qr = handle_upload('qr_image', PUBLIC_PATH . '/uploads', MIME_IMAGES, 3 * 1048576);
            $old = (string)setting('qr_image');
            if ($qr !== '') { if ($old) @unlink(PUBLIC_PATH . '/uploads/' . basename($old)); setting_set('qr_image', $qr); }
            elseif (isset($_POST['remove_qr']) && $old) { @unlink(PUBLIC_PATH . '/uploads/' . basename($old)); setting_set('qr_image', ''); }
            flash('success', 'Tetapan disimpan.');
        } catch (RuntimeException $ex) { flash('error', $ex->getMessage()); }
    }
    redirect('admin/settings.php');
}
$s = settings_all(true);
$f = fn($k, $label, $type = 'text', $ph = '') => '<div><label class="lbl">' . e($label) . '</label><input name="' . $k . '" type="' . $type . '" ' . ($type === 'number' ? 'step="0.01" min="0" ' : '') . 'class="inp" placeholder="' . e($ph) . '" value="' . e($s[$k] ?? '') . '"></div>';
admin_header('Tetapan', 'settings.php');
?>
<div class="grid lg:grid-cols-3 gap-4">
<form method="post" enctype="multipart/form-data" class="lg:col-span-2 bg-white rounded-xl border p-5 space-y-5">
  <?= csrf_field() ?>
  <fieldset class="grid md:grid-cols-2 gap-4"><legend class="font-semibold mb-2">Kedai</legend>
    <?= $f('shop_name', 'Nama kedai') ?><?= $f('company', 'Nama syarikat') ?><?= $f('whatsapp', 'WhatsApp utama', 'text', '0183775941') ?><?= $f('whatsapp2', 'WhatsApp kedua') ?>
    <?= $f('notify_email', 'E-mel notifikasi pesanan', 'email', 'pemilik@domain.com.my') ?>
    <?= $f('shop_email', 'E-mel dipapar pada invois', 'email', 'kedai@domain.com.my') ?>
    <div class="md:col-span-2"><label class="lbl">Alamat perniagaan <span class="font-normal">(dipapar pada invois/resit)</span></label>
      <textarea name="shop_address" rows="3" maxlength="255" class="inp" placeholder="No 12, Jalan Perusahaan 3&#10;08000 Sungai Petani&#10;Kedah"><?= e($s['shop_address'] ?? '') ?></textarea></div></fieldset>
  <fieldset class="grid md:grid-cols-3 gap-4"><legend class="font-semibold mb-2">Media sosial (dipapar di footer bila diisi)</legend>
    <?php foreach (socials() as $k => [$label, $hosts]): ?><?= $f($k, $label, 'url', 'https://www.' . $hosts[0] . '/…') ?><?php endforeach; ?></fieldset>
  <fieldset class="grid md:grid-cols-3 gap-4"><legend class="font-semibold mb-2">Penghantaran</legend>
    <?= $f('ship_sm', 'Semenanjung (RM)', 'number') ?><?= $f('ship_ss', 'Sabah/Sarawak/Labuan (RM)', 'number') ?><?= $f('free_min', 'Pos percuma bila beli ≥ RM (0 = tiada)', 'number') ?>
    <div class="md:col-span-2"><label class="lbl">Kos pos di checkout</label><select name="rate_courier" class="inp">
      <?php foreach (['' => 'Kadar tetap di atas', 'spx' => 'Ikut kadar SPX Express (poskod + berat)', 'jnt' => 'Ikut kadar J&T Express (poskod + berat)'] as $rk => $rl): ?>
        <option value="<?= e($rk) ?>" <?= ($s['rate_courier'] ?? '') === $rk ? 'selected' : '' ?>><?= e($rl) ?></option><?php endforeach; ?></select></div>
    <?= $f('rate_markup', 'Markup atas kadar kurier (RM)', 'number', '0') ?>
    <p class="md:col-span-3 text-xs text-slate-500">Kurier utama hanya menentukan harga yang dicaj kepada pembeli — anda tetap boleh hantar setiap pesanan guna mana-mana kurier di halaman pesanan.
      Kadar tetap dipakai bila tiada kurier utama, dan sebagai sandaran bila kurier itu tidak menjawab.</p></fieldset>
  <fieldset class="grid md:grid-cols-3 gap-4" id="pengirim"><legend class="font-semibold mb-2">Alamat pengirim (untuk tempahan SPX &amp; J&amp;T)</legend>
    <?= $f('spx_sender_name', 'Nama pengirim') ?><?= $f('spx_sender_phone', 'Telefon pengirim', 'text', '0183775941') ?><?= $f('spx_sender_postcode', 'Poskod pengirim', 'text', '08000') ?>
    <div class="md:col-span-3"><label class="lbl">Alamat pengirim (alamat pickup / pemulangan)</label>
      <input name="spx_sender_address" class="inp" maxlength="255" value="<?= e($s['spx_sender_address'] ?? '') ?>" placeholder="No 12, Jalan Perusahaan 3"></div>
    <?= $f('spx_sender_city', 'Bandar pengirim') ?>
    <div><label class="lbl">Negeri pengirim</label><select name="spx_sender_state" class="inp"><option value="">— pilih —</option>
      <?php foreach (states() as $st => $_r): ?><option value="<?= e($st) ?>" <?= ($s['spx_sender_state'] ?? '') === $st ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?></select></div>
    <div><label class="lbl">Berat bungkusan (gram)</label><input name="spx_pack_g" type="number" min="0" step="1" class="inp" value="<?= e($s['spx_pack_g'] ?? '50') ?>">
      <p class="text-xs text-slate-500 mt-1">Ditambah kepada berat produk (diset pada setiap produk).</p></div>
  </fieldset>
  <fieldset class="grid md:grid-cols-3 gap-4" id="spx"><legend class="font-semibold mb-2">SPX Express</legend>
    <?php if (!spx_enabled()): ?><p class="md:col-span-3 text-sm bg-amber-50 text-amber-900 rounded p-2">Kelayakan API SPX belum diisi dalam <code>aurasaraf_app/config.php</code>.</p><?php endif; ?>
    <div><label class="lbl">Cara serah parcel</label><select name="spx_collect" class="inp">
      <option value="2" <?= ($s['spx_collect'] ?? '2') !== '1' ? 'selected' : '' ?>>Drop-off di kaunter SPX</option>
      <option value="1" <?= ($s['spx_collect'] ?? '') === '1' ? 'selected' : '' ?>>Pickup — kurier datang ambil (slot terawal)</option></select></div>
  </fieldset>
  <fieldset class="grid md:grid-cols-3 gap-4" id="jnt"><legend class="font-semibold mb-2">J&amp;T Express</legend>
    <?php if (!jnt_enabled()): ?><p class="md:col-span-3 text-sm bg-amber-50 text-amber-900 rounded p-2">Blok <code>'jnt'</code> belum ada dalam <code>aurasaraf_app/config.php</code>.</p><?php endif; ?>
    <div><label class="lbl">Cara serah parcel</label><select name="jnt_service" class="inp">
      <option value="6" <?= ($s['jnt_service'] ?? '6') !== '1' ? 'selected' : '' ?>>Drop-off di kaunter J&amp;T</option>
      <option value="1" <?= ($s['jnt_service'] ?? '') === '1' ? 'selected' : '' ?>>Pickup — kurier datang ambil</option></select></div>
    <div><label class="lbl">Cara bayar J&amp;T</label><select name="jnt_paytype" class="inp">
      <option value="PP_PM" <?= ($s['jnt_paytype'] ?? 'PP_PM') !== 'PP_CASH' ? 'selected' : '' ?>>Bulanan (akaun kontrak)</option>
      <option value="PP_CASH" <?= ($s['jnt_paytype'] ?? '') === 'PP_CASH' ? 'selected' : '' ?>>Tunai semasa serah</option></select>
      <p class="text-xs text-slate-500 mt-1">Ikut perjanjian dengan cawangan J&amp;T anda.</p></div>
  </fieldset>
  <fieldset class="space-y-2"><legend class="font-semibold mb-2">Cara bayaran di checkout</legend>
    <p class="text-sm text-slate-600">Ditawarkan sekarang: <b><?= e(implode(', ', array_map('pay_label', payment_methods()))) ?></b></p>
    <?php if (bp_enabled()): ?>
      <p class="text-sm text-slate-500">Billplz aktif — pembeli hanya nampak FPX. Tanda di bawah jika mahu tawarkan pilihan tambahan:</p>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="pay_transfer" value="1" <?= ($s['pay_transfer'] ?? '') === '1' ? 'checked' : '' ?>> Transfer bank / DuitNow QR <span class="text-slate-500">(perlu akaun bank atau QR di bawah)</span></label>
    <?php else: ?>
      <p class="text-sm text-slate-500">Billplz belum diset — transfer bank ditawarkan sementara (jika akaun bank atau QR diisi). Sebaik sahaja Billplz diset, hanya FPX akan ditawarkan.</p>
      <?php /* Kekalkan pilihan sedia ada — kotak yang tidak dipapar tidak dihantar dan akan dikira MATI. */ ?>
      <?php if (($s['pay_transfer'] ?? '') === '1'): ?><input type="hidden" name="pay_transfer" value="1"><?php endif; ?>
    <?php endif; ?>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="pay_whatsapp" value="1" <?= ($s['pay_whatsapp'] ?? '') === '1' ? 'checked' : '' ?>> Tawarkan juga "Urus melalui WhatsApp"</label>
  </fieldset>
  <fieldset class="grid md:grid-cols-3 gap-4"><legend class="font-semibold mb-2">Transfer bank / DuitNow QR</legend>
    <?= $f('bank_name', 'Bank') ?><?= $f('bank_acc', 'No. akaun') ?><?= $f('bank_holder', 'Nama pemegang') ?>
    <div class="md:col-span-3"><label class="lbl">Gambar DuitNow QR</label><input type="file" name="qr_image" accept="image/jpeg,image/png,image/webp" class="text-sm">
      <?php if (!empty($s['qr_image'])): ?><div class="flex items-center gap-3 mt-2"><img src="<?= e(url('uploads/' . rawurlencode($s['qr_image']))) ?>" class="w-24 rounded border" alt=""><label class="text-sm"><input type="checkbox" name="remove_qr"> Buang QR</label></div><?php endif; ?></div></fieldset>
  <fieldset class="grid md:grid-cols-3 gap-4"><legend class="font-semibold mb-2">Pematuhan (dipapar di footer bila diisi)</legend>
    <?= $f('ssm_no', 'No. SSM') ?><?= $f('mal_no', 'No. pendaftaran produk (MAL)') ?><?= $f('kkliu_no', 'No. kelulusan iklan (KKLIU)') ?></fieldset>
  <fieldset><legend class="font-semibold mb-2">Testimoni</legend>
    <label class="flex items-start gap-2 text-sm">
      <input type="checkbox" name="testimonials_show" value="1" class="mt-1" <?= ($s['testimonials_show'] ?? '') === '1' ? 'checked' : '' ?>>
      <span><b>Papar bahagian testimoni di kedai</b>
        <span class="block text-slate-500 mt-1">Isinya diurus dalam tab <b>Testimoni</b>. Gambar yang sekadar menunjukkan produk, bungkusan atau kepuasan servis tidak memerlukan kelulusan iklan.
        Testimoni yang mendakwa kesan kesihatan — contohnya "lega sakit lutut" — ialah iklan ubat di bawah Akta Ubat (Iklan dan Penjualan) 1956 dan memerlukan kelulusan KKLIU.
        Nombor KKLIU di atas dipapar bersama bahagian itu apabila diisi.</span></span>
    </label></fieldset>
  <button class="btn bg-brand text-white">Simpan tetapan</button>
</form>
<div class="space-y-4">
  <div class="bg-white rounded-xl border p-5 text-sm space-y-1">
    <h2 class="font-semibold mb-1">Billplz (FPX)</h2>
    <p>Status: <b class="<?= bp_enabled() ? 'text-emerald-700' : 'text-rose-700' ?>"><?= bp_enabled() ? 'AKTIF' : 'BELUM DISET' ?></b><?php if (bp_enabled()): ?> · mod <b><?= !empty(bp_cfg()['sandbox']) ? 'SANDBOX (ujian)' : 'LIVE' ?></b><?php endif; ?></p>
    <p class="text-slate-500">API secret key dan collection ID disimpan dalam <code>aurasaraf_app/config.php</code>, bukan di database.</p>
    <p class="text-slate-500 break-all">Callback URL: <code><?= e(url('payment-callback.php')) ?></code></p>
  </div>
  <div class="bg-white rounded-xl border p-5 text-sm space-y-2">
    <h2 class="font-semibold mb-1">SPX Express</h2>
    <p>API: <b class="<?= spx_enabled() ? 'text-emerald-700' : 'text-rose-700' ?>"><?= spx_enabled() ? 'DISET' : 'BELUM DISET' ?></b><?php if (spx_enabled()): ?> · mod <b><?= !empty(spx_cfg()['sandbox']) ? 'UJIAN (test-stable)' : 'LIVE' ?></b><?php endif; ?></p>
    <p>Alamat pengirim: <b class="<?= ship_sender_ready() ? 'text-emerald-700' : 'text-rose-700' ?>"><?= ship_sender_ready() ? 'LENGKAP' : 'BELUM LENGKAP' ?></b></p>
    <p class="text-slate-500">app_id, app_secret, user_id dan user_secret disimpan dalam <code>aurasaraf_app/config.php</code>.</p>
    <p class="text-slate-500 break-all">Webhook URL (beri kepada PIC SPX): <code><?= e(url('spx-webhook.php')) ?></code></p>
    <?php if (spx_enabled()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="spx_verify"><button class="btn bg-slate-100">Uji sambungan SPX</button></form><?php endif; ?>
  </div>
  <div class="bg-white rounded-xl border p-5 text-sm space-y-2">
    <h2 class="font-semibold mb-1">J&amp;T Express</h2>
    <p>API: <b class="<?= jnt_enabled() ? 'text-emerald-700' : 'text-rose-700' ?>"><?= jnt_enabled() ? 'DISET' : 'BELUM DISET' ?></b><?php if (jnt_enabled()): ?> · mod <b><?= jnt_sandbox() ? 'SANDBOX (ujian)' : 'LIVE' ?></b><?php endif; ?></p>
    <?php if (jnt_enabled() && jnt_sandbox()): ?><p class="text-xs bg-amber-50 text-amber-900 rounded p-2">Sandbox: tempahan pergi ke server ujian J&amp;T — tiada kurier datang dan label tidak sah untuk parcel sebenar.</p><?php endif; ?>
    <p>Alamat pengirim: <b class="<?= ship_sender_ready() ? 'text-emerald-700' : 'text-rose-700' ?>"><?= ship_sender_ready() ? 'LENGKAP' : 'BELUM LENGKAP' ?></b></p>
    <p class="text-slate-500">api_account, private_key, customer_code dan password disimpan dalam <code>aurasaraf_app/config.php</code>.</p>
    <p class="text-slate-500 break-all">URL tolak status (isi di konsol J&amp;T semasa mohon live): <code><?= e(url('jnt-webhook.php')) ?></code></p>
    <?php if (jnt_enabled()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="jnt_verify"><button class="btn bg-slate-100">Uji sambungan J&amp;T</button></form><?php endif; ?>
  </div>
  <div class="bg-white rounded-xl border p-5 text-sm space-y-1">
    <h2 class="font-semibold mb-1">Kos pos di checkout</h2>
    <p>Sekarang: <b><?= ($rc = rate_courier()) !== '' ? 'Ikut kadar ' . e(courier_label($rc)) : 'Kadar tetap' ?></b></p>
    <?php if (($s['rate_courier'] ?? '') !== '' && $rc === ''): ?><p class="text-xs text-rose-700">Kurier yang dipilih belum diset sepenuhnya — kadar tetap digunakan.</p><?php endif; ?>
  </div>
  <form method="post" class="bg-white rounded-xl border p-5 space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <h2 class="font-semibold">Profil saya</h2>
    <p class="text-xs text-slate-500">Log masuk sebagai <b><?= e($admin['username']) ?></b> · <?= e(admin_roles()[$admin['role']] ?? $admin['role']) ?></p>
    <div><label class="lbl">Nama penuh</label><input name="name" class="inp" maxlength="100" value="<?= e($admin['name']) ?>"></div>
    <div><label class="lbl">E-mel</label><input name="email" type="email" class="inp" maxlength="190" value="<?= e($admin['email']) ?>"></div>
    <div><label class="lbl">No. telefon</label><input name="phone" class="inp" maxlength="30" value="<?= e($admin['phone']) ?>" placeholder="0183775941"></div>
    <button class="btn bg-brand text-white">Simpan profil</button></form>

  <form method="post" class="bg-white rounded-xl border p-5 space-y-3" id="tukar-kata-laluan"><?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h2 class="font-semibold">Tukar kata laluan</h2>
    <?php if (!empty($admin['must_change_password'])): ?>
      <p class="bg-amber-100 text-amber-900 text-sm rounded-lg px-3 py-2">Kata laluan anda telah di-reset. Tetapkan yang baharu untuk membuka semula panel.</p>
    <?php endif; ?>
    <?= pw_input('current_password', 'Kata laluan semasa', 'current-password') ?>
    <?= pw_input('new_password', 'Kata laluan baharu (min 10)') ?>
    <?= pw_input('new_password2', 'Ulang kata laluan baharu') ?>
    <button class="btn bg-slate-800 text-white">Tukar</button></form>
</div></div>
<?php admin_footer();
