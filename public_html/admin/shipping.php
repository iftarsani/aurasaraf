<?php
/**
 * Pos harian — senarai pesanan yang sudah dibayar tetapi belum dihantar,
 * dengan medan kurier + no. tracking sebaris-sebaris supaya staf boleh
 * key in sekali gus selepas balik dari kaunter kurier.
 *
 * Halaman pesanan (order.php) tetap kekal untuk kes satu-satu dan untuk
 * membetulkan tracking yang sudah disimpan. Kedua-duanya memanggil
 * order_mark_shipped() supaya peraturan urutan status sama.
 */
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

if (is_post() && in_array($_POST['action'] ?? '', ['spx_clear', 'jnt_clear'], true)) {
    csrf_check();
    unset($_SESSION[$_POST['action'] === 'spx_clear' ? 'spx_booked' : 'jnt_booked']);
    redirect('admin/shipping.php');
}

if (is_post() && in_array($_POST['action'] ?? '', ['spx', 'jnt'], true)) {
    csrf_check();
    // Tempah kurier (SPX atau J&T) untuk baris yang ditanda. Yang berjaya terus
    // jadi DIHANTAR dengan no. tracking kurier; senarainya diingat untuk butang cetak label.
    $cour = $_POST['action'];
    $name = $cour === 'spx' ? 'SPX' : 'J&T';
    $pick = array_keys(array_filter((array)($_POST['pick'] ?? [])));
    if (!$pick) { flash('error', 'Tanda sekurang-kurangnya satu pesanan untuk ditempah di ' . $name . '.'); redirect('admin/shipping.php'); }
    try {
        $r = $cour === 'spx' ? spx_book($pick) : jnt_book($pick);
        if ($r['ok']) {
            $_SESSION[$cour . '_booked'] = array_keys($r['ok']);
            flash('success', count($r['ok']) . ' pesanan ditempah di ' . $name . ' dan ditanda DIHANTAR.');
        }
        foreach ($r['fail'] as $fid => $msg) {
            $fo = order_find((int)$fid);
            flash('error', ($fo['order_no'] ?? '#' . $fid) . ': ' . $msg);
        }
    } catch (Throwable $ex) {
        error_log($cour . ' bulk: ' . $ex->getMessage());
        flash('error', $ex->getMessage());
    }
    redirect('admin/shipping.php');
}

if (is_post()) {
    csrf_check();
    $rows = $_POST['rows'] ?? [];
    $ok = 0; $gagal = 0; $dipakai = [];

    foreach ((array)$rows as $id => $r) {
        $id    = (int)$id;
        $track = trim((string)($r['tracking_no'] ?? ''));
        // Baris tanpa no. tracking = belum dipos lagi. Dilangkau senyap supaya
        // staf boleh isi separuh senarai dan simpan tanpa kehilangan yang lain.
        if ($id <= 0 || $track === '') continue;

        $cour = courier_resolve((string)($r['courier'] ?? ''), (string)($r['courier_other'] ?? ''));
        if (order_mark_shipped($id, $cour, $track)) {
            $ok++;
            if (isset(couriers()[$cour])) $dipakai[] = $cour;
        } else {
            $gagal++;
        }
    }

    // Ingat kurier yang paling banyak dipakai kali ini supaya esok senarai
    // sudah terpilih dan staf cuma perlu taip nombor.
    if ($dipakai) {
        $kira = array_count_values($dipakai);
        arsort($kira);
        setting_set('last_courier', (string)array_key_first($kira));
    }

    if ($ok)    flash('success', $ok . ' pesanan ditanda DIHANTAR.');
    if ($gagal) flash('error', $gagal . ' pesanan gagal — status berubah sejak halaman ini dibuka. Muat semula dan cuba lagi.');
    if (!$ok && !$gagal) flash('error', 'Tiada no. tracking diisi, jadi tiada apa disimpan.');
    redirect('admin/shipping.php');
}

$per  = 100;
$rows = db()->query("SELECT o.*, (SELECT COALESCE(SUM(qty), 0) FROM order_items WHERE order_id = o.id) units
                       FROM orders o WHERE o.status = 'paid'
                      ORDER BY o.paid_at, o.id LIMIT $per")->fetchAll();
$total = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'paid'")->fetchColumn();
$lastC = (string)setting('last_courier');
if (!isset(couriers()[$lastC])) $lastC = '';
$allIds = implode(',', array_column($rows, 'id'));
$spxOn  = spx_ready();
$jntOn  = jnt_ready();
$booked = array_slice(array_map('intval', (array)($_SESSION['spx_booked'] ?? [])), 0, 30);
$jntBooked = array_slice(array_map('intval', (array)($_SESSION['jnt_booked'] ?? [])), 0, 100);

admin_header('Pos harian', 'shipping.php');
?>
<?php if ($booked): ?>
  <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 mb-4 flex flex-wrap items-center gap-3">
    <p class="text-sm text-orange-900"><b><?= count($booked) ?></b> pesanan baru ditempah di SPX. Cetak AWB dan lekat pada setiap parcel.</p>
    <a class="btn bg-orange-600 text-white" target="_blank" href="<?= e(url('admin/spx-awb.php?ids=' . implode(',', $booked))) ?>">🖨️ Cetak AWB SPX</a>
    <form method="post" action="<?= e(url('admin/shipping.php')) ?>" class="ml-auto"><?= csrf_field() ?><input type="hidden" name="action" value="spx_clear">
      <button class="text-xs text-slate-500 underline">Tutup</button></form>
  </div>
<?php endif; ?>
<?php if ($jntBooked): ?>
  <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4 flex flex-wrap items-center gap-3">
    <p class="text-sm text-red-900"><b><?= count($jntBooked) ?></b> pesanan baru ditempah di J&amp;T. Cetak label dan lekat pada setiap parcel.</p>
    <a class="btn bg-red-700 text-white" target="_blank" href="<?= e(url('admin/jnt-awb.php?ids=' . implode(',', $jntBooked))) ?>">🖨️ Cetak label J&amp;T</a>
    <form method="post" action="<?= e(url('admin/shipping.php')) ?>" class="ml-auto"><?= csrf_field() ?><input type="hidden" name="action" value="jnt_clear">
      <button class="text-xs text-slate-500 underline">Tutup</button></form>
  </div>
<?php endif; ?>
<?php if (!$rows): ?>
  <div class="bg-white rounded-xl border p-8 text-center">
    <p class="text-4xl mb-2">📦</p>
    <p class="font-semibold">Tiada pesanan menunggu penghantaran.</p>
    <p class="text-sm text-slate-500 mt-1">Semua pesanan yang sudah dibayar telah dipos.</p>
    <p class="mt-4 flex justify-center gap-2">
      <a class="btn bg-slate-100" href="<?= e(url('admin/orders.php?status=shipped')) ?>">Lihat yang sudah dihantar</a>
      <a class="btn bg-slate-100" href="<?= e(url('admin/orders.php?status=new')) ?>">Pesanan baru</a>
    </p>
  </div>
<?php else: ?>

<div class="bg-white rounded-xl border p-4 mb-4">
  <div class="flex flex-wrap items-end gap-3">
    <div>
      <label class="lbl" for="bulkCour">Isi kurier untuk semua baris</label>
      <select id="bulkCour" class="inp !w-auto">
        <option value="">— pilih untuk isi semua —</option>
        <?php foreach (couriers() as $ck => $cv): ?><option value="<?= e($ck) ?>"><?= e($cv[0]) ?></option><?php endforeach; ?>
        <option value="lain">Lain-lain…</option>
      </select>
    </div>
    <a class="btn bg-slate-800 text-white" target="_blank" href="<?= e(url('admin/label.php?ids=' . $allIds . '&size=a4')) ?>">
      🏷️ Cetak <?= count($rows) ?> label (A4)</a>
    <a class="btn bg-slate-100" target="_blank" href="<?= e(url('admin/label.php?ids=' . $allIds)) ?>">Label sticker 100×150mm</a>
    <p class="text-xs text-slate-500 ml-auto max-w-sm">Biarkan no. tracking kosong untuk pesanan yang belum dipos — baris itu akan dilangkau.</p>
  </div>
</div>

<form method="post">
  <?= csrf_field() ?>
  <div class="bg-white rounded-xl border overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
        <tr><?php if ($spxOn || $jntOn): ?><th class="px-3 py-2 w-10 whitespace-nowrap"><input type="checkbox" id="pickAll" title="Tanda semua untuk ditempah"> Tempah</th><?php endif; ?><th class="px-3 py-2">Pesanan</th><th class="px-3 py-2">Pelanggan</th><th class="px-3 py-2">Unit</th>
            <th class="px-3 py-2 w-56">Kurier</th><th class="px-3 py-2 w-56">No. tracking</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $o): ?>
        <tr class="border-t align-top">
          <?php if ($spxOn || $jntOn): ?><td class="px-3 py-2"><input type="checkbox" class="book-pick" name="pick[<?= (int)$o['id'] ?>]" value="1" aria-label="Tempah kurier untuk <?= e($o['order_no']) ?>"></td><?php endif; ?>
          <td class="px-3 py-2 font-mono whitespace-nowrap">
            <a class="text-brand-leaf font-semibold" href="<?= e(url('admin/order.php?id=' . $o['id'])) ?>"><?= e($o['order_no']) ?></a>
            <div class="text-xs text-slate-400"><?= e(date('d/m', strtotime($o['paid_at'] ?: $o['created_at']))) ?></div>
          </td>
          <td class="px-3 py-2"><?= e($o['customer_name']) ?>
            <div class="text-xs text-slate-500"><?= e($o['city']) ?>, <?= e($o['state']) ?></div></td>
          <td class="px-3 py-2 font-semibold"><?= (int)$o['units'] ?></td>
          <td class="px-3 py-2">
            <select name="rows[<?= (int)$o['id'] ?>][courier]" class="inp cour-sel">
              <option value="">— pilih —</option>
              <?php foreach (couriers() as $ck => $cv): ?>
                <option value="<?= e($ck) ?>" <?= $lastC === $ck ? 'selected' : '' ?>><?= e($cv[0]) ?></option>
              <?php endforeach; ?>
              <option value="lain">Lain-lain…</option>
            </select>
            <input name="rows[<?= (int)$o['id'] ?>][courier_other]" class="inp cour-other mt-1" placeholder="Nama kurier" hidden>
          </td>
          <td class="px-3 py-2">
            <input name="rows[<?= (int)$o['id'] ?>][tracking_no]" class="inp font-mono" placeholder="No. tracking" autocomplete="off">
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="flex flex-wrap items-center gap-3 mt-4">
    <button class="btn bg-sky-600 text-white">Simpan &amp; tanda DIHANTAR</button>
    <?php if ($spxOn): ?>
      <button class="btn bg-orange-600 text-white" name="action" value="spx" title="No. tracking diisi automatik oleh SPX">🚚 Tempah SPX untuk yang ditanda</button>
    <?php endif; ?>
    <?php if ($jntOn): ?>
      <button class="btn bg-red-700 text-white" name="action" value="jnt" title="No. tracking diisi automatik oleh J&amp;T">🚚 Tempah J&amp;T untuk yang ditanda<?= jnt_sandbox() ? ' (sandbox)' : '' ?></button>
    <?php endif; ?>
    <a class="btn bg-slate-100" href="<?= e(url('admin/orders.php?status=shipped')) ?>">Sudah dihantar →</a>
    <?php if ($total > count($rows)): ?>
      <span class="text-xs text-slate-500"><?= $total - count($rows) ?> lagi pesanan tidak muat di halaman ini — simpan senarai ini dahulu.</span>
    <?php endif; ?>
  </div>
</form>

<script>
(function () {
  // Satu pendengar untuk semua baris: "Lain-lain…" membuka medan nama bebas.
  document.addEventListener('change', function (ev) {
    var s = ev.target.closest ? ev.target.closest('.cour-sel') : null;
    if (!s) return;
    var t = s.parentNode.querySelector('.cour-other');
    if (t) t.hidden = s.value !== 'lain';
  });

  var pickAll = document.getElementById('pickAll');
  if (pickAll) pickAll.addEventListener('change', function () {
    var on = this.checked;
    document.querySelectorAll('.book-pick').forEach(function (c) { c.checked = on; });
  });

  var bulk = document.getElementById('bulkCour');
  if (bulk) bulk.addEventListener('change', function () {
    if (this.value === '') return;
    var v = this.value;
    document.querySelectorAll('.cour-sel').forEach(function (s) {
      s.value = v;
      s.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

  // Enter dalam medan tracking = turun ke baris seterusnya, bukan hantar borang.
  // Staf menaip senarai nombor berturut-turut; hantar awal akan kehilangan baki.
  var boxes = Array.prototype.slice.call(document.querySelectorAll('input[name$="[tracking_no]"]'));
  boxes.forEach(function (b, i) {
    b.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Enter') return;
      ev.preventDefault();
      if (boxes[i + 1]) boxes[i + 1].focus();
    });
  });
})();
</script>
<?php endif; ?>
<?php admin_footer();
