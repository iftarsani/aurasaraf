<?php
require __DIR__ . '/../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_partials.php';

$products = db()->query('SELECT * FROM products WHERE active = 1 ORDER BY sort, id')->fetchAll();
$hero     = $products[0] ?? null;
$payM     = payment_methods();   // FPX sahaja bila Billplz aktif — lihat payment_methods()
$bankOn   = in_array('transfer', $payM, true);
$old      = $_SESSION['old'] ?? [];  unset($_SESSION['old']);
$errors   = $_SESSION['errors'] ?? []; unset($_SESSION['errors']);
$shopName = setting('shop_name', 'Minyak Aura Saraf');
$add      = (int)($_GET['add'] ?? 0);   // dari butang "Pesan" di product.php — isi kuantiti 1
$heroSteps = $hero ? usage_steps($hero['usage_steps']) : [];
$heroWarn  = $hero ? trim((string)$hero['warning']) : '';

page_head($shopName . ' — minyak urut tradisional 30ml', 'Minyak urut tradisional dengan minyak kelapa, minyak bidara dan akar herba. Pesan terus dalam talian, bayar dengan FPX.');
?>
<header class="top">
  <div class="wrap top-in">
    <a class="logo" href="<?= e(url()) ?>"><img src="<?= e(asset('assets/logo-header.png')) ?>" alt="<?= e($shopName) ?>" width="429" height="120"></a>
    <nav><a href="#produk">Produk</a><a href="#sesuai">Sesuai untuk</a><a href="#cara">Cara guna</a><a href="#pesan" class="btn btn-sm">Pesan</a></nav>
  </div>
</header>

<section class="hero">
  <div class="wrap hero-grid">
    <div class="hero-copy">
      <p class="eyebrow"><?= e(setting('company', 'Warisan Maktok Empire')) ?></p>
      <h1>Urut sikit,<br><em>lega</em> satu badan.</h1>
      <p class="lead">Minyak urut tradisional diperkaya <b>minyak kelapa</b>, <b>minyak bidara</b> dan <b>akar herba</b>. Untuk belikat, pinggang, bahu dan lutut yang lenguh selepas seharian bekerja.</p>
      <div class="hero-cta">
        <a href="#pesan" class="btn btn-lg">Pesan Sekarang<?= $hero ? ' — ' . e(money($hero['price'])) : '' ?></a>
        <?php if ($hero): ?><a class="hero-meta" href="<?= e(product_url($hero)) ?>">Lihat butiran produk →</a><?php endif; ?>
      </div>
      <ul class="ticks"><li>Bahan semula jadi</li><li>Herba terpilih</li><li>Buatan Malaysia</li></ul>
    </div>
    <div class="hero-art">
      <div class="sun"></div>
      <?php $heroImgs = $hero ? product_images($hero) : []; ?>
      <?php if (count($heroImgs) > 1): ?>
        <div class="hero-slides" data-fade>
          <?php foreach ($heroImgs as $i => $img): ?><img src="<?= e(product_image_url($img)) ?>" alt="<?= $i ? '' : e($hero['name']) ?>"<?= $i ? '' : ' class="on"' ?>><?php endforeach; ?>
        </div>
      <?php elseif ($heroImgs): ?><img class="hero-img" src="<?= e(product_image_url($heroImgs[0])) ?>" alt="<?= e($hero['name']) ?>">
      <?php else: echo bottle_svg(); endif; ?>
    </div>
  </div>
</section>

<?php /* Setiap produk aktif dipapar di sini secara automatik — tambah produk
         di Admin → Produk dan tanda "Papar di kedai". */
if ($products): ?>
<section id="produk" class="sec sec-paper">
  <div class="wrap">
    <h2>Produk kami</h2>
    <div class="pgrid">
      <?php foreach ($products as $p): $out = (int)$p['stock'] <= 0; ?>
        <a class="pcard<?= $out ? ' is-out' : '' ?>" href="<?= e(product_url($p)) ?>">
          <div class="pcard-img"><?php if ($p['image']): ?><img src="<?= e(product_image_url($p['image'])) ?>" alt="" loading="lazy"><?php else: echo bottle_svg(); endif; ?></div>
          <b><?= e($p['name']) ?> <small><?= e($p['size']) ?></small></b>
          <?php if ($p['short_desc'] !== ''): ?><p><?= e($p['short_desc']) ?></p><?php endif; ?>
          <span class="pcard-price"><?= e(money($p['price'])) ?>
            <?php if ((float)$p['compare_price'] > (float)$p['price']): ?><s><?= e(money($p['compare_price'])) ?></s><?php endif; ?>
            <?php if ($out): ?><span class="tag tag-out">Habis stok</span><?php endif; ?></span>
          <span class="pcard-more">Lihat butiran →</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section id="sesuai" class="sec">
  <div class="wrap">
    <h2>Sesuai untuk siapa?</h2>
    <div class="cards4">
      <article><span>01</span><h3>Lenguh urat &amp; sendi</h3><p>Belikat, pinggang dan bahu yang tegang. Sapu dan urut perlahan.</p></article>
      <article><span>02</span><h3>Orang kerja berat</h3><p>Kontraktor, pekerja kilang, pemandu dan yang aktif bersukan.</p></article>
      <article><span>03</span><h3>Mak ayah &amp; warga emas</h3><p>Teman urutan harian untuk lutut dan sendi yang cepat penat.</p></article>
      <article><span>04</span><h3>Suri rumah &amp; ibu menyusu</h3><p>Badan lenguh seharian menguruskan rumah dan anak.</p></article>
    </div>
  </div>
</section>

<section class="sec sec-green">
  <div class="wrap two">
    <div>
      <h2>Tiga bahan, satu botol.</h2>
      <p class="lead light">Resipi warisan yang ringkas. Tiada yang pelik-pelik.</p>
    </div>
    <ul class="ingredients">
      <li><b>Minyak kelapa</b><span>Asas minyak urut yang licin dan mudah meresap.</span></li>
      <li><b>Minyak bidara</b><span>Daun bidara, bahan tradisi turun-temurun.</span></li>
      <li><b>Akar herba</b><span>Campuran akar kayu terpilih, haruman herba yang menenangkan.</span></li>
    </ul>
  </div>
</section>

<section id="cara" class="sec">
  <div class="wrap">
    <h2>Cara penggunaan</h2>
    <p class="lead">Sapukan di bahagian yang lenguh dan urut perlahan.</p>
    <ol class="steps">
      <?php if ($heroSteps): /* Diambil daripada produk utama (Admin → Produk → Cara penggunaan). */ ?>
      <?php foreach ($heroSteps as [$tajuk, $teks]): ?><li><?php if ($tajuk !== ''): ?><b><?= e($tajuk) ?></b> <?php endif; ?><?= e($teks) ?></li><?php endforeach; ?>
      <?php else: ?>
      <li><b>Badan lenguh</b> Sapu terus pada bahagian yang lenguh.</li>
      <li><b>Lutut</b> Sapu di bawah pelipat lutut dan juga di atas kepala lutut.</li>
      <li><b>Leher &amp; bahu</b> Sapu dari belakang telinga, turun ke leher dan terus tarik hingga ke bahu. Sesuai juga sebagai urutan santai sebelum tidur.</li>
      <li><b>Kepala</b> Titiskan 3–4 titik dan ratakan sambil urut perlahan.</li>
      <li><b>Tangan</b> Sapu di urat besar kiri dan kanan tangan, dari bawah ketiak hingga ke pergelangan tangan.</li>
      <li><b>Kaki</b> Sapu di urat besar bermula dari atas pinggang, sebelah kiri dan kanan, turun ke kaki.</li>
      <?php endif; ?>
    </ol>
  </div>
</section>

<?php
/* Testimoni terbit hanya apabila suis testimonials_show dihidupkan dalam Tetapan
   — lihat testimonials_enabled(). Senarai kosong turut menyembunyikan
   keseluruhan bahagian ini. */
if (testimonials_enabled() && ($testimoni = testimonials_list())): ?>
<section id="testimoni" class="sec sec-paper">
  <div class="wrap">
    <h2>Apa kata pelanggan</h2>
    <div class="tgrid">
      <?php foreach ($testimoni as $t): ?>
        <figure class="tcard">
          <?php if ($t['kind'] === 'image'): ?>
            <img src="<?= e(testimonial_image_url($t['image'])) ?>" alt="<?= e($t['author'] !== '' ? 'Testimoni daripada ' . $t['author'] : 'Testimoni pelanggan') ?>" loading="lazy">
          <?php else: ?>
            <div class="tvid">
              <iframe src="https://www.youtube-nocookie.com/embed/<?= e($t['youtube_id']) ?>"
                title="<?= e($t['author'] !== '' ? 'Testimoni daripada ' . $t['author'] : 'Video testimoni pelanggan') ?>"
                loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"
                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
            </div>
          <?php endif; ?>
          <?php if ($t['caption'] !== '' || $t['author'] !== ''): ?>
            <figcaption>
              <?php if ($t['caption'] !== ''): ?><p><?= e($t['caption']) ?></p><?php endif; ?>
              <?php if ($t['author'] !== ''): ?><cite><?= e($t['author']) ?></cite><?php endif; ?>
            </figcaption>
          <?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
    <?php if (trim((string)setting('kkliu_no')) !== ''): ?>
      <p class="muted tkkliu">KKLIU <?= e((string)setting('kkliu_no')) ?></p>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="warn">
  <div class="wrap warn-in">
    <strong>PENTING</strong>
    <?php if ($heroWarn !== ''): ?><p><?= nl2br(e($heroWarn)) ?></p>
    <?php else: ?><p><b>Tidak dibenarkan</b> untuk kanak-kanak bawah <b>12 tahun</b> dan <b>ibu mengandung</b>. Kegunaan luaran sahaja. Elakkan mata dan kulit luka.</p><?php endif; ?>
  </div>
</section>

<section id="pesan" class="sec sec-paper">
  <div class="wrap">
    <h2>Pesan sekarang</h2>
    <?php if ($errors): ?><div class="alert"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div><?php endif; ?>
    <?php if (!$products): ?><p class="lead">Produk belum tersedia. Sila hubungi kami melalui WhatsApp.</p><?php else: ?>

    <form id="orderForm" class="order" method="post" action="<?= e(url('checkout.php')) ?>" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">

      <div class="order-left">
        <h3 class="step-h"><i>1</i> Pilih produk</h3>
        <div class="plist">
        <?php foreach ($products as $p): $out = (int)$p['stock'] <= 0; $oldQty = (int)($old['items'][$p['id']] ?? ($add === (int)$p['id'] ? 1 : 0)); ?>
          <div class="pitem<?= $out ? ' is-out' : '' ?>" data-id="<?= (int)$p['id'] ?>" data-price="<?= e($p['price']) ?>" data-stock="<?= max(0, (int)$p['stock']) ?>" data-name="<?= e($p['name']) ?>">
            <div class="pthumb"><?php if ($p['image']): ?><img src="<?= e(product_image_url($p['image'])) ?>" alt="" loading="lazy"><?php else: echo bottle_svg(); endif; ?></div>
            <div class="pinfo">
              <b><a href="<?= e(product_url($p)) ?>"><?= e($p['name']) ?></a> <small><?= e($p['size']) ?></small></b>
              <p><?= e($p['short_desc']) ?></p>
              <div class="pprice"><?= e(money($p['price'])) ?>
                <?php if ((float)$p['compare_price'] > (float)$p['price']): ?><s><?= e(money($p['compare_price'])) ?></s><?php endif; ?>
                <?php if ($out): ?><span class="tag tag-out">Habis stok</span>
                <?php elseif ((int)$p['stock'] <= (int)$p['low_stock']): ?><span class="tag tag-low">Tinggal <?= (int)$p['stock'] ?></span>
                <?php else: ?><span class="tag">Ada stok</span><?php endif; ?>
              </div>
            </div>
            <div class="qty">
              <button type="button" class="qminus" aria-label="Kurang" <?= $out ? 'disabled' : '' ?>>−</button>
              <input type="number" name="items[<?= (int)$p['id'] ?>]" value="<?= $out ? 0 : $oldQty ?>" min="0" max="<?= max(0, (int)$p['stock']) ?>" inputmode="numeric" <?= $out ? 'disabled' : '' ?>>
              <button type="button" class="qplus" aria-label="Tambah" <?= $out ? 'disabled' : '' ?>>+</button>
            </div>
          </div>
        <?php endforeach; ?>
        </div>

        <h3 class="step-h"><i>2</i> Maklumat penghantaran</h3>
        <div class="fgrid">
          <label>Nama penuh<input name="customer_name" required maxlength="120" value="<?= e($old['customer_name'] ?? '') ?>" autocomplete="name"></label>
          <label>No. telefon (WhatsApp)<input name="phone" required maxlength="20" inputmode="tel" placeholder="0123456789" value="<?= e($old['phone'] ?? '') ?>" autocomplete="tel"></label>
          <label class="full">E-mel <small>(pilihan, untuk resit)</small><input type="email" name="email" maxlength="150" value="<?= e($old['email'] ?? '') ?>" autocomplete="email"></label>
          <label class="full">Alamat<textarea name="address" required maxlength="255" rows="2" autocomplete="street-address"><?= e($old['address'] ?? '') ?></textarea></label>
          <label>Poskod<input name="postcode" required maxlength="5" pattern="\d{5}" inputmode="numeric" value="<?= e($old['postcode'] ?? '') ?>" autocomplete="postal-code"></label>
          <label>Bandar<input name="city" required maxlength="80" value="<?= e($old['city'] ?? '') ?>" autocomplete="address-level2"></label>
          <label class="full">Negeri<select name="state" id="state" required><option value="">Pilih negeri</option>
            <?php foreach (states() as $s => $r): ?><option value="<?= e($s) ?>" data-region="<?= $r ?>" <?= ($old['state'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></label>
          <label class="full">Nota <small>(pilihan)</small><input name="note" maxlength="500" value="<?= e($old['note'] ?? '') ?>"></label>
        </div>

        <h3 class="step-h"><i>3</i> Cara bayaran</h3>
        <div class="pay">
          <?php $pm = in_array($old['payment_method'] ?? '', $payM, true) ? $old['payment_method'] : $payM[0]; ?>
          <?php if (in_array('fpx', $payM, true)): ?><label class="payopt"><input type="radio" name="payment_method" value="fpx" <?= $pm === 'fpx' ? 'checked' : '' ?>><span><b>FPX Online Banking</b><small>Bayar terus, pengesahan automatik</small></span></label><?php endif; ?>
          <?php if ($bankOn): ?><label class="payopt"><input type="radio" name="payment_method" value="transfer" <?= $pm === 'transfer' ? 'checked' : '' ?>><span><b>Transfer bank / DuitNow QR</b><small>Muat naik resit bayaran</small></span></label><?php endif; ?>
          <?php if (in_array('whatsapp', $payM, true)): ?><label class="payopt"><input type="radio" name="payment_method" value="whatsapp" <?= $pm === 'whatsapp' ? 'checked' : '' ?>><span><b>Urus melalui WhatsApp</b><small>Kami hubungi anda untuk bayaran</small></span></label><?php endif; ?>
        </div>

        <?php if ($bankOn): ?>
        <div id="transferBox" class="transfer" hidden>
          <div>
            <?php if (setting('bank_acc')): ?><p><small>Bank</small><b><?= e(setting('bank_name')) ?></b></p>
            <p><small>No. akaun</small><b class="mono"><?= e(setting('bank_acc')) ?></b></p>
            <p><small>Nama</small><b><?= e(setting('bank_holder')) ?></b></p><?php endif; ?>
            <p><small>Jumlah</small><b class="js-total mono">RM0.00</b></p>
          </div>
          <?php if (setting('qr_image')): ?><img src="<?= e(url('uploads/' . rawurlencode(setting('qr_image')))) ?>" alt="DuitNow QR" class="qr"><?php endif; ?>
          <label class="full">Resit bayaran <small>(JPG, PNG atau PDF, maks 5MB)</small><input type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
        </div>
        <?php endif; ?>
      </div>

      <aside class="summary">
        <h3>Ringkasan</h3>
        <div id="sumItems" class="sum-items"><p class="muted">Belum ada produk dipilih.</p></div>
        <dl>
          <div><dt>Subtotal</dt><dd id="sumSub">RM0.00</dd></div>
          <div><dt>Penghantaran</dt><dd id="sumShip">Pilih negeri</dd></div>
          <div class="grand"><dt>Jumlah</dt><dd id="sumTotal">RM0.00</dd></div>
        </dl>
        <button type="submit" class="btn btn-lg btn-block" id="submitBtn">Hantar Pesanan</button>
        <p class="muted center">Dengan menghantar, anda bersetuju maklumat ini digunakan untuk memproses pesanan.</p>
      </aside>
    </form>
    <?php endif; ?>
  </div>
</section>

<section class="sec">
  <div class="wrap narrow">
    <h2>Soalan lazim</h2>
    <details><summary>Berapa lama penghantaran?</summary><p>Pesanan diproses dalam 1–2 hari bekerja selepas bayaran disahkan. Nombor tracking boleh disemak di halaman status pesanan.</p></details>
    <?php if ($rc = rate_courier()): ?>
    <details><summary>Berapa kos pos?</summary><p>Kos pos dikira ikut kadar <?= e(courier_label($rc)) ?> mengikut poskod dan berat pesanan — jumlahnya dipapar dalam ringkasan sebelum anda hantar pesanan<?php if ((float)setting('free_min', 0) > 0): ?>. Percuma untuk pembelian <?= e(money(setting('free_min'))) ?> ke atas<?php endif; ?>.</p></details>
    <?php else: ?>
    <details><summary>Berapa kos pos?</summary><p>Semenanjung <?= e(money(setting('ship_sm', 8))) ?>, Sabah / Sarawak / Labuan <?= e(money(setting('ship_ss', 12))) ?><?php if ((float)setting('free_min', 0) > 0): ?>. Percuma untuk pembelian <?= e(money(setting('free_min'))) ?> ke atas<?php endif; ?>.</p></details>
    <?php endif; ?>
    <details><summary>Boleh guna untuk kanak-kanak atau ibu mengandung?</summary><p>Tidak. Produk ini tidak dibenarkan untuk kanak-kanak bawah 12 tahun dan ibu mengandung.</p></details>
  </div>
</section>

<a href="#pesan" class="sticky-cta">Pesan Sekarang<?= $hero ? ' · ' . e(money($hero['price'])) : '' ?></a>

<script>window.SHOP = <?= json_encode(['shipSM' => (float)setting('ship_sm', 8), 'shipSS' => (float)setting('ship_ss', 12), 'freeMin' => (float)setting('free_min', 0),
    'liveRates' => rate_courier() !== '', 'quoteUrl' => url('shipping-quote.php')], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="<?= e(asset('assets/shop.js')) ?>" defer></script>
<?php if (count($heroImgs) > 1): ?><script src="<?= e(asset('assets/gallery.js')) ?>" defer></script><?php endif; ?>
<?php page_foot();
