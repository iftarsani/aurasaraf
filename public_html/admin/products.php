<?php
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

if (is_post()) {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'toggle') {
        db()->prepare('UPDATE products SET active = 1 - active WHERE id = ?')->execute([$id]);
        flash('success', 'Status paparan dikemas kini.');
    } elseif ($act === 'delete') {
        $st = db()->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = ?'); $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            db()->prepare('UPDATE products SET active = 0 WHERE id = ?')->execute([$id]);
            flash('error', 'Produk ada dalam rekod pesanan, jadi hanya disorok (bukan dipadam).');
        } else {
            $st = db()->prepare('SELECT image, gallery FROM products WHERE id = ?'); $st->execute([$id]);
            $imgs = product_images($st->fetch() ?: []);
            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            foreach ($imgs as $img) @unlink(PUBLIC_PATH . '/uploads/products/' . basename($img));
            flash('success', 'Produk dipadam.');
        }
    }
    redirect('admin/products.php');
}
$rows = db()->query('SELECT * FROM products ORDER BY sort, id')->fetchAll();
admin_header('Produk', 'products.php');
?>
<div class="mb-4"><a href="<?= e(url('admin/product-edit.php')) ?>" class="btn bg-brand text-white">+ Tambah produk</a></div>
<div class="bg-white rounded-xl border overflow-x-auto"><table class="w-full text-sm">
  <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Produk</th><th class="px-4 py-2">SKU</th><th class="px-4 py-2">Harga</th><th class="px-4 py-2">Stok</th><th class="px-4 py-2">Papar</th><th class="px-4 py-2"></th></tr></thead><tbody>
  <?php foreach ($rows as $p): ?><tr class="border-t">
    <td class="px-4 py-2"><div class="flex items-center gap-3"><?php if ($p['image']): ?><img src="<?= e(product_image_url($p['image'])) ?>" class="w-10 h-10 rounded object-cover" alt=""><?php else: ?><div class="w-10 h-10 rounded bg-amber-100 grid place-items-center">🧴</div><?php endif; ?>
      <div><b><?= e($p['name']) ?></b> <span class="text-slate-500"><?= e($p['size']) ?></span></div></div></td>
    <td class="px-4 py-2 font-mono text-xs"><?= e($p['sku']) ?></td>
    <td class="px-4 py-2"><?= e(money($p['price'])) ?><?php if ((float)$p['compare_price'] > 0): ?> <s class="text-slate-400 text-xs"><?= e(money($p['compare_price'])) ?></s><?php endif; ?></td>
    <td class="px-4 py-2 font-semibold <?= (int)$p['stock'] <= 0 ? 'text-rose-600' : ((int)$p['stock'] <= (int)$p['low_stock'] ? 'text-amber-600' : '') ?>"><?= (int)$p['stock'] ?></td>
    <td class="px-4 py-2"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="toggle">
      <button class="text-xs font-semibold rounded-full px-2.5 py-0.5 <?= $p['active'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>"><?= $p['active'] ? 'Dipapar' : 'Disorok' ?></button></form></td>
    <td class="px-4 py-2 text-right whitespace-nowrap">
      <a class="text-brand-leaf font-semibold mr-3" href="<?= e(url('admin/stock.php?product_id=' . $p['id'])) ?>">Stok</a>
      <a class="text-brand-leaf font-semibold mr-3" href="<?= e(url('admin/product-edit.php?id=' . $p['id'])) ?>">Sunting</a>
      <form method="post" class="inline" onsubmit="return confirm('Padam produk ini?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="delete"><button class="text-rose-600">Padam</button></form></td>
  </tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada produk.</td></tr><?php endif; ?>
</tbody></table></div>
<?php admin_footer();
