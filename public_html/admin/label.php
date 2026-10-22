<?php
/**
 * Label penghantaran — dokumen boleh cetak untuk ditampal pada bungkusan.
 *
 * admin/label.php?id=12            satu pesanan
 * admin/label.php?ids=12,13,14     beberapa pesanan sekali cetak
 * &size=a6 (lalai, sticker 100×150mm) atau &size=a4 (4 label satu helaian)
 *
 * Admin sahaja — label mengandungi alamat dan telefon pelanggan.
 *
 * PENTING: ini BUKAN consignment note rasmi kurier. Nombor tracking sebenar
 * (dan kod bar yang boleh diimbas sistem kurier) hanya boleh dikeluarkan oleh
 * kurier itu sendiri. Label ini ialah label alamat kedai: kod bar padanya
 * mengekod nombor tracking yang admin kunci masuk, atau nombor pesanan jika
 * tracking belum ada. Consignment note kurier tetap perlu ditampal berasingan.
 */
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require_admin();

// Terima tiga bentuk: id=4 (butang pada halaman pesanan), ids=4,7 (pautan suis
// saiz), dan id[]=4&id[]=7 (checkbox borang GET pada senarai pesanan).
$raw = $_GET['id'] ?? $_GET['ids'] ?? [];
if (is_string($raw)) $raw = explode(',', $raw);

$ids = [];
foreach ((array)$raw as $v) if (is_scalar($v) && (int)$v > 0) $ids[] = (int)$v;
$ids = array_slice(array_values(array_unique($ids)), 0, 100);

$orders = [];
foreach ($ids as $id) if ($o = order_find($id)) $orders[] = $o;

if (!$orders) {
    http_response_code($ids ? 404 : 400);
    exit($ids
        ? 'Pesanan tidak dijumpai.'
        : 'Tiada pesanan dipilih. Kembali ke senarai pesanan, tanda kotak di sebelah kiri, kemudian tekan cetak.');
}

$size = ($_GET['size'] ?? '') === 'a4' ? 'a4' : 'a6';

// Pautan suis saiz: bina semula daripada id/ids sahaja. Nota: array_merge(),
// bukan $_GET + [...] — operator + mengekalkan nilai kiri, jadi 'size' sedia
// ada dalam URL tidak akan bertukar dan suis itu mati selepas klik pertama.
$found   = array_column($orders, 'id');
$selfUrl = static function (string $s) use ($found): string {
    return '?' . http_build_query(count($found) === 1
        ? ['id' => $found[0], 'size' => $s]
        : ['ids' => implode(',', $found), 'size' => $s]);
};

$shopName = setting('shop_name', 'Minyak Aura Saraf');
$company  = setting('company', 'Warisan Maktok Empire');
$shopAddr = trim((string)setting('shop_address'));
$shopWa   = setting('whatsapp');
$backUrl  = count($orders) === 1 ? url('admin/order.php?id=' . $orders[0]['id']) : url('admin/orders.php');
?><!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e('Label ' . (count($orders) === 1 ? $orders[0]['order_no'] : count($orders) . ' pesanan')) ?></title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#e9e7e0;font:12px/1.4 "Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#000}
.bar{max-width:920px;margin:0 auto;padding:14px 16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.bar a,.bar button{font:inherit;font-weight:600;font-size:13px;border:0;border-radius:8px;padding:.55em 1.1em;cursor:pointer;text-decoration:none}
.bar .go{background:#173b2a;color:#fff}
.bar .alt{background:#fff;color:#173b2a;border:1px solid #cfcabb}
.bar .alt.on{background:#173b2a;color:#fff;border-color:#173b2a}
.bar .hint{font-size:12px;color:#6b6b6b}
.warn{max-width:920px;margin:0 auto 6px;padding:10px 16px;background:#fff4d6;border:1px solid #e3cf8e;border-radius:9px;font-size:12.5px;color:#6b4e00}
.warn a{color:#6b4e00}
.labels{padding:6px 0 40px}
/* Satu .sheet = satu helaian cetakan. Label dikumpul dalam PHP (1 untuk sticker,
   4 untuk A4) dan bukan dibiarkan pembrowser memecahkannya sendiri — pemisahan
   halaman automatik dalam grid/flex tidak boleh dipercayai semasa cetak. */
.sheet{display:flex;flex-wrap:wrap;justify-content:center;align-content:flex-start;gap:16px;margin:0 auto 16px}

/* Satu label = 100mm × 150mm, saiz sticker termal biasa. */
.lbl{width:100mm;height:150mm;background:#fff;padding:4mm;display:flex;flex-direction:column;
     border:1px solid #000;box-shadow:0 2px 12px rgba(0,0,0,.12);overflow:hidden}
.top{display:flex;justify-content:space-between;align-items:flex-start;gap:6px;border-bottom:2px solid #000;padding-bottom:2mm}
.shop{font-size:12px;font-weight:800;line-height:1.25}
.shop small{display:block;font-size:8.5px;font-weight:600;color:#444}
.cour{text-align:right;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.03em;max-width:38mm;line-height:1.2}
.cour small{display:block;font-size:8px;font-weight:600;color:#444;letter-spacing:0}

.code{text-align:center;padding:2.5mm 0 1mm;border-bottom:1px solid #000}
.code svg{display:block;margin:0 auto;max-width:100%}
.code .no{font:700 13px/1.2 ui-monospace,Consolas,"Courier New",monospace;letter-spacing:.08em;margin-top:.8mm}
.code .cap{font-size:8px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#555}
.code .none{font:800 15px/1.3 ui-monospace,Consolas,monospace;padding:3mm 0}

.box{border-bottom:1px solid #000;padding:1.8mm 0;display:flex;gap:3mm}
.box .fill{flex:1;min-width:0}
h3{margin:0 0 .6mm;font-size:8px;letter-spacing:.14em;text-transform:uppercase;color:#555;font-weight:800}
.from{font-size:9.5px;line-height:1.35}
.from b{font-size:10.5px}
.qty{width:17mm;border:2px solid #000;border-radius:2mm;text-align:center;padding:1mm .5mm;flex:none}
.qty b{display:block;font-size:19px;font-weight:800;line-height:1}
.qty span{font-size:7.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em}

.to{flex:1;padding:2.2mm 0;border-bottom:1px solid #000;min-height:0}
.to .nm{font-size:15px;font-weight:800;line-height:1.2;text-transform:uppercase}
.to .ad{font-size:12.5px;line-height:1.45;margin-top:1mm;word-wrap:break-word}
.to .ph{font:700 14px/1.2 ui-monospace,Consolas,monospace;margin-top:1.4mm}

.goods{font-size:9.5px;line-height:1.35;padding:1.6mm 0;border-bottom:1px solid #000}
.goods .it{display:flex;justify-content:space-between;gap:4mm}
.note{font-size:9px;line-height:1.3;margin-top:.8mm}
.note b{font-weight:800}

.foot{display:flex;justify-content:space-between;align-items:flex-end;gap:3mm;padding-top:1.6mm}
.foot .meta{font-size:8.5px;line-height:1.45;color:#333}
.foot .meta b{color:#000}
.pay{border:2.5px solid #000;border-radius:2mm;padding:1mm 2.5mm;text-align:center;flex:none}
.pay b{display:block;font-size:15px;font-weight:800;line-height:1.1;letter-spacing:.02em}
.pay span{font-size:7.5px;font-weight:700;letter-spacing:.06em}
.pay.due{border-color:#b3243b;color:#b3243b}

@media print{
  @page{size:<?= $size === 'a4' ? 'A4' : '100mm 150mm' ?>;margin:<?= $size === 'a4' ? '5mm' : '0' ?>}
  body{background:#fff}
  .bar,.warn{display:none}
  .labels{padding:0}
  .sheet{gap:0;margin:0;break-after:page}
  .sheet:last-child{break-after:auto}
  .lbl{box-shadow:none;break-inside:avoid;<?= $size === 'a4'
        ? 'width:95mm;height:136mm;border:1px dashed #999'
        : 'border:0' ?>}
}
</style>
</head>
<body>

<div class="bar">
  <button class="go" onclick="window.print()">Cetak <?= count($orders) > 1 ? count($orders) . ' label' : 'label' ?></button>
  <a class="alt <?= $size === 'a6' ? 'on' : '' ?>" href="<?= e($selfUrl('a6')) ?>">Sticker 100×150mm</a>
  <a class="alt <?= $size === 'a4' ? 'on' : '' ?>" href="<?= e($selfUrl('a4')) ?>">A4 (4 label)</a>
  <a class="alt" href="<?= e($backUrl) ?>">← Kembali</a>
  <span class="hint">Dalam kotak cetak, pastikan skala <b>100%</b> dan margin “None”.</span>
</div>
<?php if ($shopAddr === ''): ?>
  <div class="warn">⚠ Alamat kedai belum diisi — label akan dicetak tanpa alamat penghantar (alamat pemulangan).
    Isi di <a href="<?= e(url('admin/settings.php')) ?>">Tetapan → Alamat kedai</a>.</div>
<?php endif; ?>

<div class="labels">
<?php foreach (array_chunk($orders, $size === 'a4' ? 4 : 1) as $sheet): ?>
<div class="sheet">
<?php foreach ($sheet as $o):
    $items   = order_items((int)$o['id']);
    $units   = 0; foreach ($items as $it) $units += (int)$it['qty'];
    $paid    = in_array($o['status'], ['paid', 'shipped', 'completed'], true);
    $track   = trim((string)$o['tracking_no']);
    $barData = $track !== '' ? $track : $o['order_no'];
    $svg     = barcode128_svg($barData, 13.0, $track !== '' ? 0.30 : 0.36);
?>
  <div class="lbl">
    <div class="top">
      <div class="shop">🌿 <?= e($shopName) ?><small><?= e($company) ?></small></div>
      <div class="cour"><?= $o['courier'] !== '' ? e(courier_label($o['courier'])) : 'KURIER' ?>
        <small><?= $track !== '' ? 'No. tracking' : 'Tracking belum dikunci' ?></small></div>
    </div>

    <div class="code">
      <?php if ($svg !== ''): ?>
        <?= $svg ?>
        <div class="no"><?= e($barData) ?></div>
        <div class="cap"><?= $track !== '' ? e(courier_label($o['courier']) ?: 'Kurier') : 'No. pesanan' ?></div>
      <?php else: ?>
        <div class="none"><?= e($barData) ?></div>
      <?php endif; ?>
      <?php if ($track !== ''): ?><div class="cap" style="margin-top:.6mm">Pesanan <?= e($o['order_no']) ?></div><?php endif; ?>
    </div>

    <div class="box">
      <div class="fill">
        <h3>Penghantar (from)</h3>
        <div class="from"><b><?= e($shopName) ?></b><?= $shopAddr !== '' ? '<br>' . nl2br(e($shopAddr)) : '' ?><?= $shopWa !== '' ? '<br>' . e($shopWa) : '' ?></div>
      </div>
      <div class="qty"><b><?= $units ?></b><span>unit</span></div>
    </div>

    <div class="to">
      <h3>Penerima (to)</h3>
      <div class="nm"><?= e($o['customer_name']) ?></div>
      <div class="ad"><?= e($o['address']) ?><br><?= e($o['postcode']) ?> <?= e($o['city']) ?><br><?= e($o['state']) ?></div>
      <div class="ph"><?= e($o['phone']) ?></div>
    </div>

    <div class="goods">
      <h3>Kandungan</h3>
      <?php foreach ($items as $it): ?>
        <div class="it"><span><?= (int)$it['qty'] ?> × <?= e($it['name']) ?></span><span><?= e($it['sku']) ?></span></div>
      <?php endforeach; ?>
      <?php if ($o['note'] !== ''): ?><div class="note"><b>Nota pelanggan:</b> <?= e(mb_substr($o['note'], 0, 120)) ?></div><?php endif; ?>
      <?php if ($o['admin_note'] !== ''): ?><div class="note"><b>Nota dalaman:</b> <?= e(mb_substr($o['admin_note'], 0, 120)) ?></div><?php endif; ?>
    </div>

    <div class="foot">
      <div class="meta">
        Pesanan: <b><?= e($o['order_no']) ?></b><br>
        Tarikh: <b><?= e(date('d/m/Y', strtotime($o['created_at']))) ?></b><br>
        Jumlah: <b><?= e(money($o['total'])) ?></b> · <?= e(pay_label($o['payment_method'])) ?>
      </div>
      <div class="pay <?= $paid ? '' : 'due' ?>">
        <b><?= $paid ? 'SUDAH BAYAR' : 'BELUM BAYAR' ?></b>
        <span><?= $paid ? 'Prabayar · jangan kutip tunai' : 'Semak sebelum hantar' ?></span>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
</div>

</body>
</html>
