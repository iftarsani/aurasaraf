<?php
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_layout.php';   // untuk pw_input() dan admin_scripts()
if (admin_current()) redirect('admin/index.php');
$err = '';
if (is_post()) {
    csrf_check();
    $u = (string)($_POST['username'] ?? '');
    if (login_locked(trim($u)))                                  $err = 'Terlalu banyak cubaan. Cuba lagi selepas ' . LOGIN_WINDOW_MIN . ' minit.';
    elseif (admin_login($u, (string)($_POST['password'] ?? ''))) redirect('admin/index.php');
    else                                                         $err = 'Username atau kata laluan salah.';
}
?><!doctype html><html lang="ms"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Log masuk admin</title><script src="https://cdn.tailwindcss.com"></script>
<style>.inp{width:100%;border:1px solid #d6d3c4;border-radius:.6rem;padding:.55rem .75rem;background:#fff}.inp:focus{outline:2px solid #2f7d4a55;border-color:#2f7d4a}
.btn{display:inline-flex;align-items:center;gap:.4rem;border-radius:.6rem;padding:.55rem 1rem;font-weight:600;font-size:.9rem}</style></head>
<body class="min-h-screen grid place-items-center bg-[#173b2a] p-4">
<form method="post" class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-sm space-y-4" autocomplete="off">
  <?= csrf_field() ?>
  <div class="text-center"><div class="text-4xl">🌿</div><h1 class="text-xl font-bold mt-2">Admin Aura Saraf</h1></div>
  <?php if ($err): ?><p class="bg-rose-100 text-rose-800 text-sm rounded-lg px-3 py-2"><?= e($err) ?></p><?php endif; ?>
  <label class="block text-sm font-semibold">Username<input name="username" required autofocus autocomplete="username" class="mt-1 w-full border rounded-lg px-3 py-2"></label>
  <div><span class="block text-sm font-semibold mb-1">Kata laluan</span><?= pw_input('password', '', 'current-password') ?></div>
  <button class="w-full bg-[#173b2a] text-white font-semibold rounded-lg py-2.5 hover:bg-[#2f7d4a]">Log masuk</button>
</form>
<?php admin_scripts(); ?>
</body></html>
