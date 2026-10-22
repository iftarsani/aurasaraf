<?php
/**
 * Urus testimoni — gambar yang dimuat naik atau video YouTube.
 *
 * Kandungan boleh disediakan bila-bila masa, tetapi storefront hanya
 * memaparkannya apabila setting kkliu_no diisi. Lihat testimonials_enabled().
 */
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require __DIR__ . '/_layout.php';
$admin = require_admin();

const TESTIMONI_DIR = '/uploads/testimonials';

if (is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'create') {
        $author  = mb_substr(trim((string)($_POST['author'] ?? '')), 0, 100);
        $caption = mb_substr(trim((string)($_POST['caption'] ?? '')), 0, 500);
        $sort    = (int)($_POST['sort_order'] ?? 0);
        $yt      = youtube_id((string)($_POST['youtube'] ?? ''));
        $ytRaw   = trim((string)($_POST['youtube'] ?? ''));

        try {
            if ($ytRaw !== '' && $yt === '') {
                flash('error', 'Pautan YouTube tidak dikenali. Tampal pautan penuh seperti https://youtu.be/xxxxxxxxxxx');
            } elseif ($yt !== '') {
                db()->prepare('INSERT INTO testimonials (kind, youtube_id, author, caption, sort_order) VALUES ("youtube", ?, ?, ?, ?)')
                    ->execute([$yt, $author, $caption, $sort]);
                flash('success', 'Video testimoni ditambah.');
            } else {
                $file = handle_upload('image', PUBLIC_PATH . TESTIMONI_DIR, MIME_IMAGES, 4 * 1048576);
                if ($file === '') {
                    flash('error', 'Muat naik gambar, atau tampal pautan YouTube.');
                } else {
                    db()->prepare('INSERT INTO testimonials (kind, image, author, caption, sort_order) VALUES ("image", ?, ?, ?, ?)')
                        ->execute([$file, $author, $caption, $sort]);
                    flash('success', 'Gambar testimoni ditambah.');
                }
            }
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
        }

    } else {
        $st = db()->prepare('SELECT id, kind, image FROM testimonials WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) { flash('error', 'Testimoni tidak dijumpai.'); redirect('admin/testimonials.php'); }

        if ($action === 'update') {
            db()->prepare('UPDATE testimonials SET author = ?, caption = ?, sort_order = ?, is_active = ? WHERE id = ?')
                ->execute([
                    mb_substr(trim((string)($_POST['author'] ?? '')), 0, 100),
                    mb_substr(trim((string)($_POST['caption'] ?? '')), 0, 500),
                    (int)($_POST['sort_order'] ?? 0),
                    isset($_POST['is_active']) ? 1 : 0,
                    $row['id'],
                ]);
            flash('success', 'Testimoni dikemas kini.');

        } elseif ($action === 'delete') {
            // Buang fail dahulu; baris DB ialah satu-satunya rujukan kepadanya.
            if ($row['kind'] === 'image' && $row['image'] !== '') {
                @unlink(PUBLIC_PATH . TESTIMONI_DIR . '/' . basename((string)$row['image']));
            }
            db()->prepare('DELETE FROM testimonials WHERE id = ?')->execute([$row['id']]);
            flash('success', 'Testimoni dipadam.');
        }
    }
    redirect('admin/testimonials.php');
}

$senarai = testimonials_list(false);
$aktif   = testimonials_enabled();
admin_header('Testimoni', 'testimonials.php');
?>
<?php $kkliu = trim((string)setting('kkliu_no')); ?>
<?php if (!$aktif): ?>
  <div class="mb-4 rounded-lg bg-amber-100 text-amber-900 px-4 py-3 text-sm">
    <b>Belum dipaparkan di kedai.</b> Hidupkan <b>Papar bahagian testimoni di kedai</b> dalam
    <a href="<?= e(url('admin/settings.php')) ?>" class="underline font-semibold">Tetapan</a> apabila kandungan sudah sedia.
  </div>
<?php else: ?>
  <div class="mb-4 rounded-lg bg-emerald-100 text-emerald-800 px-4 py-3 text-sm">
    <b>Dipaparkan di kedai</b> — testimoni aktif kelihatan kepada pelanggan<?= $kkliu !== '' ? ', dengan KKLIU ' . e($kkliu) : '' ?>.
  </div>
<?php endif; ?>
<?php if ($kkliu === ''): ?>
  <div class="mb-4 rounded-lg bg-slate-100 text-slate-700 px-4 py-3 text-sm">
    Tiada nombor KKLIU diisi. Pastikan gambar dan kapsyen kekal pada produk, bungkusan atau kepuasan servis.
    Testimoni yang mendakwa kesan kesihatan — "lega sakit lutut", "hilang lenguh" — ialah iklan ubat di bawah
    Akta Ubat (Iklan dan Penjualan) 1956 dan memerlukan kelulusan Lembaga Iklan Ubat terlebih dahulu.
  </div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-4">
  <div class="lg:col-span-2 space-y-3">
    <?php if (!$senarai): ?>
      <div class="bg-white rounded-xl border p-5 text-sm text-slate-600">Belum ada testimoni. Tambah yang pertama di sebelah.</div>
    <?php endif; ?>
    <?php foreach ($senarai as $t): ?>
      <div class="bg-white rounded-xl border p-5">
        <div class="flex gap-4">
          <div class="w-40 shrink-0">
            <?php if ($t['kind'] === 'image'): ?>
              <img src="<?= e(testimonial_image_url($t['image'])) ?>" alt="" class="w-full rounded-lg border">
            <?php else: ?>
              <a href="https://www.youtube.com/watch?v=<?= e($t['youtube_id']) ?>" target="_blank" rel="noopener" class="block relative">
                <img src="https://img.youtube.com/vi/<?= e($t['youtube_id']) ?>/mqdefault.jpg" alt="" class="w-full rounded-lg border">
                <span class="absolute inset-0 grid place-items-center text-white text-3xl drop-shadow">▶</span>
              </a>
            <?php endif; ?>
            <p class="text-xs text-slate-500 mt-1"><?= $t['kind'] === 'image' ? 'Gambar' : 'YouTube · ' . e($t['youtube_id']) ?></p>
          </div>

          <form method="post" class="flex-1 space-y-3"><?= csrf_field() ?>
            <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <div class="grid sm:grid-cols-2 gap-3">
              <div><label class="lbl">Nama pelanggan</label><input name="author" class="inp" maxlength="100" value="<?= e($t['author']) ?>"></div>
              <div><label class="lbl">Susunan (kecil dahulu)</label><input name="sort_order" type="number" class="inp" value="<?= (int)$t['sort_order'] ?>"></div>
            </div>
            <div><label class="lbl">Kapsyen</label><textarea name="caption" rows="2" maxlength="500" class="inp"><?= e($t['caption']) ?></textarea></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" <?= $t['is_active'] ? 'checked' : '' ?>> Aktif</label>
            <div class="flex gap-2">
              <button class="btn bg-brand text-white">Simpan</button>
            </div>
          </form>
        </div>
        <div class="mt-3 pt-3 border-t">
          <form method="post" data-confirm="Padam testimoni ini? Gambarnya turut dibuang dan tidak boleh dikembalikan." data-confirm-ok="Padam"><?= csrf_field() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <button class="btn bg-rose-600 text-white">Padam</button></form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <form method="post" enctype="multipart/form-data" class="bg-white rounded-xl border p-5 space-y-3 self-start"><?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <h2 class="font-semibold">Tambah testimoni</h2>
    <p class="text-xs text-slate-500">Muat naik gambar <b>atau</b> tampal pautan YouTube — satu sahaja setiap testimoni.</p>
    <div><label class="lbl">Gambar (JPG/PNG/WEBP, maks 4MB)</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="text-sm"></div>
    <div><label class="lbl">atau pautan YouTube</label><input name="youtube" class="inp" placeholder="https://youtu.be/xxxxxxxxxxx"></div>
    <div><label class="lbl">Nama pelanggan</label><input name="author" class="inp" maxlength="100" placeholder="Pn. Aminah, Kedah"></div>
    <div><label class="lbl">Kapsyen</label><textarea name="caption" rows="3" maxlength="500" class="inp"></textarea></div>
    <div><label class="lbl">Susunan</label><input name="sort_order" type="number" class="inp" value="0"></div>
    <button class="btn bg-brand text-white">Tambah</button>
  </form>
</div>
<?php admin_footer();
