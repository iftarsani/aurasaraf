<?php
require __DIR__ . '/../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_partials.php';

// Halaman butiran satu produk. Produk yang tidak aktif dianggap tidak wujud.
$st = db()->prepare('SELECT * FROM products WHERE id = ? AND active = 1');
$st->execute([(int)($_GET['id'] ?? 0)]);
$p = $st->fetch();
$shopName = setting('shop_name', 'Minyak Aura Saraf');

if (!$p) {
    http_response_code(404);
    page_head('Produk tidak dijumpai — ' . $shopName); ?>
<main class="wrap narrow sec"><h1>Produk tidak dijumpai</h1><p class="lead">Produk ini tiada atau tidak lagi dijual.</p>
  <a class="btn" href="<?= e(url()) ?>">Kembali ke kedai</a></main>
<?php page_foot(); exit;
}

$out   = (int)$p['stock'] <= 0;
$steps = usage_steps($p['usage_steps']);
$paras = paragraphs($p['description']);
$order = url('?add=' . (int)$p['id']) . '#pesan';
$imgs  = product_images($p);

page_head($p['name'] . ($p['size'] !== '' ? ' ' . $p['size'] : '') . ' — ' . $shopName, $p['short_desc']);
?>
<header class="top">
  <div class="wrap top-in">
    <a class="logo" href="<?= e(url()) ?>"><img src="<?= e(asset('assets/logo-header.png')) ?>" alt="<?= e($shopName) ?>" width="429" height="120"></a>
    <nav><a href="<?= e(url()) ?>#produk">Semua produk</a><?php if (!$out): ?><a href="<?= e($order) ?>" class="btn btn-sm">Pesan</a><?php endif; ?></nav>
  </div>
</header>

<section class="sec pd">
  <div class="wrap pd-grid">
    <div class="pd-media">
      <?php if (count($imgs) > 1): ?>
      <div class="pd-art gal" data-gal aria-roledescription="slide" aria-label="Gambar <?= e($p['name']) ?>">
        <div class="gal-track" tabindex="0">
          <?php foreach ($imgs as $i => $img): ?><div class="gal-slide"><img src="<?= e(product_image_url($img)) ?>" alt="<?= e($p['name']) ?> — gambar <?= $i + 1 ?>"<?= $i ? ' loading="lazy"' : '' ?>></div><?php endforeach; ?>
        </div>
        <button type="button" class="gal-nav gal-prev" aria-label="Gambar sebelumnya">‹</button>
        <button type="button" class="gal-nav gal-next" aria-label="Gambar seterusnya">›</button>
        <div class="gal-dots"><?php foreach ($imgs as $i => $img): ?><button type="button" aria-label="Gambar <?= $i + 1 ?>"<?= $i ? '' : ' class="on"' ?>></button><?php endforeach; ?></div>
      </div>
      <div class="gal-thumbs"><?php foreach ($imgs as $i => $img): ?><button type="button" aria-label="Gambar <?= $i + 1 ?>"<?= $i ? '' : ' class="on"' ?>><img src="<?= e(product_image_url($img)) ?>" alt="" loading="lazy"></button><?php endforeach; ?></div>
      <?php else: ?>
      <div class="pd-art">
        <?php if ($imgs): ?><img src="<?= e(product_image_url($imgs[0])) ?>" alt="<?= e($p['name']) ?>">
        <?php else: echo bottle_svg(); endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <div>
      <h1 class="pd-name"><?= e($p['name']) ?><?php if ($p['size'] !== ''): ?> <small><?= e($p['size']) ?></small><?php endif; ?></h1>
      <?php if ($p['short_desc'] !== ''): ?><p class="lead"><?= e($p['short_desc']) ?></p><?php endif; ?>
      <div class="pd-price"><?= e(money($p['price'])) ?>
        <?php if ((float)$p['compare_price'] > (float)$p['price']): ?><s><?= e(money($p['compare_price'])) ?></s><?php endif; ?>
        <?php if ($out): ?><span class="tag tag-out">Habis stok</span>
        <?php elseif ((int)$p['stock'] <= (int)$p['low_stock']): ?><span class="tag tag-low">Tinggal <?= (int)$p['stock'] ?></span>
        <?php else: ?><span class="tag">Ada stok</span><?php endif; ?>
      </div>
      <?php if (!$out): ?><a class="btn btn-lg" href="<?= e($order) ?>">Pesan Sekarang</a><?php endif; ?>

      <?php if ($paras): ?>
      <h2 class="pd-h">Penerangan produk</h2>
      <?php foreach ($paras as $para): ?><p><?= nl2br(e($para)) ?></p><?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($steps): ?>
<section class="sec sec-paper">
  <div class="wrap">
    <h2>Cara penggunaan</h2>
    <ol class="steps">
      <?php foreach ($steps as [$tajuk, $teks]): ?><li><?php if ($tajuk !== ''): ?><b><?= e($tajuk) ?></b> <?php endif; ?><?= e($teks) ?></li><?php endforeach; ?>
    </ol>
  </div>
</section>
<?php endif; ?>

<?php if (trim((string)$p['warning']) !== ''): ?>
<section class="warn">
  <div class="wrap warn-in">
    <strong>AMARAN</strong>
    <p><?= nl2br(e(trim($p['warning']))) ?></p>
  </div>
</section>
<?php endif; ?>

<?php if (!$out): ?><a href="<?= e($order) ?>" class="sticky-cta">Pesan Sekarang · <?= e(money($p['price'])) ?></a><?php endif; ?>
<?php if (count($imgs) > 1): ?><script src="<?= e(asset('assets/gallery.js')) ?>" defer></script><?php endif; ?>
<?php page_foot();
