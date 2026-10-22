<?php
/** Pelanggan dibawa balik ke sini oleh Billplz (GET: billplz[id], billplz[paid], billplz[paid_at], billplz[x_signature]). */
require __DIR__ . '/../aurasaraf_app/bootstrap.php';

$bp = is_array($_GET['billplz'] ?? null) ? $_GET['billplz'] : [];
$billId = (string)($bp['id'] ?? '');
$o = null;
if ($billId !== '') {
    $st = db()->prepare('SELECT * FROM orders WHERE bill_code = ?');
    $st->execute([$billId]);
    $o = $st->fetch() ?: null;
}
if (!$o) redirect('index.php');
payment_log((int)$o['id'], 'return', $_GET);
try {
    // Jangan percaya billplz[paid] dari URL: sahkan dengan API
    if (bp_sync_order($o, 'return')) flash('success', 'Bayaran berjaya diterima. Terima kasih!');
    elseif (($bp['paid'] ?? '') === 'false') flash('error', 'Bayaran tidak berjaya. Anda boleh cuba bayar semula.');
} catch (Throwable $ex) {
    error_log('return verify: ' . $ex->getMessage());
}
redirect(order_public_url($o));
