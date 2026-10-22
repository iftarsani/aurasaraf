<?php
/** Susun atur admin (Tailwind CDN). */
function admin_header(string $title, string $active = ''): void
{
    $nav = ['index.php' => 'Papan pemuka', 'orders.php' => 'Pesanan', 'shipping.php' => 'Pos harian', 'products.php' => 'Produk', 'stock.php' => 'Stok', 'testimonials.php' => 'Testimoni', 'settings.php' => 'Tetapan'];
    if (admin_is_owner()) $nav['admins.php'] = 'Admin';
    // Satu pertanyaan untuk kedua-dua lencana nav.
    $kira = db()->query("SELECT status, COUNT(*) c FROM orders WHERE status IN ('new','paid') GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $badge = ['orders.php' => (int)($kira['new'] ?? 0), 'shipping.php' => (int)($kira['paid'] ?? 0)]; ?>
<!doctype html><html lang="ms"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?= e($title) ?> · Admin Aura Saraf</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{brand:{DEFAULT:'#173b2a',leaf:'#2f7d4a',gold:'#b4841a',cream:'#f7f1e3'}}}}}</script>
<style>.inp{width:100%;border:1px solid #d6d3c4;border-radius:.6rem;padding:.55rem .75rem;background:#fff}.inp:focus{outline:2px solid #2f7d4a55;border-color:#2f7d4a}
.btn{display:inline-flex;align-items:center;gap:.4rem;border-radius:.6rem;padding:.55rem 1rem;font-weight:600;font-size:.9rem}.lbl{display:block;font-size:.8rem;font-weight:600;color:#4b5563;margin-bottom:.25rem}</style>
</head><body class="bg-brand-cream text-slate-800 min-h-screen">
<header class="bg-brand text-white">
  <div class="max-w-6xl mx-auto px-4 flex items-center justify-between h-14">
    <a href="<?= e(url('admin/index.php')) ?>" class="font-bold">🌿 Admin Aura Saraf</a>
    <div class="flex items-center gap-3 text-sm">
      <a href="<?= e(url()) ?>" target="_blank" class="opacity-80 hover:opacity-100">Lihat kedai ↗</a>
      <span class="hidden sm:inline opacity-80"><?= e(admin_name()) ?><?php if (admin_is_owner()): ?> <span class="text-xs bg-brand-gold/80 rounded px-1.5 py-0.5">Pemilik</span><?php endif; ?></span>
      <form method="post" action="<?= e(url('admin/logout.php')) ?>" data-confirm="Anda pasti mahu log keluar?" data-confirm-ok="Log keluar"><?= csrf_field() ?><button class="bg-white/10 hover:bg-white/20 rounded px-3 py-1">Log keluar</button></form>
    </div>
  </div>
  <nav class="max-w-6xl mx-auto px-4 flex gap-1 overflow-x-auto text-sm">
    <?php foreach ($nav as $file => $label): ?>
      <a href="<?= e(url('admin/' . $file)) ?>" class="px-3 py-2 rounded-t-lg whitespace-nowrap <?= $active === $file ? 'bg-brand-cream text-brand font-semibold' : 'text-white/80 hover:text-white' ?>">
        <?= e($label) ?><?php if (!empty($badge[$file])): ?> <span class="ml-1 bg-brand-gold text-white text-xs rounded-full px-1.5"><?= (int)$badge[$file] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<main class="max-w-6xl mx-auto px-4 py-6">
<?php foreach (flashes() as [$t, $m]): ?>
  <div class="mb-4 rounded-lg px-4 py-3 text-sm <?= $t === 'error' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' ?>"><?= e($m) ?></div>
<?php endforeach; ?>
<h1 class="text-2xl font-bold mb-4"><?= e($title) ?></h1>
<?php }

function admin_footer(): void { echo '</main>'; admin_scripts(); echo '</body></html>'; }

/**
 * Medan kata laluan dengan butang tunjuk/sembunyi.
 * Butang itu dikendalikan oleh JS dalam admin_scripts().
 */
function pw_input(string $name, string $placeholder, string $autocomplete = 'new-password', bool $required = true): string
{
    return '<div class="relative">'
        . '<input type="password" name="' . e($name) . '" class="inp pr-20" placeholder="' . e($placeholder) . '"'
        . ($required ? ' required' : '') . ' autocomplete="' . e($autocomplete) . '">'
        . '<button type="button" class="pw-toggle absolute inset-y-0 right-0 px-3 text-xs font-semibold text-slate-500 hover:text-slate-900">Tunjuk</button>'
        . '</div>';
}

/**
 * Modal pengesahan + toggle kata laluan.
 *
 * Sebarang <form data-confirm="Soalan?"> akan dipintas dan meminta pengesahan
 * dahulu. Nota: form.submit() TIDAK menghantar name/value butang submit, jadi
 * borang begitu mesti membawa tindakannya dalam <input type="hidden">.
 */
function admin_scripts(): void
{ ?>
<div id="confirmModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-5" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
    <h2 id="confirmTitle" class="font-bold text-lg mb-1">Anda pasti?</h2>
    <p id="confirmMsg" class="text-sm text-slate-600 mb-4"></p>
    <div class="flex justify-end gap-2">
      <button type="button" id="confirmNo" class="btn bg-slate-200 text-slate-800">Batal</button>
      <button type="button" id="confirmYes" class="btn bg-rose-600 text-white">Teruskan</button>
    </div>
  </div>
</div>
<script>
(function () {
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('.pw-toggle');
    if (!t) return;
    var inp = t.parentNode.querySelector('input');
    if (!inp) return;
    var tunjuk = inp.type === 'password';
    inp.type = tunjuk ? 'text' : 'password';
    t.textContent = tunjuk ? 'Sembunyi' : 'Tunjuk';
  });

  var modal = document.getElementById('confirmModal'),
      msg   = document.getElementById('confirmMsg'),
      yes   = document.getElementById('confirmYes'),
      no    = document.getElementById('confirmNo'),
      menunggu = null;

  function tutup() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    menunggu = null;
  }

  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!f.dataset || !f.dataset.confirm || f.dataset.confirmed === '1') return;
    ev.preventDefault();
    menunggu = f;
    msg.textContent = f.dataset.confirm;
    yes.textContent = f.dataset.confirmOk || 'Teruskan';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    yes.focus();
  });

  yes.addEventListener('click', function () {
    if (!menunggu) return;
    var f = menunggu;
    f.dataset.confirmed = '1';
    tutup();
    f.submit();
  });
  no.addEventListener('click', tutup);
  modal.addEventListener('click', function (ev) { if (ev.target === modal) tutup(); });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && menunggu) tutup(); });
})();
</script>
<?php }

function status_badge(string $s): string
{
    $st = order_statuses()[$s] ?? [$s, 'bg-slate-200'];
    return '<span class="inline-block text-xs font-semibold rounded-full px-2.5 py-0.5 ' . $st[1] . '">' . e($st[0]) . '</span>';
}

/** Bilangan baris sehalaman dari ?per=, dihadkan kepada pilihan yang dibenarkan. */
function admin_per_page(int $default = 25): int
{
    $per = (int)($_GET['per'] ?? $default);
    return in_array($per, [25, 50, 100], true) ? $per : $default;
}

/**
 * Navigasi halaman: "Menunjukkan X–Y daripada N", Sebelum/Seterusnya dan nombor halaman
 * bertingkap (1 … 4 5 6 … 20). $keep = parameter GET yang dikekalkan (tapisan, carian).
 */
function admin_pager(int $total, int $page, int $per, array $keep): string
{
    if ($total === 0) return '';
    $pages = (int)ceil($total / $per);
    $from = ($page - 1) * $per + 1; $to = min($total, $page * $per);
    $link = function (int $p, string $label, bool $on = false, bool $off = false) use ($keep): string {
        if ($off) return '<span class="px-3 py-1 rounded border bg-white text-slate-300">' . $label . '</span>';
        $cls = $on ? 'bg-brand text-white border-brand' : 'bg-white hover:bg-slate-50';
        return '<a class="px-3 py-1 rounded border ' . $cls . '" href="?' . e(http_build_query($keep + ['page' => $p])) . '">' . $label . '</a>';
    };
    $nums = array_unique(array_filter([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages],
        fn($p) => $p >= 1 && $p <= $pages));
    sort($nums);
    $out = ''; $prev = 0;
    foreach ($nums as $p) {
        if ($p - $prev > 1) $out .= '<span class="px-1 text-slate-400">…</span>';
        $out .= $link($p, (string)$p, $p === $page);
        $prev = $p;
    }
    return '<div class="flex flex-wrap items-center justify-between gap-2 mt-3 text-sm">'
         . '<span class="text-slate-500">Menunjukkan ' . $from . '–' . $to . ' daripada ' . $total . '</span>'
         . ($pages > 1 ? '<div class="flex flex-wrap items-center gap-1">' . $link($page - 1, '‹ Sebelum', false, $page <= 1)
           . $out . $link($page + 1, 'Seterusnya ›', false, $page >= $pages) . '</div>' : '')
         . '</div>';
}

/** Syarat SQL pesanan yang ada resit tetapi belum disahkan — dikongsi oleh tapisan dan kiraan. */
const SQL_PERLU_SEMAK = "status IN ('new','pending_payment') AND receipt_file IS NOT NULL AND receipt_file <> ''";

/**
 * Label keadaan bayaran untuk pesanan yang belum Dibayar, supaya admin tahu tindakan
 * seterusnya. Hanya petunjuk: status bayaran tetap ditukar oleh "Tanda DIBAYAR" atau Billplz.
 */
function pay_hint(array $o): string
{
    if (!in_array($o['status'], ['new', 'pending_payment'], true)) return '';
    if (!empty($o['receipt_file'])) {
        [$t, $c] = ['Resit diterima · perlu semak', 'bg-rose-600 text-white'];
    } elseif ($o['payment_method'] === 'transfer') {
        [$t, $c] = ['Belum upload resit', 'bg-slate-100 text-slate-600'];
    } elseif ($o['payment_method'] === 'whatsapp') {
        [$t, $c] = ['Urus bayaran di WhatsApp', 'bg-slate-100 text-slate-600'];
    } else {
        [$t, $c] = ['Menunggu FPX', 'bg-slate-100 text-slate-600'];
    }
    return '<span class="inline-block text-xs font-semibold rounded-full px-2.5 py-0.5 mt-1 ' . $c . '">' . e($t) . '</span>';
}
