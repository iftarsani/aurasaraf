<?php
/**
 * Callback server-ke-server dari Billplz (POST: id, collection_id, paid, state, amount, paid_amount, x_signature, ...).
 * Tiada sesi / CSRF di sini. Keselamatan: data POST hanya dijadikan petunjuk;
 * status sebenar disahkan melalui GET /v3/bills/{id} sebelum pesanan ditanda dibayar.
 */
require __DIR__ . '/../aurasaraf_app/bootstrap.php';

if (!is_post()) { http_response_code(405); exit('Method not allowed'); }

$billId = (string)($_POST['id'] ?? '');
$o = null;
if ($billId !== '') {
    $st = db()->prepare('SELECT * FROM orders WHERE bill_code = ?');
    $st->execute([$billId]);
    $o = $st->fetch() ?: null;
}
payment_log($o ? (int)$o['id'] : null, 'callback', $_POST);

if (!$o) { http_response_code(404); exit('Order not found'); }
try {
    bp_sync_order($o, 'callback');
} catch (Throwable $ex) {
    error_log('callback verify: ' . $ex->getMessage());
    http_response_code(500); exit('Verify failed'); // Billplz akan cuba semula
}
echo 'OK';
