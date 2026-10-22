<?php
/**
 * Buka AWB (label rasmi SPX) untuk satu atau beberapa pesanan: ?id=5 atau ?ids=5,6,7.
 * SPX memulangkan pautan PDF yang sah 30 minit / 10 kali buka, jadi pautan
 * tidak disimpan — setiap klik meminta yang baharu.
 */
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require_admin();

$ids = array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? $_GET['id'] ?? ''))));
$back = count($ids) === 1 ? 'admin/order.php?id=' . reset($ids) : 'admin/orders.php?status=shipped';
if (!$ids) redirect('admin/orders.php');
if (!spx_enabled()) { flash('error', 'SPX belum diset dalam config.php.'); redirect($back); }

$ph = implode(',', array_fill(0, count($ids), '?'));
$st = db()->prepare("SELECT tracking_no FROM orders WHERE id IN ($ph) AND courier = 'spx' AND tracking_no <> '' AND status IN ('shipped','completed')");
$st->execute(array_values($ids));
$tns = $st->fetchAll(PDO::FETCH_COLUMN);

if (!$tns) { flash('error', 'Tiada pesanan SPX yang aktif dalam pilihan ini.'); redirect($back); }
if (count($tns) > 30) { flash('error', 'SPX hadkan 30 AWB sekali cetak. Pilih kurang pesanan.'); redirect($back); }

try {
    redirect(spx_awb_link($tns));
} catch (Throwable $ex) {
    error_log('spx-awb: ' . $ex->getMessage());
    flash('error', $ex->getMessage());
    redirect($back);
}
