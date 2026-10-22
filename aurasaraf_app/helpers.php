<?php
declare(strict_types=1);

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money($n): string { return 'RM' . number_format((float)$n, 2); }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
/**
 * IP sebenar pelanggan.
 *
 * Di belakang Cloudflare Tunnel, cloudflared menyambung ke Apache dari localhost,
 * jadi REMOTE_ADDR sentiasa 127.0.0.1 untuk SEMUA pelawat — had kadar per-IP dalam
 * admin_login() dan checkout.php akan jadi global, dan seorang penyerang boleh
 * mengunci semua orang lain. Cloudflare menghantar IP asal dalam CF-Connecting-IP.
 *
 * Header itu hanya dipercayai apabila sambungan datang dari loopback, iaitu tempat
 * cloudflared berada. Tanpa syarat itu sesiapa yang capai Apache terus boleh
 * memalsukan header dan memintas had kadar sepenuhnya.
 */
function client_ip(): string
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    if ($remote === '127.0.0.1' || $remote === '::1') {
        $cf = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) !== false) {
            return substr($cf, 0, 45);
        }
    }

    return substr($remote, 0, 45);
}

function url(string $path = ''): string
{
    global $CONFIG;
    return rtrim((string)$CONFIG['base_url'], '/') . '/' . ltrim($path, '/');
}
// URL fail statik (CSS/JS/gambar) dengan ?v=<masa ubah fail>, supaya cache pelayar/server
// dibatalkan secara automatik setiap kali fail baharu dimuat naik.
function asset(string $path): string
{
    $file = PUBLIC_PATH . '/' . ltrim($path, '/');
    $v = is_file($file) ? (string)filemtime($file) : '1';
    return url($path) . '?v=' . $v;
}
function redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void
{
    $t = (string)($_POST['_csrf'] ?? '');
    if ($t === '' || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Sesi tamat atau token tidak sah. Sila kembali dan cuba semula.');
    }
}

/* ---------- Flash ---------- */
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

/* ---------- Tetapan (jadual settings) ---------- */
function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = db()->query('SELECT skey, svalue FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $cache;
}
function setting(string $key, $default = '') { $s = settings_all(); return ($s[$key] ?? '') !== '' ? $s[$key] : $default; }
function setting_set(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)')
        ->execute([$key, $value]);
}

/* ---------- Telefon / WhatsApp ---------- */
function norm_phone(string $p): string
{
    $d = preg_replace('/\D+/', '', $p);
    if (str_starts_with($d, '0')) $d = '6' . $d;       // 018... -> 6018...
    elseif (str_starts_with($d, '1')) $d = '60' . $d;  // 18...  -> 6018...
    return $d;
}
function wa_link(string $phone, string $text = ''): string
{
    return 'https://wa.me/' . norm_phone($phone) . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/* ---------- Negeri & penghantaran ---------- */
function states(): array
{
    return [
        'Johor' => 'SM', 'Kedah' => 'SM', 'Kelantan' => 'SM', 'Melaka' => 'SM', 'Negeri Sembilan' => 'SM',
        'Pahang' => 'SM', 'Perak' => 'SM', 'Perlis' => 'SM', 'Pulau Pinang' => 'SM', 'Selangor' => 'SM',
        'Terengganu' => 'SM', 'W.P. Kuala Lumpur' => 'SM', 'W.P. Putrajaya' => 'SM',
        'Sabah' => 'SS', 'Sarawak' => 'SS', 'W.P. Labuan' => 'SS',
    ];
}
function shipping_fee(string $region, float $subtotal): float
{
    $freeMin = (float)setting('free_min', 0);
    if ($freeMin > 0 && $subtotal >= $freeMin) return 0.0;
    return (float)setting($region === 'SS' ? 'ship_ss' : 'ship_sm', $region === 'SS' ? 12 : 8);
}
/** Alamat pengirim (Tetapan → Penghantaran, dikongsi SPX & J&T) cukup untuk tempahan kurier. */
function ship_sender_ready(): bool
{
    return setting('spx_sender_name') !== '' && setting('spx_sender_phone') !== ''
        && setting('spx_sender_address') !== '' && preg_match('/^\d{5}$/', (string)setting('spx_sender_postcode')) === 1;
}

/**
 * Kurier utama untuk kadar pos di checkout: 'spx' | 'jnt', atau '' (kadar tetap)
 * bila tidak dipilih atau kurier itu belum diset sepenuhnya.
 */
function rate_courier(): string
{
    $c = (string)setting('rate_courier');
    if ($c === 'spx' && spx_ready()) return 'spx';
    if ($c === 'jnt' && jnt_ready()) return 'jnt';
    return '';
}

/** Cache anggaran kos pos (12 jam) ikut kurier + poskod + berat dibundar ke 100g. */
function quote_cache_get(string $courier, string $postcode, int $grams): ?float
{
    $st = db()->prepare('SELECT fee FROM shipping_quotes WHERE courier = ? AND postcode = ? AND grams = ? AND created_at > (NOW() - INTERVAL 12 HOUR)');
    $st->execute([$courier, $postcode, $grams]);
    $hit = $st->fetchColumn();
    return $hit === false ? null : (float)$hit;
}
function quote_cache_put(string $courier, string $postcode, int $grams, float $fee): float
{
    $fee = round($fee, 2);
    db()->prepare('REPLACE INTO shipping_quotes (courier, postcode, grams, fee, created_at) VALUES (?, ?, ?, ?, NOW())')
        ->execute([$courier, $postcode, $grams, $fee]);
    return $fee;
}

/**
 * Kos pos untuk pesanan: [RM, sumber] dengan sumber 'free' | 'spx' | 'jnt' | 'flat'.
 * Pos percuma (free_min) didahulukan. Bila kurier utama dipilih, anggaran kurier
 * itu + markup kedai digunakan; jika kurier gagal atau tidak menjawab, kadar
 * tetap SM/SS diguna supaya checkout tidak pernah tersekat kerana kurier.
 * Dipanggil oleh checkout.php (yang dicaj) dan shipping-quote.php (yang
 * dipapar) — kedua-duanya mesti melalui fungsi ini supaya angkanya sama.
 */
function shipping_calc(string $state, string $postcode, string $city, float $subtotal, int $grams): array
{
    $region  = states()[$state] ?? 'SM';
    $freeMin = (float)setting('free_min', 0);
    if ($freeMin > 0 && $subtotal >= $freeMin) return [0.0, 'free'];
    $c = rate_courier();
    if ($c !== '') {
        $q = $c === 'spx' ? spx_quote($postcode, $state, $city, $grams, $subtotal) : jnt_quote($postcode, $state, $city, $grams, $subtotal);
        if ($q !== null) return [round($q + max(0, (float)setting('rate_markup', 0)), 2), $c];
    }
    return [shipping_fee($region, $subtotal), 'flat'];
}

/* ---------- Media sosial ---------- */
/**
 * Akaun media sosial: kunci tetapan => [nama paparan, hos yang dibenarkan].
 * Hos disemak semasa simpan supaya pautan footer hanya menuju ke platform itu.
 */
function socials(): array
{
    return [
        'social_facebook'  => ['Facebook',  ['facebook.com', 'fb.com', 'fb.me']],
        'social_instagram' => ['Instagram', ['instagram.com']],
        'social_tiktok'    => ['TikTok',    ['tiktok.com']],
    ];
}

/** True jika $url ialah pautan https ke salah satu hos (atau subdomainnya). */
function social_url_ok(string $url, array $hosts): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    if (strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') return false;
    $h = strtolower((string)parse_url($url, PHP_URL_HOST));
    foreach ($hosts as $ok) if ($h === $ok || str_ends_with($h, '.' . $ok)) return true;
    return false;
}

/** Pautan media sosial yang diisi: [[nama, url], ...]. */
function social_links(): array
{
    $out = [];
    foreach (socials() as $k => [$label]) {
        $u = trim((string)setting($k));
        if ($u !== '') $out[] = [$label, $u];
    }
    return $out;
}

/* ---------- Kurier & penjejakan ---------- */
/**
 * Senarai kurier: kunci => [nama paparan, laman jejak, templat pautan terus].
 * Templat kosong bermakna laman kurier itu guna borang — nombor tidak boleh
 * dihantar melalui URL, jadi pelanggan kena tampal sendiri.
 * Tambah kurier baharu di sini sahaja; semua tempat lain ikut senarai ini.
 */
function couriers(): array
{
    return [
        'poslaju'  => ['Pos Laju / Pos Malaysia', 'https://tracking.pos.com.my/tracking/',            ''],
        'jnt'      => ['J&T Express',             'https://www.jtexpress.my/tracking',                ''],
        'ninjavan' => ['Ninja Van',               'https://www.ninjavan.co/en-my/tracking',           'https://www.ninjavan.co/en-my/tracking?id={no}'],
        'citylink' => ['City-Link Express',       'https://www.citylinkexpress.com/tracking-result/', ''],
        'dhl'      => ['DHL eCommerce',           'https://ecommerceportal.dhl.com/track/',           ''],
        'spx'      => ['Shopee Express (SPX)',    'https://spx.com.my/',                              'https://spx.com.my/track?{no}'],
        'skynet'   => ['Skynet',                  'https://www.skynet.com.my/',                       ''],
        'gdex'     => ['GDEX',                    'https://www.gdexpress.com/',                       ''],
    ];
}
/** Nama kurier untuk paparan. Nilai teks bebas lama dipulangkan seadanya. */
function courier_label(string $courier): string { return couriers()[$courier][0] ?? $courier; }

/** Pautan jejak untuk kurier. Pulang '' jika kurier tidak dikenali. */
function tracking_url(string $courier, string $trackingNo): string
{
    $c = couriers()[$courier] ?? null;
    if (!$c) return '';
    if ($c[2] !== '' && $trackingNo !== '') return str_replace('{no}', rawurlencode($trackingNo), $c[2]);
    return $c[1];
}
/** True jika pautan terus ke keputusan; false jika hanya laman borang. */
function tracking_is_direct(string $courier): bool { return (couriers()[$courier][2] ?? '') !== ''; }

/**
 * Tentukan nilai `orders.courier` daripada input borang.
 *
 * $selected ialah kunci daripada couriers(), atau 'lain' untuk membuka nama
 * bebas dalam $other. Kunci yang tidak dikenali dibuang menjadi '' — tanpa itu
 * sesiapa yang ubah <option> boleh menyimpan teks sampah sebagai nama kurier,
 * dan tracking_url() kemudiannya tidak akan jumpa padanan.
 */
function courier_resolve(string $selected, string $other = ''): string
{
    if ($selected === 'lain')         return mb_substr(trim($other), 0, 60);
    if (isset(couriers()[$selected])) return $selected;
    return '';
}

/* ---------- Kod bar (Code 128B) ---------- */
/**
 * Jana kod bar Code 128 subset B sebagai SVG inline.
 *
 * Ditulis tangan kerana projek ini tiada composer/pustaka luar, dan label
 * penghantaran mesti boleh dicetak walaupun mesin kedai tiada internet —
 * perkhidmatan penjana kod bar dalam talian juga bermakna nombor pesanan
 * pelanggan dihantar keluar, yang tidak perlu.
 *
 * Subset B meliputi ASCII 32–126, cukup untuk nombor pesanan (AS250928ABCD)
 * dan nombor tracking kurier. Aksara di luar julat itu ditolak — pemanggil
 * mesti menganggap '' sebagai "tidak boleh dijadikan kod bar".
 *
 * PENTING: kod bar ini HANYA mengekod teks yang diberi. Ia bukan consignment
 * note rasmi kurier; nombor tracking sebenar tetap datang daripada kurier.
 */
function barcode128_svg(string $data, float $heightMm = 13.0, float $moduleMm = 0.33): string
{
    // \z, bukan $ — $ membenarkan satu "\n" di hujung, dan ord("\n")-32 negatif
    // akan mengindeks jadual corak di luar julat.
    if ($data === '' || !preg_match('/^[\x20-\x7E]+\z/', $data)) return '';

    // Jadual rasmi Code 128: setiap corak = lebar 6 elemen (bar, jarak, bar, ...).
    $pat = [
        '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
        '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
        '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
        '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
        '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
        '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
        '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
        '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
        '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
        '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
        '114131','311141','411131','211412','211214','211232','2331112',
    ];

    $codes = [104];              // Start B
    $sum   = 104;
    $i     = 1;
    foreach (str_split($data) as $ch) {
        $v       = ord($ch) - 32;
        $codes[] = $v;
        $sum    += $v * $i++;
    }
    $codes[] = $sum % 103;       // semakan
    $codes[] = 106;              // Stop

    // Unit lukisan = satu modul. Tinggi ditukar ke unit modul supaya nisbah
    // kekal betul tanpa preserveAspectRatio (bar senget = tidak boleh diimbas).
    $h     = $heightMm / $moduleMm;
    $quiet = 10;                 // zon senyap wajib, sekurang-kurangnya 10 modul
    $x     = $quiet;
    $rects = '';
    foreach ($codes as $c) {
        $bar = true;
        foreach (str_split($pat[$c]) as $w) {
            $w = (int)$w;
            if ($bar) $rects .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . round($h, 2) . '"/>';
            $x  += $w;
            $bar = !$bar;
        }
    }
    $total = $x + $quiet;

    return '<svg class="bcode" viewBox="0 0 ' . $total . ' ' . round($h, 2) . '"'
        . ' width="' . round($total * $moduleMm, 2) . 'mm" height="' . $heightMm . 'mm"'
        . ' shape-rendering="crispEdges" fill="#000" role="img" aria-label="' . e($data) . '">'
        . $rects . '</svg>';
}

/* ---------- Status pesanan ---------- */
function order_statuses(): array
{
    return [
        'new'             => ['Baru',             'bg-amber-100 text-amber-800'],
        'pending_payment' => ['Menunggu bayaran', 'bg-slate-200 text-slate-700'],
        'paid'            => ['Dibayar',          'bg-emerald-100 text-emerald-800'],
        'shipped'         => ['Dihantar',         'bg-sky-100 text-sky-800'],
        'completed'       => ['Selesai',          'bg-green-700 text-white'],
        'cancelled'       => ['Batal',            'bg-rose-100 text-rose-800'],
    ];
}
function status_label(string $s): string { return order_statuses()[$s][0] ?? $s; }
function pay_label(string $m): string
{
    return ['fpx' => 'FPX (Billplz)', 'transfer' => 'Transfer / DuitNow QR', 'whatsapp' => 'WhatsApp'][$m] ?? $m;
}

/** True jika akaun bank atau gambar QR sudah diisi dalam Tetapan. */
function bank_configured(): bool { return setting('bank_acc') !== '' || setting('qr_image') !== ''; }

/**
 * Cara bayaran yang ditawarkan di checkout, mengikut susunan paparan.
 * Satu-satunya sumber kebenaran — borang (index.php) dan pengesahan
 * (checkout.php) kedua-duanya guna senarai ini.
 *
 * - FPX: bila Billplz aktif.
 * - Transfer: bila bank/QR diisi DAN (pemilik hidupkan pay_transfer ATAU
 *   Billplz belum aktif — ketika itu transfer ialah laluan bayaran utama).
 * - WhatsApp: hanya bila pemilik hidupkan pay_whatsapp.
 * Jika senarai kosong (tiada Billplz, tiada bank), WhatsApp dibuka sebagai
 * jalan terakhir supaya kedai tidak pernah tinggal tanpa cara bayaran.
 */
function payment_methods(): array
{
    $bp = bp_enabled();
    $m  = [];
    if ($bp) $m[] = 'fpx';
    if (bank_configured() && (!$bp || setting('pay_transfer') === '1')) $m[] = 'transfer';
    if (setting('pay_whatsapp') === '1') $m[] = 'whatsapp';
    return $m ?: ['whatsapp'];
}

/* ---------- Muat naik fail ---------- */
/**
 * Simpan fail muat naik dengan nama rawak. Pulangkan nama fail, '' jika tiada fail.
 * @throws RuntimeException jika fail tidak sah
 */
function handle_upload(string $field, string $destDir, array $allowed, int $maxBytes = 5242880): string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    return upload_store($_FILES[$field], $destDir, $allowed, $maxBytes);
}

/**
 * Muat naik berbilang fail dari <input type="file" name="x[]" multiple>.
 * Paling banyak $limit fail diambil; slot kosong dilangkau. Jika satu fail
 * gagal, fail yang sudah disimpan dalam panggilan ini dipadam semula.
 */
function handle_upload_many(string $field, string $destDir, array $allowed, int $maxBytes, int $limit): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f['name'] ?? null)) return [];
    $saved = [];
    try {
        foreach (array_keys($f['name']) as $k) {
            if (($f['error'][$k] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if (count($saved) >= $limit) break;
            $saved[] = upload_store(['name' => $f['name'][$k], 'tmp_name' => $f['tmp_name'][$k], 'error' => $f['error'][$k], 'size' => $f['size'][$k]], $destDir, $allowed, $maxBytes);
        }
    } catch (RuntimeException $ex) {
        foreach ($saved as $n) @unlink(rtrim($destDir, '/') . '/' . $n);
        throw $ex;
    }
    return $saved;
}

function upload_store(array $f, string $destDir, array $allowed, int $maxBytes): string
{
    if ($f['error'] !== UPLOAD_ERR_OK)  throw new RuntimeException('Muat naik gagal (kod ' . $f['error'] . ').');
    if ($f['size'] > $maxBytes)         throw new RuntimeException('Fail terlalu besar. Had ' . round($maxBytes / 1048576) . 'MB.');
    if (!is_uploaded_file($f['tmp_name'])) throw new RuntimeException('Fail tidak sah.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($allowed[$mime])) throw new RuntimeException('Jenis fail tidak dibenarkan.');

    if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) throw new RuntimeException('Folder simpanan tidak boleh dicipta.');
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], rtrim($destDir, '/') . '/' . $name)) {
        throw new RuntimeException('Fail tidak dapat disimpan. Semak permission folder.');
    }
    return $name;
}
const MIME_IMAGES  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const MIME_RECEIPT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

function product_image_url(?string $file): string { return $file ? url('uploads/products/' . rawurlencode($file)) : ''; }
function product_url(array $p): string { return url('product.php?id=' . (int)$p['id']); }

/** Had gambar tambahan (slide) bagi satu produk, selain gambar utama. */
const PRODUCT_GALLERY_MAX = 4;

/** Gambar tambahan produk (products.gallery, JSON senarai nama fail). */
function product_gallery(array $p): array
{
    $g = json_decode((string)($p['gallery'] ?? ''), true);
    if (!is_array($g)) return [];
    return array_values(array_filter($g, fn($n) => is_string($n) && preg_match('/^[A-Za-z0-9._-]{1,120}$/', $n)));
}

/** Semua gambar produk untuk slide: gambar utama dahulu, kemudian tambahan. */
function product_images(array $p): array
{
    return array_values(array_unique(array_merge(!empty($p['image']) ? [(string)$p['image']] : [], product_gallery($p))));
}

/**
 * Pecahkan products.usage_steps kepada langkah: [[tajuk, teks], ...].
 * Satu langkah sebaris; nombor di depan ("1." / "2)") dibuang. Jika baris
 * berbentuk "Tajuk: teks", tajuk dipapar tebal — jika tidak, tajuk ''.
 */
function usage_steps(?string $raw): array
{
    $out = [];
    foreach (preg_split('/\R/', (string)$raw) as $l) {
        $l = trim(preg_replace('/^\s*\d+\s*[.)]\s*/u', '', $l));
        if ($l === '') continue;
        if (preg_match('/^([^:]{1,40}):\s*(.+)$/u', $l, $m)) $out[] = [trim($m[1]), trim($m[2])];
        else $out[] = ['', $l];
    }
    return $out;
}

/** Perenggan teks biasa → [perenggan, ...] (dipisah baris kosong). */
function paragraphs(?string $raw): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/', trim((string)$raw))), 'strlen'));
}

/* ---------- Testimoni ---------- */

function testimonial_image_url(?string $file): string { return $file ? url('uploads/testimonials/' . rawurlencode($file)) : ''; }

/**
 * Cabut ID video 11-aksara daripada apa sahaja bentuk pautan YouTube.
 * Menerima watch?v=, youtu.be/, /embed/, /shorts/, /live/, atau ID mentah.
 * Pulangkan '' jika tiada ID sah — pemanggil mesti menganggapnya input tidak sah.
 */
function youtube_id(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') return '';
    if (preg_match('~^[A-Za-z0-9_-]{11}$~', $raw)) return $raw;
    // (?!...) memastikan ID betul-betul tamat pada 11 aksara. Tanpanya, nilai
    // lebih panjang seperti ?v=terlalu_pendek akan dipotong menjadi ID palsu
    // dan menyemat video yang salah, bukannya ditolak.
    if (preg_match('~(?:youtu\.be/|/embed/|/shorts/|/live/|[?&]v=)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])~', $raw, $m)) return $m[1];
    return '';
}

/**
 * Adakah bahagian testimoni dipaparkan di storefront.
 *
 * Dikawal oleh suis `testimonials_show` dalam Tetapan, bukan oleh nombor KKLIU.
 * Sebabnya: yang menentukan keperluan kelulusan KKLIU ialah ISI testimoni, bukan
 * kewujudannya — gambar "barang sampai cepat" bukan iklan ubat, manakala
 * screenshot "lega sakit lutut" ialah dakwaan terapeutik di bawah Akta Ubat
 * (Iklan dan Penjualan) 1956. Pemilik kedai yang menilai isinya; suis ini
 * lalai MATI supaya keputusan itu sentiasa dibuat secara sedar.
 */
function testimonials_enabled(): bool
{
    return (string)setting('testimonials_show') === '1';
}

function testimonials_list(bool $activeOnly = true): array
{
    $sql = 'SELECT id, kind, image, youtube_id, author, caption, sort_order, is_active, created_at FROM testimonials';
    if ($activeOnly) $sql .= ' WHERE is_active = 1';
    return db()->query($sql . ' ORDER BY sort_order, id DESC')->fetchAll();
}
