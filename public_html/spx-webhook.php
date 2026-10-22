<?php
/**
 * Webhook status parcel SPX (4.1 Tracking Web Hook). URL ini didaftar oleh PIC
 * SPX pada app semasa app-id diberi — ia mesti URL HTTPS awam.
 *
 * Tiada CSRF (server-ke-server). Keaslian disahkan dengan check-sign: SPX
 * menandatangani body mentah dengan app_secret kedai menggunakan formula yang
 * sama seperti permintaan API. Tandatangan salah = 401 dan tiada apa diubah.
 * SPX menunggu 3 saat sahaja dan berhenti menghantar jika kerap gagal, jadi
 * kerja di sini dihadkan kepada satu carian dan satu kemas kini.
 */
require __DIR__ . '/../aurasaraf_app/bootstrap.php';

$raw = (string)file_get_contents('php://input');
if (!is_post() || !spx_enabled()) { http_response_code(404); exit; }

$ts   = (string)($_SERVER['HTTP_TIMESTAMP'] ?? '');
$rand = (string)($_SERVER['HTTP_RANDOM_NUM'] ?? '');
$sign = strtolower((string)($_SERVER['HTTP_CHECK_SIGN'] ?? ''));
$app  = (string)($_SERVER['HTTP_APP_ID'] ?? '');

$valid = ctype_digit($ts) && preg_match('/^-?\d{1,19}$/', $rand) && $sign !== ''
      && ($app === '' || $app === (string)(int)(spx_cfg()['app_id'] ?? 0))
      && hash_equals(spx_sign($raw, (int)$ts, (int)$rand), $sign);
if (!$valid) {
    spx_log(null, 'webhook:badsign', ['headers' => compact('ts', 'rand', 'app'), 'body' => mb_substr($raw, 0, 2000)]);
    http_response_code(401);
    exit('bad signature');
}

$d  = json_decode($raw, true);
$tn = is_array($d) ? trim((string)($d['tracking_no'] ?? '')) : '';
$st = db()->prepare("SELECT * FROM orders WHERE tracking_no = ? AND courier = 'spx' LIMIT 1");
$st->execute([$tn]);
$o = $tn !== '' ? $st->fetch() : false;
spx_log($o ? (int)$o['id'] : null, 'webhook', $d ?? $raw);

// Parcel yang tidak dikenali tetap dijawab 200 — jika tidak SPX akan cuba
// berulang kali dan akhirnya mematikan webhook untuk semua pesanan.
if ($o) {
    try {
        spx_apply_status($o, (string)($d['status_code'] ?? ''), (string)($d['status'] ?? ''), (int)($d['timestamp'] ?? 0));
    } catch (Throwable $ex) {
        error_log('spx-webhook: ' . $ex->getMessage());
        http_response_code(500);
        exit('error');
    }
}
echo 'ok';
