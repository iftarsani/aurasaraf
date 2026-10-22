<?php
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_layout.php';
$admin = require_admin();
$id = (int)($_GET['id'] ?? 0);
$o = order_find($id);
if (!$o) { flash('error', 'Pesanan tidak dijumpai.'); redirect('admin/orders.php'); }

if (is_post()) {
    csrf_check();
    $act = (string)($_POST['action'] ?? '');
    try {
        if ($act === 'paid') {
            order_mark_paid($id, trim((string)($_POST['payment_ref'] ?? '')), (int)$admin['id'])
                ? flash('success', 'Ditanda DIBAYAR. Stok telah ditolak.') : flash('error', 'Status semasa tidak membenarkan tindakan ini.');
        } elseif ($act === 'verify') {
            bp_sync_order($o, 'admin') ? flash('success', 'Billplz mengesahkan bayaran diterima.') : flash('error', 'Billplz: bayaran belum diterima.');
        } elseif ($act === 'ship') {
            // Urutan status dikuatkuasakan dalam order_mark_shipped().
            $cour = courier_resolve((string)($_POST['courier'] ?? ''), (string)($_POST['courier_other'] ?? ''));
            order_mark_shipped($id, $cour, (string)($_POST['tracking_no'] ?? ''))
                ? flash('success', 'Ditanda DIHANTAR.')
                : flash('error', 'Hanya pesanan yang sudah DIBAYAR boleh ditanda dihantar. Status semasa: ' . status_label($o['status']) . '.');
        } elseif ($act === 'complete') {
            if ($o['status'] !== 'shipped') {
                flash('error', 'Hanya pesanan yang sudah DIHANTAR boleh ditanda selesai. Status semasa: ' . status_label($o['status']) . '.');
            } else {
                db()->prepare("UPDATE orders SET status='completed' WHERE id=?")->execute([$id]);
                flash('success', 'Pesanan SELESAI.');
            }
        } elseif ($act === 'spx_book') {
            $r = spx_book([$id]);
            isset($r['ok'][$id])
                ? flash('success', 'Ditempah di SPX. No. tracking: ' . $r['ok'][$id] . '. Cetak AWB dan lekat pada parcel.')
                : flash('error', 'SPX: ' . ($r['fail'][$id] ?? 'gagal.'));
        } elseif ($act === 'spx_sync') {
            spx_sync([$o]) ? flash('success', 'Status SPX dikemas kini.') : flash('error', 'SPX belum ada maklumat untuk parcel ini. Cuba sebentar lagi.');
        } elseif ($act === 'spx_unbook') {
            spx_unbook($o);
            flash('success', 'Tempahan SPX dibatalkan. Pesanan kembali ke DIBAYAR dan boleh ditempah semula.');
        } elseif ($act === 'jnt_book') {
            $r = jnt_book([$id]);
            isset($r['ok'][$id])
                ? flash('success', 'Ditempah di J&T. No. tracking: ' . $r['ok'][$id] . '. Cetak label dan lekat pada parcel.')
                : flash('error', 'J&T: ' . ($r['fail'][$id] ?? 'gagal.'));
        } elseif ($act === 'jnt_sync') {
            jnt_sync([$o]) ? flash('success', 'Status J&T dikemas kini.') : flash('error', 'J&T belum ada imbasan untuk parcel ini. Cuba sebentar lagi.');
        } elseif ($act === 'jnt_unbook') {
            jnt_unbook($o);
            flash('success', 'Tempahan J&T dibatalkan. Pesanan kembali ke DIBAYAR dan boleh ditempah semula.');
        } elseif ($act === 'cancel') {
            // Parcel kurier yang belum diambil mesti dibatalkan di kurier juga, jika
            // tidak kurier masih datang/menerima parcel untuk pesanan yang sudah batal.
            if ($o['courier'] === 'spx' && $o['tracking_no'] !== '' && $o['status'] === 'shipped' && spx_enabled()) {
                try { spx_cancel_remote($o); }
                catch (Throwable $ex) { flash('error', 'Parcel ' . $o['tracking_no'] . ' TIDAK dapat dibatalkan di SPX (' . $ex->getMessage() . '). Batalkan secara manual di portal SPX.'); }
            }
            if ($o['courier'] === 'jnt' && $o['tracking_no'] !== '' && $o['status'] === 'shipped' && jnt_enabled()) {
                try { jnt_cancel_remote($o); }
                catch (Throwable $ex) { flash('error', 'Parcel ' . $o['tracking_no'] . ' TIDAK dapat dibatalkan di J&T (' . $ex->getMessage() . '). Hubungi cawangan J&T.'); }
            }
            order_cancel($id, (int)$admin['id']) ? flash('success', 'Pesanan dibatalkan' . ((int)$o['stock_deducted'] ? ' dan stok dipulangkan.' : '.')) : flash('error', 'Sudah dibatalkan.');
        } elseif ($act === 'note') {
            db()->prepare('UPDATE orders SET admin_note=? WHERE id=?')->execute([mb_substr(trim((string)($_POST['admin_note'] ?? '')), 0, 500), $id]);
            flash('success', 'Nota disimpan.');
        } else {
            flash('error', 'Tindakan tidak dikenali.');
        }
    } catch (Throwable $ex) {
        error_log('admin order: ' . $ex->getMessage());
        flash('error', 'Ralat: ' . $ex->getMessage());
    }
    redirect('admin/order.php?id=' . $id);
}
$items = order_items($id);
$logs = db()->prepare('SELECT * FROM payment_logs WHERE order_id = ? ORDER BY id DESC LIMIT 10'); $logs->execute([$id]); $logs = $logs->fetchAll();
$spxLogs = db()->prepare('SELECT * FROM spx_logs WHERE order_id = ? ORDER BY id DESC LIMIT 15'); $spxLogs->execute([$id]); $spxLogs = $spxLogs->fetchAll();
$jntLogs = db()->prepare('SELECT * FROM jnt_logs WHERE order_id = ? ORDER BY id DESC LIMIT 15'); $jntLogs->execute([$id]); $jntLogs = $jntLogs->fetchAll();
$isSpx = $o['courier'] === 'spx' && $o['tracking_no'] !== '';
$isJnt = $o['courier'] === 'jnt' && $o['tracking_no'] !== '';
// Pesanan yang ditempah melalui API: no. tracking diurus oleh kurier, bukan borang manual.
$apiBooked = ($isSpx && spx_enabled()) || ($isJnt && jnt_enabled());
$waMsg = "Salam {$o['customer_name']}, berkenaan pesanan {$o['order_no']} (" . money($o['total']) . '). Semak status: ' . order_public_url($o);
admin_header('Pesanan ' . $o['order_no'], 'orders.php');
?>
<div class="grid lg:grid-cols-3 gap-4">
  <section class="lg:col-span-2 space-y-4">
    <div class="bg-white rounded-xl border p-4">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-3"><?= status_badge($o['status']) ?>
        <span class="text-sm text-slate-500"><?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?> · <?= e(pay_label($o['payment_method'])) ?></span></div>
      <table class="w-full text-sm">
        <?php foreach ($items as $it): ?><tr class="border-b"><td class="py-2"><?= (int)$it['qty'] ?> × <?= e($it['name']) ?> <span class="text-slate-400"><?= e($it['sku']) ?></span></td><td class="py-2 text-right"><?= e(money($it['line_total'])) ?></td></tr><?php endforeach; ?>
        <tr><td class="py-1 text-slate-500">Penghantaran</td><td class="py-1 text-right"><?= e(money($o['shipping'])) ?></td></tr>
        <tr class="font-bold text-base"><td class="py-1">Jumlah</td><td class="py-1 text-right"><?= e(money($o['total'])) ?></td></tr>
      </table>
      <p class="text-xs text-slate-500 mt-2">Stok: <?= (int)$o['stock_deducted'] ? 'sudah ditolak' : 'belum ditolak (ditolak bila ditanda Dibayar)' ?></p>
    </div>
    <div class="bg-white rounded-xl border p-4 text-sm space-y-1">
      <h2 class="font-semibold mb-1">Pelanggan</h2>
      <p><b><?= e($o['customer_name']) ?></b> · <?= e($o['phone']) ?><?= $o['email'] ? ' · ' . e($o['email']) : '' ?></p>
      <p><?= e($o['address']) ?>, <?= e($o['postcode']) ?> <?= e($o['city']) ?>, <?= e($o['state']) ?></p>
      <?php if ($o['note']): ?><p class="bg-amber-50 rounded p-2">Nota pelanggan: <?= e($o['note']) ?></p><?php endif; ?>
      <p class="pt-2 flex flex-wrap gap-2">
        <a class="btn bg-green-600 text-white" target="_blank" rel="noopener" href="<?= e(wa_link($o['phone'], $waMsg)) ?>">WhatsApp pelanggan</a>
        <a class="btn bg-slate-100" target="_blank" href="<?= e(order_public_url($o)) ?>">Halaman status pelanggan ↗</a>
        <a class="btn bg-brand text-white" target="_blank" href="<?= e(url('invoice.php?id=' . $id)) ?>">🧾 <?= in_array($o['status'], ['paid', 'shipped', 'completed'], true) ? 'Resit rasmi' : 'Invois' ?></a>
        <a class="btn bg-slate-800 text-white" target="_blank" href="<?= e(url('admin/label.php?id=' . $id)) ?>">🏷️ Cetak label penghantaran</a>
        <?php if ($o['receipt_file']): ?><a class="btn bg-amber-100 text-amber-900" target="_blank" href="<?= e(url('admin/receipt.php?id=' . $id)) ?>">📎 Bukti bayaran pelanggan</a><?php endif; ?>
      </p>
    </div>
    <?php if ($logs): ?><details class="bg-white rounded-xl border p-4 text-xs"><summary class="font-semibold text-sm cursor-pointer">Log bayaran (<?= count($logs) ?>)</summary>
      <?php foreach ($logs as $l): ?><pre class="mt-2 bg-slate-50 rounded p-2 overflow-x-auto"><?= e($l['created_at'] . ' [' . $l['source'] . '] ' . $l['payload']) ?></pre><?php endforeach; ?></details><?php endif; ?>
    <?php if ($spxLogs): ?><details class="bg-white rounded-xl border p-4 text-xs"><summary class="font-semibold text-sm cursor-pointer">Log SPX (<?= count($spxLogs) ?>)</summary>
      <?php foreach ($spxLogs as $l): ?><pre class="mt-2 bg-slate-50 rounded p-2 overflow-x-auto whitespace-pre-wrap break-all"><?= e($l['created_at'] . ' [' . $l['source'] . '] ' . $l['payload']) ?></pre><?php endforeach; ?></details><?php endif; ?>
    <?php if ($jntLogs): ?><details class="bg-white rounded-xl border p-4 text-xs"><summary class="font-semibold text-sm cursor-pointer">Log J&amp;T (<?= count($jntLogs) ?>)</summary>
      <?php foreach ($jntLogs as $l): ?><pre class="mt-2 bg-slate-50 rounded p-2 overflow-x-auto whitespace-pre-wrap break-all"><?= e($l['created_at'] . ' [' . $l['source'] . '] ' . $l['payload']) ?></pre><?php endforeach; ?></details><?php endif; ?>
  </section>

  <aside class="space-y-4">
    <div class="bg-white rounded-xl border p-4 space-y-3">
      <h2 class="font-semibold">Tindakan</h2>
      <?php if (in_array($o['status'], ['new', 'pending_payment'], true)): ?>
        <div><?= pay_hint($o) ?></div>
        <?php if ($o['receipt_file']): ?><p class="text-xs bg-rose-50 text-rose-800 rounded p-2">Buka <b>📎 Bukti bayaran</b>, kemudian <b>semak akaun bank</b> bahawa <?= e(money($o['total'])) ?> benar-benar masuk sebelum tanda Dibayar. Resit boleh dipalsukan.</p><?php endif; ?>
        <?php if ($o['bill_code'] && bp_enabled()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="verify"><button class="btn bg-slate-100 w-full justify-center">Semak bayaran di Billplz</button></form><?php endif; ?>
        <form method="post" class="space-y-2" onsubmit="return confirm('Sahkan bayaran diterima? Stok akan ditolak.')"><?= csrf_field() ?><input type="hidden" name="action" value="paid">
          <input name="payment_ref" class="inp" placeholder="Rujukan bayaran (pilihan)"><button class="btn bg-emerald-600 text-white w-full justify-center">Tanda DIBAYAR</button></form>
      <?php endif; ?>
      <?php if ($o['status'] === 'paid' && (spx_enabled() || jnt_enabled())): ?>
        <?php if (ship_sender_ready()): ?>
          <?php if (spx_enabled()): ?>
            <form method="post" data-confirm="Tempah penghantaran SPX untuk pesanan ini?"><?= csrf_field() ?><input type="hidden" name="action" value="spx_book">
              <button class="btn bg-orange-600 text-white w-full justify-center">🚚 Tempah SPX (<?= spx_collect_type() === 1 ? 'pickup' : 'drop-off' ?>)</button></form>
          <?php endif; ?>
          <?php if (jnt_enabled()): ?>
            <form method="post" data-confirm="Tempah penghantaran J&T untuk pesanan ini?"><?= csrf_field() ?><input type="hidden" name="action" value="jnt_book">
              <button class="btn bg-red-700 text-white w-full justify-center">🚚 Tempah J&amp;T (<?= setting('jnt_service') === '1' ? 'pickup' : 'drop-off' ?>)<?= jnt_sandbox() ? ' · sandbox' : '' ?></button></form>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-xs bg-amber-50 text-amber-900 rounded p-2">Isi alamat pengirim dalam <a class="underline" href="<?= e(url('admin/settings.php#pengirim')) ?>">Tetapan</a> untuk tempah kurier dari sini.</p>
        <?php endif; ?>
        <p class="text-center text-xs text-slate-400">— atau isi tracking secara manual —</p>
      <?php endif; ?>
      <?php if ($isJnt && in_array($o['status'], ['shipped', 'completed'], true)): ?>
        <div class="rounded-lg border border-red-200 bg-red-50 p-3 space-y-2 text-sm">
          <p class="font-semibold text-red-900">J&amp;T · <span class="font-mono"><?= e($o['tracking_no']) ?></span></p>
          <p>Status parcel: <b><?= e($o['jnt_status_code'] !== '' ? jnt_status_label($o['jnt_status_code'], $o['jnt_status']) : 'Belum ada kemas kini') ?></b>
            <?php if ($o['jnt_status_at']): ?><span class="block text-xs text-slate-500"><?= e(date('d/m/Y H:i', strtotime($o['jnt_status_at']))) ?></span><?php endif; ?></p>
          <?php if ($o['jnt_fee'] !== null): ?><p class="text-xs text-slate-600">Anggaran kos J&amp;T: <?= e(money($o['jnt_fee'])) ?> · dicaj kepada pembeli: <?= e(money($o['shipping'])) ?></p><?php endif; ?>
          <?php if (jnt_enabled()): ?>
            <a class="btn bg-red-700 text-white w-full justify-center" target="_blank" href="<?= e(url('admin/jnt-awb.php?id=' . $id)) ?>">🖨️ Cetak label J&amp;T</a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="jnt_sync"><button class="btn bg-white w-full justify-center">Semak status J&amp;T</button></form>
            <?php if ($o['status'] === 'shipped' && $o['jnt_status_code'] === ''): ?>
              <form method="post" data-confirm="Batalkan tempahan J&T ini? Pesanan kembali ke DIBAYAR supaya boleh ditempah semula."><?= csrf_field() ?><input type="hidden" name="action" value="jnt_unbook">
                <button class="btn bg-white text-rose-700 w-full justify-center">Batal tempahan J&amp;T</button></form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($isSpx && in_array($o['status'], ['shipped', 'completed'], true)): ?>
        <div class="rounded-lg border border-orange-200 bg-orange-50 p-3 space-y-2 text-sm">
          <p class="font-semibold text-orange-900">SPX · <span class="font-mono"><?= e($o['tracking_no']) ?></span></p>
          <p>Status parcel: <b><?= e($o['spx_status_code'] !== '' ? spx_status_label($o['spx_status_code'], $o['spx_status']) : 'Belum ada kemas kini') ?></b>
            <?php if ($o['spx_status_at']): ?><span class="block text-xs text-slate-500"><?= e(date('d/m/Y H:i', strtotime($o['spx_status_at']))) ?></span><?php endif; ?></p>
          <?php if ($o['spx_fee'] !== null): ?><p class="text-xs text-slate-600">Anggaran kos SPX: <?= e(money($o['spx_fee'])) ?> · dicaj kepada pembeli: <?= e(money($o['shipping'])) ?></p><?php endif; ?>
          <?php if (spx_enabled()): ?>
            <a class="btn bg-orange-600 text-white w-full justify-center" target="_blank" href="<?= e(url('admin/spx-awb.php?id=' . $id)) ?>">🖨️ Cetak AWB SPX</a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="spx_sync"><button class="btn bg-white w-full justify-center">Semak status SPX</button></form>
            <?php if ($o['status'] === 'shipped' && in_array($o['spx_status_code'], ['', '1001'], true)): ?>
              <form method="post" data-confirm="Batalkan tempahan SPX ini? Pesanan kembali ke DIBAYAR supaya boleh ditempah semula."><?= csrf_field() ?><input type="hidden" name="action" value="spx_unbook">
                <button class="btn bg-white text-rose-700 w-full justify-center">Batal tempahan SPX</button></form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if (in_array($o['status'], ['paid', 'shipped'], true) && !$apiBooked): ?>
        <?php $known = isset(couriers()[$o['courier']]); $isOther = $o['courier'] !== '' && !$known;
              // Pesanan belum berkurier: pilih awal kurier lazim kedai (last_courier).
              $selC = $o['courier'] !== '' ? $o['courier'] : (string)setting('last_courier'); ?>
        <form method="post" class="space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="ship">
          <select name="courier" id="courierSel" class="inp">
            <option value="">— pilih kurier —</option>
            <?php foreach (couriers() as $ck => $cv): ?>
              <option value="<?= e($ck) ?>" <?= $selC === $ck ? 'selected' : '' ?>><?= e($cv[0]) ?></option>
            <?php endforeach; ?>
            <option value="lain" <?= $isOther ? 'selected' : '' ?>>Lain-lain…</option>
          </select>
          <input name="courier_other" id="courierOther" class="inp" placeholder="Nama kurier"
                 value="<?= $isOther ? e($o['courier']) : '' ?>" <?= $isOther ? '' : 'hidden' ?>>
          <input name="tracking_no" class="inp" placeholder="No. tracking" value="<?= e($o['tracking_no']) ?>" required>
          <button class="btn bg-sky-600 text-white w-full justify-center"><?= $o['status'] === 'shipped' ? 'Kemas kini tracking' : 'Tanda DIHANTAR' ?></button></form>
        <script>(function(){var s=document.getElementById('courierSel'),t=document.getElementById('courierOther');
          if(!s||!t)return;s.addEventListener('change',function(){t.hidden=s.value!=='lain';if(!t.hidden)t.focus();});})();</script>
        <?php if ($o['tracking_no'] && tracking_url($o['courier'], $o['tracking_no'])): ?>
          <a class="btn bg-slate-100 w-full justify-center" target="_blank" rel="noopener"
             href="<?= e(tracking_url($o['courier'], $o['tracking_no'])) ?>">Jejak di <?= e(courier_label($o['courier'])) ?> ↗</a>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($o['status'] === 'shipped'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="complete"><button class="btn bg-green-700 text-white w-full justify-center">Tanda SELESAI</button></form><?php endif; ?>
      <?php if ($o['status'] !== 'cancelled'): ?><form method="post" onsubmit="return confirm('Batalkan pesanan ini?<?= (int)$o['stock_deducted'] ? ' Stok akan dipulangkan.' : '' ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn bg-rose-50 text-rose-700 w-full justify-center">Batal pesanan</button></form><?php endif; ?>
    </div>
    <form method="post" class="bg-white rounded-xl border p-4 space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="note">
      <label class="lbl">Nota dalaman</label><textarea name="admin_note" rows="3" class="inp"><?= e($o['admin_note']) ?></textarea><button class="btn bg-slate-100">Simpan nota</button></form>
    <?php if ($o['payment_ref'] || $o['bill_code']): ?><div class="bg-white rounded-xl border p-4 text-xs text-slate-600">
      <?php if ($o['bill_code']): ?><p>Bill code: <span class="font-mono"><?= e($o['bill_code']) ?></span></p><?php endif; ?>
      <?php if ($o['payment_ref']): ?><p>Rujukan: <span class="font-mono"><?= e($o['payment_ref']) ?></span></p><?php endif; ?>
      <?php if ($o['paid_at']): ?><p>Dibayar: <?= e(date('d/m/Y H:i', strtotime($o['paid_at']))) ?></p><?php endif; ?></div><?php endif; ?>
  </aside>
</div>
<?php admin_footer();
