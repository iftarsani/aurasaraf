<?php
/**
 * Invois / Resit Rasmi — dokumen boleh cetak untuk satu pesanan.
 * Pelanggan : invoice.php?no=AS...&t=<access_token>
 * Admin     : invoice.php?id=<order id>  (perlu sesi admin)
 *
 * Belum dibayar -> tajuk "INVOIS" + arahan bayaran.
 * Sudah dibayar -> tajuk "RESIT RASMI" + cop LUNAS + butiran bayaran.
 */
require __DIR__ . '/../aurasaraf_app/bootstrap.php';

$o = null;
if (isset($_GET['id']) && admin_current()) {
    $o = order_find((int)$_GET['id']);
} elseif (isset($_GET['no'])) {
    $found = order_find_by_no((string)$_GET['no']);
    if ($found && hash_equals($found['access_token'], (string)($_GET['t'] ?? ''))) $o = $found;
}
if (!$o) {
    http_response_code(404);
    exit('Dokumen tidak dijumpai. Pautan tidak sah atau telah tamat.');
}

$items = order_items((int)$o['id']);
$paid  = in_array($o['status'], ['paid', 'shipped', 'completed'], true);
$void  = $o['status'] === 'cancelled';
$title = $void ? 'INVOIS (DIBATALKAN)' : ($paid ? 'RESIT RASMI' : 'INVOIS');

$shopName = setting('shop_name', 'Minyak Aura Saraf');
$company  = setting('company', 'Warisan Maktok Empire');
$address  = trim((string)setting('shop_address'));
$shopMail = trim((string)setting('shop_email'));
$wa       = setting('whatsapp');
$ssm      = setting('ssm_no');
$bankOn   = setting('bank_acc') !== '';
?><!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title . ' ' . $o['order_no']) ?></title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#e9e7e0;font:14px/1.55 "Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#1c1c1c}
.bar{max-width:800px;margin:0 auto;padding:14px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.bar a,.bar button{font:inherit;font-weight:600;font-size:13px;border:0;border-radius:8px;padding:.55em 1.1em;cursor:pointer;text-decoration:none}
.bar .go{background:#173b2a;color:#fff}
.bar .alt{background:#fff;color:#173b2a;border:1px solid #cfcabb}
.bar .sp{margin-left:auto;font-size:12px;color:#6b6b6b}
.sheet{max-width:800px;margin:0 auto 40px;background:#fff;padding:42px 46px;box-shadow:0 2px 14px rgba(0,0,0,.09);position:relative}
.head{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap;border-bottom:3px solid #173b2a;padding-bottom:18px}
.brand{font-size:19px;font-weight:700;color:#173b2a;margin:0 0 4px}
.head .meta{font-size:12px;color:#5a5a5a;line-height:1.6;white-space:pre-line}
.doc{text-align:right}
.doc h1{margin:0;font-size:26px;letter-spacing:.06em;color:#173b2a}
.doc .ref{font-family:ui-monospace,Consolas,monospace;font-size:15px;font-weight:700;margin-top:5px}
.doc .dt{font-size:12px;color:#5a5a5a;margin-top:3px}
.cols{display:flex;gap:30px;flex-wrap:wrap;margin:26px 0 6px}
.cols>div{flex:1 1 240px;min-width:220px}
h2{font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#7a7a7a;margin:0 0 7px;font-weight:700}
.kv{margin:0;font-size:13px}
.kv div{display:flex;gap:8px;padding:1px 0}
.kv dt{color:#6b6b6b;min-width:98px}
.kv dd{margin:0;font-weight:600}
table{width:100%;border-collapse:collapse;margin-top:22px;font-size:13px}
thead th{background:#173b2a;color:#fff;text-align:left;padding:9px 11px;font-size:11px;letter-spacing:.07em;text-transform:uppercase}
tbody td{padding:10px 11px;border-bottom:1px solid #e6e3da;vertical-align:top}
.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.sku{font-family:ui-monospace,Consolas,monospace;font-size:11px;color:#8a8a8a;display:block}
tfoot td{padding:6px 11px;font-size:13px}
tfoot .grand td{border-top:2px solid #173b2a;font-size:17px;font-weight:700;color:#173b2a;padding-top:11px}
.note{margin-top:26px;padding:14px 16px;border:1px solid #e0dcd1;border-radius:9px;background:#faf8f3;font-size:12.5px}
.note b{display:block;margin-bottom:5px;color:#173b2a}
.note .mono{font-family:ui-monospace,Consolas,monospace;font-weight:700}
.stamp{position:absolute;top:150px;right:52px;border:4px solid #1c7a45;color:#1c7a45;font-size:34px;font-weight:800;letter-spacing:.1em;padding:6px 20px;border-radius:9px;transform:rotate(-13deg);opacity:.24;pointer-events:none}
.stamp.bad{border-color:#b3243b;color:#b3243b}
.foot{margin-top:30px;padding-top:14px;border-top:1px solid #e6e3da;font-size:11px;color:#777;line-height:1.7}
@media print{
  @page{size:A4;margin:13mm}
  body{background:#fff}
  .bar{display:none}
  .sheet{box-shadow:none;margin:0;padding:0;max-width:none}
  tr{break-inside:avoid}
  .note,.foot{break-inside:avoid}
}
</style>
</head>
<body>

<div class="bar">
  <button class="go" onclick="window.print()">Cetak / Simpan PDF</button>
  <?php if (admin_current()): ?>
    <a class="alt" href="<?= e(url('admin/order.php?id=' . $o['id'])) ?>">← Kembali ke pesanan</a>
  <?php else: ?>
    <a class="alt" href="<?= e(order_public_url($o)) ?>">← Status pesanan</a>
  <?php endif; ?>
  <span class="sp">Pilih “Simpan sebagai PDF” dalam kotak cetak untuk muat turun.</span>
</div>

<div class="sheet">
  <?php if ($paid): ?><div class="stamp">LUNAS</div>
  <?php elseif ($void): ?><div class="stamp bad">BATAL</div><?php endif; ?>

  <div class="head">
    <div>
      <p class="brand">🌿 <?= e($shopName) ?></p>
      <div class="meta"><?= e($company) ?><?= $ssm ? ' (' . e($ssm) . ')' : '' ?><?php
        if ($address !== '') echo "\n" . e($address);
        if ($wa !== '')      echo "\nTel / WhatsApp: " . e($wa);
        if ($shopMail !== '') echo "\n" . e($shopMail);
      ?></div>
    </div>
    <div class="doc">
      <h1><?= e($title) ?></h1>
      <p class="ref"><?= e($o['order_no']) ?></p>
      <p class="dt">Tarikh: <?= e(date('d/m/Y', strtotime($o['created_at']))) ?></p>
      <?php if ($paid && $o['paid_at']): ?><p class="dt">Dibayar: <?= e(date('d/m/Y g:i A', strtotime($o['paid_at']))) ?></p><?php endif; ?>
    </div>
  </div>

  <div class="cols">
    <div>
      <h2>Bil kepada</h2>
      <p style="margin:0;font-weight:700"><?= e($o['customer_name']) ?></p>
      <p style="margin:2px 0 0;font-size:13px;line-height:1.6"><?= e($o['address']) ?><br><?= e($o['postcode']) ?> <?= e($o['city']) ?><br><?= e($o['state']) ?></p>
      <p style="margin:6px 0 0;font-size:13px"><?= e($o['phone']) ?><?= $o['email'] ? '<br>' . e($o['email']) : '' ?></p>
    </div>
    <div>
      <h2>Butiran</h2>
      <dl class="kv">
        <div><dt>No. rujukan</dt><dd><?= e($o['order_no']) ?></dd></div>
        <div><dt>Cara bayaran</dt><dd><?= e(pay_label($o['payment_method'])) ?></dd></div>
        <div><dt>Status</dt><dd><?= e(status_label($o['status'])) ?></dd></div>
        <?php if ($o['payment_ref']): ?><div><dt>Rujukan bayaran</dt><dd><?= e($o['payment_ref']) ?></dd></div><?php endif; ?>
        <?php if ($o['tracking_no']): ?>
          <div><dt>Kurier</dt><dd><?= e(courier_label($o['courier'])) ?></dd></div>
          <div><dt>No. tracking</dt><dd><?= e($o['tracking_no']) ?></dd></div>
        <?php endif; ?>
      </dl>
    </div>
  </div>

  <table>
    <thead><tr><th>Perihal</th><th class="num">Harga</th><th class="num">Kuantiti</th><th class="num">Jumlah</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td><?= e($it['name']) ?><span class="sku"><?= e($it['sku']) ?></span></td>
        <td class="num"><?= e(money($it['price'])) ?></td>
        <td class="num"><?= (int)$it['qty'] ?></td>
        <td class="num"><?= e(money($it['line_total'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="3" class="num">Subtotal</td><td class="num"><?= e(money($o['subtotal'])) ?></td></tr>
      <tr><td colspan="3" class="num">Penghantaran (<?= e($o['state']) ?>)</td>
          <td class="num"><?= (float)$o['shipping'] > 0 ? e(money($o['shipping'])) : 'PERCUMA' ?></td></tr>
      <tr class="grand"><td colspan="3" class="num">JUMLAH<?= $paid ? ' DIBAYAR' : ' PERLU DIBAYAR' ?></td>
          <td class="num"><?= e(money($o['total'])) ?></td></tr>
    </tfoot>
  </table>

  <?php if (!$paid && !$void && $bankOn): ?>
  <div class="note">
    <b>Arahan bayaran</b>
    <?= e(setting('bank_name')) ?> · <span class="mono"><?= e(setting('bank_acc')) ?></span><?= setting('bank_holder') ? ' · ' . e(setting('bank_holder')) : '' ?><br>
    Sila nyatakan <span class="mono"><?= e($o['order_no']) ?></span> sebagai rujukan, dan hantar resit bayaran kepada kami melalui WhatsApp.
  </div>
  <?php elseif (!$paid && !$void): ?>
  <div class="note"><b>Bayaran belum diterima</b>Sila hubungi kami melalui WhatsApp<?= $wa ? ' di ' . e($wa) : '' ?> untuk urusan bayaran.</div>
  <?php endif; ?>

  <?php if ($o['note']): ?><div class="note"><b>Nota pelanggan</b><?= e($o['note']) ?></div><?php endif; ?>

  <div class="foot">
    Dokumen ini dijana oleh komputer dan sah tanpa tandatangan.<br>
    Kegunaan luaran sahaja. Produk ini bukan pengganti rawatan perubatan.<br>
    <?= e($company) ?><?= $ssm ? ' · SSM ' . e($ssm) : '' ?> · Dicetak <?= e(date('d/m/Y g:i A')) ?>
  </div>
</div>

</body>
</html>
