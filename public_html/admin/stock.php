<?php
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_layout.php';
$admin = require_admin();
$products = db()->query('SELECT id, name, size, sku, stock FROM products ORDER BY sort, id')->fetchAll();
$pid = (int)($_GET['product_id'] ?? 0);

if (is_post()) {
    csrf_check();
    $productId = (int)($_POST['product_id'] ?? 0);
    $type = (string)($_POST['type'] ?? '');
    $qty = (int)($_POST['qty'] ?? 0);
    $pdo = db();
    try {
        if (!in_array($type, ['in', 'out', 'adjust'], true)) throw new RuntimeException('Jenis pergerakan tidak sah.');
        if ($type !== 'adjust' && $qty <= 0) throw new RuntimeException('Kuantiti mesti lebih daripada 0.');
        if ($type === 'adjust' && $qty < 0)  throw new RuntimeException('Baki sebenar tidak boleh negatif.');
        $pdo->beginTransaction();
        if ($type === 'adjust') { // qty = baki sebenar selepas kiraan fizikal
            $st = $pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE'); $st->execute([$productId]);
            $cur = $st->fetchColumn();
            if ($cur === false) throw new RuntimeException('Produk tidak dijumpai.');
            $delta = $qty - (int)$cur;
        } else {
            $delta = $type === 'in' ? $qty : -$qty;
        }
        $exp  = trim((string)($_POST['expiry_date'] ?? ''));
        $cost = trim((string)($_POST['unit_cost'] ?? ''));
        stock_move($productId, $delta, $type, [
            'batch_no'    => mb_substr(trim((string)($_POST['batch_no'] ?? '')), 0, 60) ?: null,
            'expiry_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp) ? $exp : null,
            // Kosong mesti kekal null; sebelum ini medan tidak diisi direkod sebagai RM0.00.
            'unit_cost'   => $cost !== '' ? round((float)$cost, 2) : null,
            'supplier'    => mb_substr(trim((string)($_POST['supplier'] ?? '')), 0, 120) ?: null,
            'note'        => mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255) ?: null,
            'admin_id'    => (int)$admin['id'],
        ]);
        $pdo->commit();
        flash('success', 'Pergerakan stok direkodkan (' . ($delta >= 0 ? '+' : '') . $delta . ').');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $ex->getMessage());
    }
    redirect('admin/stock.php?product_id=' . $productId);
}

$args = []; $w = '';
if ($pid) { $w = 'WHERE m.product_id = ?'; $args[] = $pid; }
$per = admin_per_page(50);
$st = db()->prepare("SELECT COUNT(*) FROM stock_movements m $w"); $st->execute($args); $total = (int)$st->fetchColumn();
$page = min(max(1, (int)($_GET['page'] ?? 1)), max(1, (int)ceil($total / $per)));
$keep = array_filter(['product_id' => $pid ?: '', 'per' => $per === 50 ? '' : $per], fn($v) => $v !== '');
$st = db()->prepare("SELECT m.*, p.name, p.sku, a.username, o.order_no FROM stock_movements m JOIN products p ON p.id = m.product_id
    LEFT JOIN admins a ON a.id = m.admin_id LEFT JOIN orders o ON o.id = m.order_id $w ORDER BY m.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($args); $moves = $st->fetchAll();
$types = ['in' => ['Masuk', 'text-emerald-700'], 'out' => ['Keluar', 'text-rose-700'], 'adjust' => ['Pelarasan', 'text-slate-700'], 'sale' => ['Jualan', 'text-sky-700'], 'return' => ['Pulangan', 'text-amber-700']];
admin_header('Stok', 'stock.php');
?>
<div class="grid lg:grid-cols-3 gap-4">
  <form method="post" class="bg-white rounded-xl border p-4 space-y-3 self-start">
    <?= csrf_field() ?>
    <h2 class="font-semibold">Rekod pergerakan stok</h2>
    <div><label class="lbl">Produk</label><select name="product_id" class="inp" required><?php foreach ($products as $p): ?>
      <option value="<?= (int)$p['id'] ?>" <?= $pid === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name'] . ' ' . $p['size']) ?> (baki <?= (int)$p['stock'] ?>)</option><?php endforeach; ?></select></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="lbl">Jenis</label><select name="type" id="mtype" class="inp"><option value="in">Stok masuk</option><option value="out">Stok keluar (rosak/sampel)</option><option value="adjust">Pelarasan (kiraan fizikal)</option></select></div>
      <div><label class="lbl" id="qlabel">Kuantiti</label><input name="qty" type="number" min="0" class="inp" required></div>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="lbl">No. batch</label><input name="batch_no" class="inp"></div>
      <div><label class="lbl">Tarikh luput</label><input name="expiry_date" type="date" class="inp"></div>
      <div><label class="lbl">Kos seunit (RM)</label><input name="unit_cost" type="number" step="0.01" min="0" class="inp"></div>
      <div><label class="lbl">Pembekal</label><input name="supplier" class="inp"></div>
    </div>
    <div><label class="lbl">Catatan</label><input name="note" class="inp"></div>
    <button class="btn bg-brand text-white">Simpan</button>
    <p class="text-xs text-slate-500">Jualan dan pulangan direkod automatik bila pesanan ditanda Dibayar atau Batal.</p>
  </form>

  <section class="lg:col-span-2 bg-white rounded-xl border overflow-x-auto">
    <div class="flex items-center justify-between p-4 pb-2"><h2 class="font-semibold">Sejarah pergerakan</h2>
      <form><select name="product_id" class="inp !w-auto text-sm" onchange="this.form.submit()"><option value="0">Semua produk</option>
        <?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $pid === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></form></div>
    <table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Tarikh</th><th class="px-3 py-2">Produk</th><th class="px-3 py-2">Jenis</th><th class="px-3 py-2 text-right">Qty</th><th class="px-3 py-2 text-right">Baki</th><th class="px-3 py-2">Butiran</th></tr></thead><tbody>
    <?php foreach ($moves as $m): ?><tr class="border-t align-top">
      <td class="px-3 py-2 whitespace-nowrap text-slate-500"><?= e(date('d/m/y H:i', strtotime($m['created_at']))) ?></td>
      <td class="px-3 py-2"><?= e($m['name']) ?></td>
      <td class="px-3 py-2 font-semibold <?= $types[$m['type']][1] ?>"><?= e($types[$m['type']][0]) ?></td>
      <td class="px-3 py-2 text-right font-mono"><?= ((int)$m['qty'] > 0 ? '+' : '') . (int)$m['qty'] ?></td>
      <td class="px-3 py-2 text-right font-mono"><?= (int)$m['balance_after'] ?></td>
      <td class="px-3 py-2 text-xs text-slate-600"><?= e(implode(' · ', array_filter([
          $m['batch_no'] ? 'Batch ' . $m['batch_no'] : '', $m['expiry_date'] ? 'Luput ' . date('d/m/Y', strtotime($m['expiry_date'])) : '',
          $m['unit_cost'] !== null ? 'Kos ' . money($m['unit_cost']) : '', $m['supplier'], $m['order_no'], $m['note'], $m['username'] ? 'oleh ' . $m['username'] : '']))) ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$moves): ?><tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada pergerakan stok.</td></tr><?php endif; ?>
    </tbody></table>
    <div class="px-4 pb-4"><?= admin_pager($total, $page, $per, $keep) ?></div>
  </section>
</div>
<script>document.getElementById('mtype').addEventListener('change',function(){document.getElementById('qlabel').textContent=this.value==='adjust'?'Baki sebenar (unit)':'Kuantiti';});</script>
<?php admin_footer();
