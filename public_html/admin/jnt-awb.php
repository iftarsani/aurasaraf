<?php
/**
 * Label rasmi J&T (PDF) untuk satu pesanan: ?id=5. J&T memberi satu label bagi
 * setiap panggilan, jadi ?ids=5,6,7 memaparkan senarai pautan — satu PDF untuk
 * setiap parcel. PDF diminta semula setiap kali dan tidak disimpan.
 */
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? $_GET['id'] ?? ''))))));
$back = count($ids) === 1 ? 'admin/order.php?id=' . $ids[0] : 'admin/shipping.php';
if (!$ids) redirect('admin/orders.php');
if (!jnt_enabled()) { flash('error', 'J&T belum diset dalam config.php.'); redirect($back); }

$ids = array_slice($ids, 0, 100);
$ph = implode(',', array_fill(0, count($ids), '?'));
$st = db()->prepare("SELECT * FROM orders WHERE id IN ($ph) AND courier = 'jnt' AND tracking_no <> '' AND status IN ('shipped','completed') ORDER BY id");
$st->execute($ids);
$orders = $st->fetchAll();
if (!$orders) { flash('error', 'Tiada pesanan J&T yang aktif dalam pilihan ini.'); redirect($back); }

if (isset($_GET['id']) && count($orders) === 1) {
    try {
        $pdf = jnt_label_pdf($orders[0]);
    } catch (Throwable $ex) {
        error_log('jnt-awb: ' . $ex->getMessage());
        flash('error', $ex->getMessage());
        redirect($back);
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="JNT-' . preg_replace('/[^A-Za-z0-9-]/', '', $orders[0]['tracking_no']) . '.pdf"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
    exit;
}

admin_header('Label J&T', 'shipping.php');
?>
<div class="bg-white rounded-xl border p-5 max-w-xl space-y-3">
  <h1 class="font-semibold text-lg">Label J&amp;T (<?= count($orders) ?>)</h1>
  <p class="text-sm text-slate-500">J&amp;T memberi satu PDF untuk setiap parcel. Buka setiap satu dan cetak.</p>
  <ul class="divide-y text-sm">
    <?php foreach ($orders as $o): ?>
      <li class="py-2 flex items-center gap-3"><span class="font-mono"><?= e($o['order_no']) ?></span><span class="text-slate-500 font-mono"><?= e($o['tracking_no']) ?></span>
        <a class="btn bg-red-700 text-white ml-auto" target="_blank" href="<?= e(url('admin/jnt-awb.php?id=' . (int)$o['id'])) ?>">🖨️ Buka label</a></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php admin_footer();
