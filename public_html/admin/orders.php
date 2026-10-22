<?php
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();
$status = (string)($_GET['status'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1)); $per = admin_per_page();
// Julat tarikh (YYYY-MM-DD dari <input type=date>); nilai lain diabaikan.
$dari   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['dari'] ?? '')) ? $_GET['dari'] : '';
$hingga = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['hingga'] ?? '')) ? $_GET['hingga'] : '';
$where = []; $args = [];
if ($status === 'semak') $where[] = SQL_PERLU_SEMAK;
elseif (isset(order_statuses()[$status])) { $where[] = 'status = ?'; $args[] = $status; }
$perluSemak = (int)db()->query('SELECT COUNT(*) FROM orders WHERE ' . SQL_PERLU_SEMAK)->fetchColumn();
if ($dari !== '')   { $where[] = 'created_at >= ?'; $args[] = $dari . ' 00:00:00'; }
if ($hingga !== '') { $where[] = 'created_at < ? + INTERVAL 1 DAY'; $args[] = $hingga; }
if ($q !== '') { $where[] = '(order_no LIKE ? OR customer_name LIKE ? OR phone LIKE ? OR tracking_no LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%", "%$q%"); }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$st = db()->prepare("SELECT COUNT(*) FROM orders $w"); $st->execute($args); $total = (int)$st->fetchColumn();
$page = min($page, max(1, (int)ceil($total / $per)));  // halaman melebihi had -> halaman terakhir
$keep = array_filter(['status' => $status, 'q' => $q, 'dari' => $dari, 'hingga' => $hingga, 'per' => $per === 25 ? '' : $per], fn($v) => $v !== '');
$st = db()->prepare("SELECT * FROM orders $w ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per)); $st->execute($args);
$rows = $st->fetchAll();
admin_header('Pesanan', 'orders.php');
?>
<form class="flex flex-wrap gap-2 mb-4">
  <select name="status" class="inp !w-auto" onchange="this.form.submit()"><option value="">Semua status</option>
    <option value="semak" <?= $status === 'semak' ? 'selected' : '' ?>>⚠ Perlu semak bayaran (<?= $perluSemak ?>)</option>
    <?php foreach (order_statuses() as $k => $v): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v[0]) ?></option><?php endforeach; ?></select>
  <input name="q" value="<?= e($q) ?>" placeholder="Cari no. pesanan, nama, telefon, tracking" class="inp !w-72">
  <label class="flex items-center gap-1 text-sm text-slate-600">Dari <input type="date" name="dari" value="<?= e($dari) ?>" class="inp !w-auto"></label>
  <label class="flex items-center gap-1 text-sm text-slate-600">Hingga <input type="date" name="hingga" value="<?= e($hingga) ?>" class="inp !w-auto"></label>
  <select name="per" class="inp !w-auto" onchange="this.form.submit()" aria-label="Baris sehalaman">
    <?php foreach ([25, 50, 100] as $n): ?><option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>><?= $n ?> sehalaman</option><?php endforeach; ?></select>
  <button class="btn bg-brand text-white">Cari</button>
  <?php if ($keep): ?><a class="btn bg-slate-100" href="orders.php">Set semula</a><?php endif; ?>
  <?php if ($perluSemak && $status !== 'semak'): ?><a class="btn bg-rose-600 text-white" href="?status=semak">⚠ <?= $perluSemak ?> resit perlu semak</a><?php endif; ?>
</form>
<!-- Borang GET berasingan daripada borang tapisan di atas: borang tidak boleh
     bersarang, jadi checkbox dihantar terus ke label.php sebagai id[]. -->
<form method="get" action="<?= e(url('admin/label.php')) ?>" target="_blank">
<div class="flex flex-wrap items-center gap-2 mb-3">
  <button id="printBtn" class="btn bg-slate-800 text-white disabled:opacity-40" disabled>🏷️ Cetak label yang ditanda</button>
  <select name="size" class="inp !w-auto">
    <option value="a4">A4 — 4 label sehelai</option>
    <option value="a6">Sticker 100×150mm</option>
  </select>
  <span class="text-xs text-slate-500" id="pickHint">Tanda kotak di sebelah kiri, kemudian tekan cetak.</span>
</div>
<div class="bg-white rounded-xl border overflow-x-auto">
<table class="w-full text-sm">
  <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2 w-8"><input type="checkbox" id="pickAll" aria-label="Tanda semua"></th><th class="px-4 py-2">No.</th><th class="px-4 py-2">Pelanggan</th><th class="px-4 py-2">Jumlah</th><th class="px-4 py-2">Bayaran</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Tarikh</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $o): ?><tr class="border-t hover:bg-slate-50">
    <td class="px-3 py-2"><input type="checkbox" class="pick" name="id[]" value="<?= (int)$o['id'] ?>" aria-label="Pilih <?= e($o['order_no']) ?>"></td>
    <td class="px-4 py-2 font-mono"><a class="text-brand-leaf font-semibold" href="<?= e(url('admin/order.php?id=' . $o['id'])) ?>"><?= e($o['order_no']) ?></a></td>
    <td class="px-4 py-2"><?= e($o['customer_name']) ?><div class="text-xs text-slate-500"><?= e($o['phone']) ?> · <?= e($o['state']) ?></div></td>
    <td class="px-4 py-2 font-semibold"><?= e(money($o['total'])) ?></td>
    <td class="px-4 py-2"><?= e(pay_label($o['payment_method'])) ?><?= $o['receipt_file'] ? ' 📎' : '' ?></td>
    <td class="px-4 py-2"><?= status_badge($o['status']) ?><?php if ($h = pay_hint($o)): ?><br><?= $h ?><?php endif; ?></td>
    <td class="px-4 py-2 text-slate-500 whitespace-nowrap"><?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Tiada pesanan.</td></tr><?php endif; ?>
  </tbody></table></div>
</form>
<script>
(function () {
  var all   = document.getElementById('pickAll'),
      btn   = document.getElementById('printBtn'),
      hint  = document.getElementById('pickHint'),
      boxes = Array.prototype.slice.call(document.querySelectorAll('.pick'));
  if (!all || !btn) return;

  // Butang dimatikan sehingga ada yang ditanda. Tanpa ini, tekan cetak tanpa
  // pilihan membuka tab baharu dengan 404 — nampak macam sistem rosak.
  function segar() {
    var n = boxes.filter(function (b) { return b.checked; }).length;
    btn.disabled = n === 0;
    btn.textContent = n ? '🏷️ Cetak ' + n + ' label' : '🏷️ Cetak label yang ditanda';
    all.checked = n > 0 && n === boxes.length;
    all.indeterminate = n > 0 && n < boxes.length;
    if (hint) hint.textContent = n
      ? n + ' pesanan dipilih.'
      : 'Tanda kotak di sebelah kiri, kemudian tekan cetak.';
  }

  all.addEventListener('change', function () {
    boxes.forEach(function (b) { b.checked = all.checked; });
    segar();
  });
  boxes.forEach(function (b) { b.addEventListener('change', segar); });
  segar();
})();
</script>
<?= admin_pager($total, $page, $per, $keep) ?>
<?php admin_footer();
