<?php
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_layout.php';
$admin = require_admin();
$id = (int)($_GET['id'] ?? 0);
$p = ['id' => 0, 'sku' => '', 'name' => '', 'size' => '', 'short_desc' => '', 'description' => '', 'usage_steps' => '', 'warning' => '', 'price' => '', 'compare_price' => '',
      'image' => '', 'stock' => 0, 'weight_g' => 100, 'low_stock' => 10, 'active' => 1, 'sort' => 0];
if ($id) {
    $st = db()->prepare('SELECT * FROM products WHERE id = ?'); $st->execute([$id]);
    $p = $st->fetch() ?: null;
    if (!$p) { flash('error', 'Produk tidak dijumpai.'); redirect('admin/products.php'); }
}
$errors = [];
if (is_post()) {
    csrf_check();
    $d = [
        'sku'  => strtoupper(mb_substr(trim((string)($_POST['sku'] ?? '')), 0, 40)),
        'name' => mb_substr(trim((string)($_POST['name'] ?? '')), 0, 150),
        'size' => mb_substr(trim((string)($_POST['size'] ?? '')), 0, 40),
        'short_desc'  => mb_substr(trim((string)($_POST['short_desc'] ?? '')), 0, 255),
        'description' => trim((string)($_POST['description'] ?? '')),
        'usage_steps' => trim((string)($_POST['usage_steps'] ?? '')),
        'warning'     => trim((string)($_POST['warning'] ?? '')),
        'price'         => round((float)($_POST['price'] ?? 0), 2),
        'compare_price' => round((float)($_POST['compare_price'] ?? 0), 2),
        'low_stock' => max(0, (int)($_POST['low_stock'] ?? 0)),
        'weight_g'  => max(1, min(30000, (int)($_POST['weight_g'] ?? 100))),
        'sort'      => (int)($_POST['sort'] ?? 0),
        'active'    => isset($_POST['active']) ? 1 : 0,
    ];
    if (!preg_match('/^[A-Z0-9._-]{2,40}$/', $d['sku'])) $errors[] = 'SKU 2–40 aksara (huruf besar, nombor, - _ .).';
    if ($d['name'] === '') $errors[] = 'Nama produk diperlukan.';
    if ($d['price'] <= 0)  $errors[] = 'Harga mesti lebih daripada 0.';
    $st = db()->prepare('SELECT id FROM products WHERE sku = ? AND id <> ?'); $st->execute([$d['sku'], $id]);
    if ($st->fetchColumn()) $errors[] = 'SKU sudah digunakan.';

    $image   = $p['image'];
    $gallery = product_gallery($p);
    $dir     = PUBLIC_PATH . '/uploads/products';
    $uploaded = $trash = [];   // fail baharu (padam jika gagal simpan) / fail lama (padam selepas berjaya)
    if (!$errors) {
        try {
            $new = handle_upload('image', $dir, MIME_IMAGES, 3 * 1048576);
            if ($new !== '') { $uploaded[] = $new; if ($image) $trash[] = $image; $image = $new; }
            elseif (isset($_POST['remove_image']) && $image) { $trash[] = $image; $image = null; }

            // Gambar tambahan: buang yang ditanda, kemudian tambah yang baharu (had PRODUCT_GALLERY_MAX)
            $drop = array_map('strval', (array)($_POST['remove_gallery'] ?? []));
            foreach ($gallery as $i => $g) if (in_array($g, $drop, true)) { $trash[] = $g; unset($gallery[$i]); }
            $gallery = array_values($gallery);
            $more = handle_upload_many('gallery', $dir, MIME_IMAGES, 3 * 1048576, PRODUCT_GALLERY_MAX - count($gallery));
            $uploaded = array_merge($uploaded, $more);
            $gallery  = array_merge($gallery, $more);

            // "Jadikan utama": tukar tempat gambar tambahan dengan gambar utama
            $cover = (string)($_POST['cover'] ?? '');
            if ($cover !== '' && ($i = array_search($cover, $gallery, true)) !== false) {
                if ($image) $gallery[$i] = $image; else unset($gallery[$i]);
                $image = $cover;
                $gallery = array_values($gallery);
            }
            // Tiada gambar utama tetapi ada tambahan: yang pertama jadi utama
            if (!$image && $gallery) $image = array_shift($gallery);
        } catch (RuntimeException $ex) {
            foreach ($uploaded as $n) @unlink($dir . '/' . basename($n));
            $errors[] = $ex->getMessage();
        }
    }
    $galleryJson = $gallery ? json_encode(array_values($gallery)) : null;
    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($id) {
                $pdo->prepare('UPDATE products SET sku=?, name=?, size=?, short_desc=?, description=?, usage_steps=?, warning=?, price=?, compare_price=?, image=?, gallery=?, weight_g=?, low_stock=?, sort=?, active=? WHERE id=?')
                    ->execute([$d['sku'], $d['name'], $d['size'], $d['short_desc'], $d['description'], $d['usage_steps'], $d['warning'], $d['price'], $d['compare_price'], $image ?: null, $galleryJson, $d['weight_g'], $d['low_stock'], $d['sort'], $d['active'], $id]);
            } else {
                $pdo->prepare('INSERT INTO products (sku, name, size, short_desc, description, usage_steps, warning, price, compare_price, image, gallery, weight_g, low_stock, sort, active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$d['sku'], $d['name'], $d['size'], $d['short_desc'], $d['description'], $d['usage_steps'], $d['warning'], $d['price'], $d['compare_price'], $image ?: null, $galleryJson, $d['weight_g'], $d['low_stock'], $d['sort'], $d['active']]);
                $id = (int)$pdo->lastInsertId();
                $open = max(0, (int)($_POST['opening_stock'] ?? 0));
                if ($open > 0) stock_move($id, $open, 'in', ['admin_id' => (int)$admin['id'], 'note' => 'Stok permulaan']);
            }
            $pdo->commit();
            foreach ($trash as $n) @unlink($dir . '/' . basename($n));
            flash('success', 'Produk disimpan.');
            redirect('admin/products.php');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            foreach ($uploaded as $n) @unlink($dir . '/' . basename($n));
            error_log('product save: ' . $ex->getMessage()); $errors[] = 'Gagal simpan produk.';
        }
    }
    $p = array_merge($p, $d);
}
admin_header($id ? 'Sunting produk' : 'Tambah produk', 'products.php');
?>
<?php if ($errors): ?><div class="mb-4 rounded-lg px-4 py-3 text-sm bg-rose-100 text-rose-800"><?php foreach ($errors as $er) echo '<p>' . e($er) . '</p>'; ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="bg-white rounded-xl border p-5 grid md:grid-cols-2 gap-4 max-w-4xl">
  <?= csrf_field() ?>
  <div><label class="lbl">Nama produk *</label><input name="name" class="inp" required value="<?= e($p['name']) ?>"></div>
  <div class="grid grid-cols-2 gap-4"><div><label class="lbl">SKU *</label><input name="sku" class="inp font-mono uppercase" required value="<?= e($p['sku']) ?>" placeholder="MAS-30"></div>
    <div><label class="lbl">Saiz</label><input name="size" class="inp" value="<?= e($p['size']) ?>" placeholder="30ml"></div></div>
  <div class="grid grid-cols-2 gap-4"><div><label class="lbl">Harga jualan (RM) *</label><input name="price" type="number" step="0.01" min="0.01" class="inp" required value="<?= e($p['price']) ?>"></div>
    <div><label class="lbl">Harga asal (RM) <span class="font-normal">dipotong</span></label><input name="compare_price" type="number" step="0.01" min="0" class="inp" value="<?= e((float)$p['compare_price'] ?: '') ?>"></div></div>
  <div class="grid grid-cols-2 gap-4"><div><label class="lbl">Amaran stok rendah</label><input name="low_stock" type="number" min="0" class="inp" value="<?= (int)$p['low_stock'] ?>"></div>
    <div><label class="lbl">Susunan</label><input name="sort" type="number" class="inp" value="<?= (int)$p['sort'] ?>"></div></div>
  <div><label class="lbl">Berat sebotol (gram) <span class="font-normal">termasuk kotak — untuk kadar pos &amp; SPX</span></label><input name="weight_g" type="number" min="1" step="1" class="inp" value="<?= (int)$p['weight_g'] ?>"></div>
  <div class="md:col-span-2"><label class="lbl">Penerangan ringkas</label><input name="short_desc" class="inp" maxlength="255" value="<?= e($p['short_desc']) ?>"></div>
  <div class="md:col-span-2 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
    <b>Elakkan tuntutan perubatan</b> dalam teks di bawah — contohnya "sakit lutut", "sakit kepala", "susah tidur", "merawat" atau "menyembuhkan".
    Tuntutan begini ialah iklan ubat di bawah Akta Iklan dan Jualan Ubat 1956 dan perlukan kelulusan KKLIU. Guna nama bahagian badan dan perkataan seperti "lenguh" atau "tegang".
  </div>
  <div class="md:col-span-2"><label class="lbl">Penerangan produk <span class="font-normal">(baris kosong = perenggan baharu)</span></label><textarea name="description" rows="6" class="inp"><?= e($p['description'] ?? '') ?></textarea></div>
  <div class="md:col-span-2"><label class="lbl">Cara penggunaan <span class="font-normal">(satu langkah sebaris; tulis "Tajuk: arahan" untuk tajuk tebal)</span></label>
    <textarea name="usage_steps" rows="6" class="inp" placeholder="Lutut: Sapu di bawah pelipat lutut dan di atas kepala lutut.&#10;Leher &amp; bahu: Sapu dari belakang telinga, turun ke leher hingga ke bahu."><?= e($p['usage_steps'] ?? '') ?></textarea></div>
  <div class="md:col-span-2"><label class="lbl">Amaran penggunaan</label><textarea name="warning" rows="2" class="inp" placeholder="Untuk sapuan luaran sahaja dan bukan untuk diminum."><?= e($p['warning'] ?? '') ?></textarea></div>
  <div><label class="lbl">Gambar utama (JPG/PNG/WEBP, maks 3MB)</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="text-sm">
    <?php if ($p['image']): ?><div class="flex items-center gap-3 mt-2"><img src="<?= e(product_image_url($p['image'])) ?>" class="w-20 h-20 rounded object-cover border" alt=""><label class="text-sm"><input type="checkbox" name="remove_image"> Buang gambar</label></div><?php endif; ?>
    <?php $gal = product_gallery($p); $room = PRODUCT_GALLERY_MAX - count($gal); ?>
    <label class="lbl mt-4">Gambar tambahan untuk slide <span class="font-normal">(<?= count($gal) ?>/<?= PRODUCT_GALLERY_MAX ?>)</span></label>
    <?php if ($gal): ?><div class="flex flex-wrap gap-3 mb-2">
      <?php foreach ($gal as $g): ?><div class="text-xs space-y-1"><img src="<?= e(product_image_url($g)) ?>" class="w-20 h-20 rounded object-cover border" alt="">
        <label class="flex items-center gap-1"><input type="checkbox" name="remove_gallery[]" value="<?= e($g) ?>"> Buang</label>
        <label class="flex items-center gap-1"><input type="radio" name="cover" value="<?= e($g) ?>"> Jadikan utama</label></div><?php endforeach; ?>
    </div><?php endif; ?>
    <?php if ($room > 0): ?><input type="file" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp" class="text-sm">
      <p class="text-xs text-slate-500 mt-1">Boleh pilih beberapa gambar sekali gus (baki <?= $room ?>). Gambar dipapar sebagai slide di halaman produk dan bahagian atas kedai.</p>
    <?php else: ?><p class="text-xs text-slate-500">Had penuh. Tanda "Buang" dahulu untuk tukar gambar.</p><?php endif; ?></div>
  <div class="space-y-3">
    <?php if (!$id): ?><div><label class="lbl">Stok permulaan</label><input name="opening_stock" type="number" min="0" class="inp" value="0"><p class="text-xs text-slate-500 mt-1">Direkod sebagai “stok masuk”.</p></div>
    <?php else: ?><p class="text-sm">Stok semasa: <b><?= (int)$p['stock'] ?></b>. Ubah melalui <a class="text-brand-leaf font-semibold" href="<?= e(url('admin/stock.php?product_id=' . $id)) ?>">halaman Stok</a> supaya ada rekod.</p><?php endif; ?>
    <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="active" <?= $p['active'] ? 'checked' : '' ?>> Papar di kedai</label>
  </div>
  <div class="md:col-span-2 flex gap-2"><button class="btn bg-brand text-white">Simpan</button><a href="<?= e(url('admin/products.php')) ?>" class="btn bg-slate-100">Batal</a></div>
</form>
<?php admin_footer();
