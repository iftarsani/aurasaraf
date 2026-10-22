<?php
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();
$pdo = db();
$cnt = $pdo->query("SELECT status, COUNT(*) c FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$month = $pdo->query("SELECT COALESCE(SUM(total),0) s, COUNT(*) c FROM orders WHERE status IN ('paid','shipped','completed') AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetch();
$units = (int)$pdo->query("SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status IN ('paid','shipped','completed') AND o.paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
$low = $pdo->query('SELECT * FROM products WHERE active = 1 AND stock <= low_stock ORDER BY stock')->fetchAll();
$exp = $pdo->query("SELECT m.batch_no, m.expiry_date, m.qty, p.name FROM stock_movements m JOIN products p ON p.id = m.product_id
    WHERE m.type = 'in' AND m.expiry_date IS NOT NULL AND m.expiry_date <= (CURDATE() + INTERVAL 90 DAY) ORDER BY m.expiry_date LIMIT 10")->fetchAll();
$recent = $pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
admin_header('Papan pemuka', 'index.php');
// $href kosong = kad biasa; jika diisi, seluruh kad jadi pautan ke senarai kerja.
$card = function ($label, $val, $sub = '', $href = '') {
    $in = "<p class='text-xs uppercase tracking-wide text-slate-500'>$label</p>"
        . "<p class='text-2xl font-bold mt-1'>$val</p><p class='text-xs text-slate-500'>$sub</p>";
    return $href
        ? "<a href='" . e($href) . "' class='block bg-white rounded-xl border p-4 hover:border-brand-leaf hover:shadow-sm transition'>$in</a>"
        : "<div class='bg-white rounded-xl border p-4'>$in</div>";
};
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
  <?= $card('Pesanan baru', (int)($cnt['new'] ?? 0), 'perlu disemak →', url('admin/orders.php?status=new')) ?>
  <?= $card('Perlu pos hari ini', (int)($cnt['paid'] ?? 0), 'sudah dibayar, belum dihantar →', url('admin/shipping.php')) ?>
  <?= $card('Jualan bulan ini', e(money($month['s'])), (int)$month['c'] . ' pesanan') ?>
  <?= $card('Unit terjual bulan ini', $units, 'botol') ?>
</div>
<div class="grid lg:grid-cols-2 gap-4 mb-6">
  <section class="bg-white rounded-xl border p-4">
    <h2 class="font-semibold mb-2">Stok rendah</h2>
    <?php if (!$low): ?><p class="text-sm text-slate-500">Semua stok mencukupi.</p><?php endif; ?>
    <?php foreach ($low as $p): ?><div class="flex justify-between text-sm py-1.5 border-b last:border-0"><span><?= e($p['name']) ?> <span class="text-slate-400"><?= e($p['sku']) ?></span></span>
      <b class="<?= (int)$p['stock'] <= 0 ? 'text-rose-600' : 'text-amber-600' ?>"><?= (int)$p['stock'] ?> unit</b></div><?php endforeach; ?>
    <a href="<?= e(url('admin/stock.php')) ?>" class="text-sm text-brand-leaf font-semibold inline-block mt-2">Tambah stok →</a>
  </section>
  <section class="bg-white rounded-xl border p-4">
    <h2 class="font-semibold mb-2">Batch hampir luput (90 hari)</h2>
    <?php if (!$exp): ?><p class="text-sm text-slate-500">Tiada batch hampir luput.</p><?php endif; ?>
    <?php foreach ($exp as $x): $past = strtotime($x['expiry_date']) < time(); ?><div class="flex justify-between text-sm py-1.5 border-b last:border-0"><span><?= e($x['name']) ?> · batch <?= e($x['batch_no'] ?: '-') ?></span>
      <b class="<?= $past ? 'text-rose-600' : 'text-amber-600' ?>"><?= e(date('d/m/Y', strtotime($x['expiry_date']))) ?></b></div><?php endforeach; ?>
  </section>
</div>
<section class="bg-white rounded-xl border overflow-x-auto">
  <h2 class="font-semibold p-4 pb-2">Pesanan terkini</h2>
  <table class="w-full text-sm"><tbody>
  <?php foreach ($recent as $o): ?><tr class="border-t hover:bg-slate-50"><td class="px-4 py-2 font-mono"><a class="text-brand-leaf font-semibold" href="<?= e(url('admin/order.php?id=' . $o['id'])) ?>"><?= e($o['order_no']) ?></a></td>
    <td class="px-4 py-2"><?= e($o['customer_name']) ?></td><td class="px-4 py-2"><?= e(money($o['total'])) ?></td><td class="px-4 py-2"><?= status_badge($o['status']) ?></td>
    <td class="px-4 py-2 text-slate-500 whitespace-nowrap"><?= e(date('d/m H:i', strtotime($o['created_at']))) ?></td></tr><?php endforeach; ?>
  <?php if (!$recent): ?><tr><td class="px-4 py-6 text-slate-500">Belum ada pesanan.</td></tr><?php endif; ?>
  </tbody></table>
</section>
<?php admin_footer();
