<?php
/** Komponen kongsi untuk halaman awam. */
function bottle_svg(): string
{
    return '<svg viewBox="0 0 200 380" role="img" aria-label="Botol Minyak Aura Saraf 30ml" class="bottle">
<defs><linearGradient id="cap" x1="0" x2="1"><stop offset="0" stop-color="#8a6416"/><stop offset=".35" stop-color="#f1d27a"/><stop offset=".7" stop-color="#b4841a"/><stop offset="1" stop-color="#7a560f"/></linearGradient>
<linearGradient id="oil" x1="0" x2="1"><stop offset="0" stop-color="#e6d37a"/><stop offset=".5" stop-color="#f6ebaa"/><stop offset="1" stop-color="#d9c25c"/></linearGradient></defs>
<rect x="62" y="6" width="76" height="70" rx="8" fill="url(#cap)"/><g stroke="#7a560f" stroke-opacity=".35"><line x1="62" y1="52" x2="138" y2="52"/><line x1="62" y1="60" x2="138" y2="60"/><line x1="62" y1="68" x2="138" y2="68"/></g>
<rect x="70" y="76" width="60" height="22" fill="#eef3ea" opacity=".9"/>
<path d="M70 98 C40 108 30 124 30 150 V340 a26 26 0 0 0 26 26 H144 a26 26 0 0 0 26-26 V150 C170 124 160 108 130 98 Z" fill="url(#oil)" stroke="#cdbb63"/>
<rect x="30" y="168" width="140" height="150" fill="#fff"/><rect x="30" y="160" width="140" height="12" fill="#2f7d4a"/><rect x="30" y="314" width="140" height="12" fill="#2f7d4a"/>
<path d="M92 186c6-10 16-12 20-12-1 10-6 18-16 20 3-5 5-8 7-11-5 3-8 6-11 3z" fill="#2f7d4a"/>
<text x="100" y="222" text-anchor="middle" font-family="Figtree,sans-serif" font-size="15" fill="#222">Minyak</text>
<text x="100" y="244" text-anchor="middle" font-family="Figtree,sans-serif" font-weight="800" font-size="19" fill="#111">Aura Saraf</text>
<circle cx="72" cy="272" r="11" fill="#7a4a2a"/><circle cx="72" cy="272" r="7" fill="#fff"/><ellipse cx="100" cy="272" rx="7" ry="13" fill="#2f7d4a"/><circle cx="128" cy="272" r="9" fill="#e7a3b8"/>
<text x="100" y="304" text-anchor="middle" font-family="Figtree,sans-serif" font-size="9" fill="#333">Kegunaan Luaran Sahaja · 30ML</text>
<rect x="44" y="120" width="10" height="220" rx="5" fill="#fff" opacity=".35"/></svg>';
}

function page_head(string $title, string $desc = ''): void
{ ?>
<!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<?php if ($desc): ?><meta name="description" content="<?= e($desc) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/style.css')) ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🌿</text></svg>">
</head>
<body>
<?php }

function page_foot(): void
{
    $wa = setting('whatsapp'); ?>
<footer class="foot">
  <div class="wrap foot-grid">
    <div>
      <strong class="foot-brand"><?= e(setting('shop_name', 'Minyak Aura Saraf')) ?></strong>
      <p><?= e(setting('company', 'Warisan Maktok Empire')) ?><?php if (setting('ssm_no')): ?> · SSM <?= e(setting('ssm_no')) ?><?php endif; ?></p>
      <?php if (setting('mal_no') || setting('kkliu_no')): ?>
      <p class="muted"><?php if (setting('mal_no')): ?>No. Pendaftaran: <?= e(setting('mal_no')) ?><?php endif; ?>
      <?php if (setting('kkliu_no')): ?> · KKLIU <?= e(setting('kkliu_no')) ?><?php endif; ?></p>
      <?php endif; ?>
    </div>
    <div>
      <?php if ($wa): ?><a class="foot-link" href="<?= e(wa_link($wa, 'Salam, saya nak tanya pasal Minyak Aura Saraf')) ?>" target="_blank" rel="noopener">WhatsApp <?= e($wa) ?></a><?php endif; ?>
      <?php if (setting('whatsapp2')): ?><a class="foot-link" href="<?= e(wa_link(setting('whatsapp2'))) ?>" target="_blank" rel="noopener">WhatsApp <?= e(setting('whatsapp2')) ?></a><?php endif; ?>
    </div>
    <?php if ($sosial = social_links()): ?>
    <div>
      <strong class="foot-h">Ikuti kami</strong>
      <?php foreach ($sosial as [$label, $u]): ?><a class="foot-link" href="<?= e($u) ?>" target="_blank" rel="noopener"><?= e($label) ?></a><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <p class="wrap foot-note">Kegunaan luaran sahaja. Produk ini bukan pengganti rawatan perubatan. Dapatkan nasihat doktor jika sakit berpanjangan. © <?= date('Y') ?></p>
</footer>
</body></html>
<?php }
