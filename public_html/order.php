<?php
require __DIR__ . '/../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_partials.php';

$o = order_find_by_no((string)($_GET['no'] ?? ''));
if (!$o || !hash_equals($o['access_token'], (string)($_GET['t'] ?? ''))) {
    http_response_code(404);
    page_head('Pesanan tidak dijumpai');
    echo '<main class="wrap narrow sec"><h2>Pesanan tidak dijumpai</h2><p class="lead">Pautan tidak sah atau telah tamat.</p><p><a class="btn" href="' . e(url()) . '">Kembali ke kedai</a></p></main>';
    page_foot(); exit;
}
// Pelanggan kembali sebelum callback sampai: semak terus dengan Billplz
if ($o['payment_method'] === 'fpx' && $o['status'] === 'pending_payment' && $o['bill_code'] && bp_enabled()) {
    try { if (bp_sync_order($o, 'orderpage')) $o = order_find((int)$o['id']); } catch (Throwable $ex) { error_log('verify: ' . $ex->getMessage()); }
}
// Parcel SPX: tarik status terkini jika webhook tidak sampai (contoh: localhost),
// paling kerap sekali setiap 30 minit bagi satu pesanan.
if ($o['courier'] === 'spx' && $o['tracking_no'] !== '' && $o['status'] === 'shipped' && spx_enabled()
    && (!$o['spx_synced_at'] || strtotime($o['spx_synced_at']) < time() - 1800)) {
    try { spx_sync([$o]); $o = order_find((int)$o['id']); } catch (Throwable $ex) { error_log('spx sync: ' . $ex->getMessage()); }
}
if ($o['courier'] === 'jnt' && $o['tracking_no'] !== '' && $o['status'] === 'shipped' && jnt_enabled()
    && (!$o['jnt_synced_at'] || strtotime($o['jnt_synced_at']) < time() - 1800)) {
    try { jnt_sync([$o]); $o = order_find((int)$o['id']); } catch (Throwable $ex) { error_log('jnt sync: ' . $ex->getMessage()); }
}
$items = order_items((int)$o['id']);
$wa    = setting('whatsapp');
$cls   = in_array($o['status'], ['paid', 'shipped', 'completed'], true) ? 'ok' : ($o['status'] === 'cancelled' ? 'bad' : '');
$msg   = "Salam, saya {$o['customer_name']}. No. pesanan {$o['order_no']}, jumlah " . money($o['total']) . '.';

page_head('Pesanan ' . $o['order_no']);
?>
<header class="top"><div class="wrap top-in"><a class="logo" href="<?= e(url()) ?>"><img src="<?= e(asset('assets/logo-header.png')) ?>" alt="<?= e(setting('shop_name', 'Minyak Aura Saraf')) ?>" width="429" height="120"></a></div></header>
<main class="wrap narrow">
  <?php foreach (flashes() as [$t, $m]): ?><div class="<?= $t === 'error' ? 'alert' : 'notice' ?>"><p><?= e($m) ?></p></div><?php endforeach; ?>
  <div class="status-card">
    <p class="eyebrow">Status pesanan</p>
    <h2 class="mono"><?= e($o['order_no']) ?></h2>
    <span class="badge <?= $cls ?>"><?= e(status_label($o['status'])) ?></span>

    <?php if ($o['status'] === 'pending_payment' && $o['bill_code']): ?>
      <div class="notice"><p>Bayaran FPX belum diterima. Jika anda sudah bayar, muat semula halaman ini dalam seminit.</p></div>
      <p><a class="btn btn-gold" href="<?= e(bp_pay_url($o['bill_code'])) ?>">Bayar sekarang (FPX)</a></p>
    <?php elseif ($o['status'] === 'new' && $o['payment_method'] === 'transfer'): ?>
      <div class="notice"><p>Resit diterima. Kami akan sahkan bayaran dan proses pesanan anda.</p></div>
    <?php elseif ($o['status'] === 'new'): ?>
      <div class="notice"><p>Pesanan direkodkan. Tekan butang WhatsApp di bawah untuk urusan bayaran.</p></div>
    <?php elseif ($o['status'] === 'paid'): ?>
      <div class="notice"><p>Bayaran diterima. Terima kasih! Pesanan sedang disediakan untuk penghantaran.</p></div>
    <?php endif; ?>

    <?php if ($o['tracking_no']): $turl = tracking_url((string)$o['courier'], (string)$o['tracking_no']); ?>
      <dl class="kv"><dt>Kurier</dt><dd><?= e(courier_label((string)$o['courier']) ?: '-') ?></dd>
        <dt>No. tracking</dt><dd class="mono" id="trackNo"><?= e($o['tracking_no']) ?></dd>
        <?php if ($o['courier'] === 'spx' && $o['spx_status_code'] !== ''): ?>
          <dt>Status parcel</dt><dd><?= e(spx_status_label($o['spx_status_code'], $o['spx_status'])) ?><?= $o['spx_status_at'] ? ' · ' . e(date('d/m/Y H:i', strtotime($o['spx_status_at']))) : '' ?></dd>
        <?php elseif ($o['courier'] === 'jnt' && $o['jnt_status_code'] !== ''): ?>
          <dt>Status parcel</dt><dd><?= e(jnt_status_label($o['jnt_status_code'], $o['jnt_status'])) ?><?= $o['jnt_status_at'] ? ' · ' . e(date('d/m/Y H:i', strtotime($o['jnt_status_at']))) : '' ?></dd>
        <?php endif; ?></dl>
      <?php if ($turl): ?>
        <p><a class="btn btn-gold" target="_blank" rel="noopener" href="<?= e($turl) ?>">Jejak parcel di <?= e(courier_label((string)$o['courier'])) ?> ↗</a>
           <button type="button" class="btn btn-sm" id="copyTrack">Salin nombor</button></p>
        <?php if (!tracking_is_direct((string)$o['courier'])): ?>
          <p class="muted">Laman kurier ini perlu nombor ditaip sendiri — salin nombor di atas dan tampal di sana.</p>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>

    <table class="items">
      <tr><th>Produk</th><th>Jumlah</th></tr>
      <?php foreach ($items as $it): ?><tr><td><?= (int)$it['qty'] ?> × <?= e($it['name']) ?></td><td><?= e(money($it['line_total'])) ?></td></tr><?php endforeach; ?>
      <tr><td>Penghantaran (<?= e($o['state']) ?>)</td><td><?= (float)$o['shipping'] > 0 ? e(money($o['shipping'])) : 'PERCUMA' ?></td></tr>
      <tr><th>Jumlah</th><th><?= e(money($o['total'])) ?></th></tr>
    </table>
    <dl class="kv">
      <dt>Nama</dt><dd><?= e($o['customer_name']) ?></dd>
      <dt>Alamat</dt><dd><?= e($o['address']) ?>, <?= e($o['postcode']) ?> <?= e($o['city']) ?>, <?= e($o['state']) ?></dd>
      <dt>Bayaran</dt><dd><?= e(pay_label($o['payment_method'])) ?></dd>
      <dt>Tarikh</dt><dd><?= e(date('d/m/Y g:i A', strtotime($o['created_at']))) ?></dd>
    </dl>
    <p><a class="btn" target="_blank" href="<?= e(order_invoice_url($o)) ?>">🧾 <?= in_array($o['status'], ['paid', 'shipped', 'completed'], true) ? 'Resit rasmi' : 'Invois' ?> (boleh cetak / simpan PDF)</a></p>
    <?php if ($wa): ?><p><a class="btn btn-wa" target="_blank" rel="noopener" href="<?= e(wa_link($wa, $msg)) ?>">WhatsApp kami</a></p><?php endif; ?>
    <p class="muted">Simpan pautan halaman ini untuk semak status dan nombor tracking.</p>
  </div>
</main>
<script>
(function(){var b=document.getElementById('copyTrack'),n=document.getElementById('trackNo');
 if(!b||!n||!navigator.clipboard)return;
 b.addEventListener('click',function(){navigator.clipboard.writeText(n.textContent.trim()).then(function(){
   var t=b.textContent;b.textContent='Disalin ✓';setTimeout(function(){b.textContent=t;},1800);});});})();
</script>
<?php page_foot();
