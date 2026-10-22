<?php
/**
 * Anggaran kos pos untuk ringkasan checkout (JSON). Paparan sahaja — checkout.php
 * mengira semula melalui shipping_calc() yang sama, jadi angka ini tidak dipercayai.
 * GET: state, postcode, city, items[id]=qty
 */
require __DIR__ . '/../aurasaraf_app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$state    = (string)($_GET['state'] ?? '');
$postcode = (string)($_GET['postcode'] ?? '');
$city     = mb_substr(trim((string)($_GET['city'] ?? '')), 0, 80);

$wanted = [];
foreach ((array)($_GET['items'] ?? []) as $pid => $qty) {
    $qty = (int)$qty;
    if ($qty > 0) $wanted[(int)$pid] = min($qty, 99);
}
if (!isset(states()[$state]) || !$wanted) { echo json_encode(['fee' => null]); exit; }

$ph = implode(',', array_fill(0, count($wanted), '?'));
$st = db()->prepare("SELECT id, price FROM products WHERE active = 1 AND id IN ($ph)");
$st->execute(array_keys($wanted));
$subtotal = 0.0; $found = [];
foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $id => $price) {
    $subtotal += round($wanted[$id] * (float)$price, 2);
    $found[$id] = $wanted[$id];
}
if (!$found) { echo json_encode(['fee' => null]); exit; }

// Tanpa poskod sah, kurier tidak boleh dianggar: pulangkan kadar tetap sebagai paparan sementara.
if (!preg_match('/^\d{5}$/', $postcode)) $postcode = '';
[$fee, $src] = shipping_calc($state, $postcode, $city, $subtotal, order_weight_g($found));
echo json_encode(['fee' => $fee, 'source' => $src, 'needPostcode' => $postcode === '' && rate_courier() !== '']);
