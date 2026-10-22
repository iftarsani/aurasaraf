<?php
/**
 * Tolakan jejak parcel J&T (logistics/statusFeedback). URL ini diisi di konsol
 * J&T semasa memohon "live" untuk antara muka tolakan; parcel dilanggan semasa
 * tempahan (trace/subscribe). Mesti URL HTTPS awam.
 *
 * Tiada CSRF (server-ke-server). J&T menghantar borang bizContent=JSON dengan
 * header digest. Dokumen tidak menyatakan formula tandatangan tolakan, jadi:
 * digest yang sepadan dengan formula API (base64(md5(bizContent + privateKey)))
 * => kandungan dipercayai terus; selain itu kandungan diabaikan dan status
 * ditarik semula dari J&T (logistics/trace) — paling kerap sekali 2 minit
 * bagi setiap parcel, supaya tolakan palsu tidak boleh mengubah apa-apa
 * dan tidak boleh membanjiri API.
 */
require __DIR__ . '/../aurasaraf_app/bootstrap.php';

header('Content-Type: application/json');
$okReply = json_encode(['code' => '1', 'msg' => 'success', 'data' => 'SUCCESS']);
if (!is_post() || !jnt_enabled()) { http_response_code(404); exit; }

$raw = (string)file_get_contents('php://input');
$biz = (string)($_POST['bizContent'] ?? '');
if ($biz === '' && is_array($j = json_decode($raw, true)) && isset($j['bizContent'])) {
    $biz = is_string($j['bizContent']) ? $j['bizContent'] : json_encode($j['bizContent'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
$digest  = (string)($_SERVER['HTTP_DIGEST'] ?? '');
$trusted = $biz !== '' && $digest !== '' && hash_equals(jnt_digest($biz), $digest);

$d = json_decode($biz, true);
$entries = !is_array($d) ? [] : (isset($d['billCode']) ? [$d] : array_values(array_filter($d, 'is_array')));
jnt_log(null, $trusted ? 'webhook' : 'webhook:unsigned', ['digest' => mb_substr($digest, 0, 60), 'body' => mb_substr($biz !== '' ? $biz : $raw, 0, 4000)]);

$st = db()->prepare("SELECT * FROM orders WHERE tracking_no = ? AND courier = 'jnt' LIMIT 1");
$requeried = 0;
try {
    foreach (array_slice($entries, 0, 50) as $e) {
        $bill = trim((string)($e['billCode'] ?? ''));
        if ($bill === '') continue;
        $st->execute([$bill]);
        $o = $st->fetch();
        // Parcel yang tidak dikenali tetap dijawab berjaya supaya J&T tidak cuba berulang kali.
        if (!$o) continue;
        if ($trusted) {
            jnt_apply_trace($o, $e);
        } elseif ($requeried < 5 && (!$o['jnt_synced_at'] || strtotime($o['jnt_synced_at']) < time() - 120)) {
            $requeried++;
            try { jnt_sync([$o]); } catch (Throwable $ex) { error_log('jnt-webhook sync: ' . $ex->getMessage()); }
        }
    }
} catch (Throwable $ex) {
    error_log('jnt-webhook: ' . $ex->getMessage());
    http_response_code(500);
    exit(json_encode(['code' => '0', 'msg' => 'error']));
}
echo $okReply;
