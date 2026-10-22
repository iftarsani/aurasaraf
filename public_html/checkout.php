<?php
require __DIR__ . '/../aurasaraf_app/bootstrap.php';

if (!is_post()) redirect('index.php#pesan');
csrf_check();

function back_with(array $errors): never
{
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = array_intersect_key($_POST, array_flip(
        ['customer_name', 'phone', 'email', 'address', 'postcode', 'city', 'state', 'note', 'payment_method', 'items']));
    redirect('index.php#pesan');
}

// Honeypot: bot isi medan tersembunyi
if (trim((string)($_POST['website'] ?? '')) !== '') redirect('index.php');

// Had kadar: maks 5 pesanan / 10 minit bagi satu IP
$st = db()->prepare('SELECT COUNT(*) FROM orders WHERE ip = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)');
$st->execute([client_ip()]);
if ((int)$st->fetchColumn() >= 5) back_with(['Terlalu banyak pesanan dalam masa singkat. Sila cuba sebentar lagi.']);

$in = array_map(fn($v) => is_string($v) ? trim($v) : $v, $_POST);
$errors = [];
$name    = mb_substr((string)($in['customer_name'] ?? ''), 0, 120);
$phone   = preg_replace('/[^\d+]/', '', (string)($in['phone'] ?? ''));
$email   = mb_substr((string)($in['email'] ?? ''), 0, 150);
$address = mb_substr((string)($in['address'] ?? ''), 0, 255);
$postcode= (string)($in['postcode'] ?? '');
$city    = mb_substr((string)($in['city'] ?? ''), 0, 80);
$state   = (string)($in['state'] ?? '');
$note    = mb_substr((string)($in['note'] ?? ''), 0, 500);
$method  = (string)($in['payment_method'] ?? '');

if (mb_strlen($name) < 3)                                   $errors[] = 'Sila isi nama penuh.';
if (!preg_match('/^\+?\d{9,13}$/', $phone))                 $errors[] = 'No. telefon tidak sah.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mel tidak sah.';
if (mb_strlen($address) < 8)                                $errors[] = 'Sila isi alamat penuh.';
if (!preg_match('/^\d{5}$/', $postcode))                    $errors[] = 'Poskod mesti 5 digit.';
if ($city === '')                                           $errors[] = 'Sila isi bandar.';
if (!isset(states()[$state]))                               $errors[] = 'Sila pilih negeri.';

if (!in_array($method, payment_methods(), true))                     $errors[] = 'Sila pilih cara bayaran.';

// Item: harga & stok diambil dari database, bukan dari borang
$wanted = [];
foreach ((array)($_POST['items'] ?? []) as $pid => $qty) {
    $qty = (int)$qty;
    if ($qty > 0) $wanted[(int)$pid] = min($qty, 99);
}
if (!$wanted) $errors[] = 'Sila pilih sekurang-kurangnya satu produk.';
if ($errors) back_with($errors);

$ph = implode(',', array_fill(0, count($wanted), '?'));
$st = db()->prepare("SELECT * FROM products WHERE active = 1 AND id IN ($ph)");
$st->execute(array_keys($wanted));
$lines = []; $subtotal = 0.0;
foreach ($st->fetchAll() as $p) {
    $q = $wanted[(int)$p['id']];
    if ($q > (int)$p['stock']) $errors[] = "Stok {$p['name']} tidak mencukupi (baki " . max(0, (int)$p['stock']) . ').';
    $lt = round($q * (float)$p['price'], 2);
    $lines[] = [$p['id'], $p['sku'], $p['name'] . ($p['size'] ? ' ' . $p['size'] : ''), $p['price'], $q, $lt];
    $subtotal += $lt;
}
if (count($lines) !== count($wanted)) $errors[] = 'Ada produk yang tidak lagi tersedia.';
if ($errors) back_with($errors);

$region   = states()[$state];
// Kadar tetap atau SPX — dikira semula di server, sama seperti shipping-quote.php.
[$shipping] = shipping_calc($state, $postcode, $city, $subtotal, order_weight_g($wanted));
$total    = round($subtotal + $shipping, 2);

// Resit (hanya untuk transfer) — disimpan di luar web root
$receipt = '';
if ($method === 'transfer') {
    try {
        $receipt = handle_upload('receipt', APP_PATH . '/storage/receipts', MIME_RECEIPT);
    } catch (RuntimeException $ex) {
        back_with([$ex->getMessage()]);
    }
    if ($receipt === '') back_with(['Sila muat naik resit bayaran untuk transfer / DuitNow QR.']);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $orderNo = order_no_generate();
    $token   = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO orders (order_no, access_token, customer_name, phone, email, address, postcode, city, state, region, note,
        subtotal, shipping, total, payment_method, status, receipt_file, ip) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$orderNo, $token, $name, $phone, $email, $address, $postcode, $city, $state, $region, $note,
            $subtotal, $shipping, $total, $method, $method === 'fpx' ? 'pending_payment' : 'new', $receipt ?: null, client_ip()]);
    $orderId = (int)$pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, sku, name, price, qty, line_total) VALUES (?,?,?,?,?,?,?)');
    foreach ($lines as $l) $ins->execute([$orderId, ...$l]);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    error_log('checkout: ' . $ex->getMessage());
    back_with(['Pesanan tidak dapat disimpan. Sila cuba lagi.']);
}

$order = order_find($orderId);
notify_owner('Pesanan baharu: ' . $orderNo,
    "Pesanan {$orderNo}\n{$name} ({$phone})\nJumlah: " . money($total) . "\nBayaran: " . pay_label($method) . "\n\n" . url('admin/order.php?id=' . $orderId));

if ($method === 'fpx') {
    try {
        $bill = bp_create_bill($order);
        $pdo->prepare('UPDATE orders SET bill_code = ? WHERE id = ?')->execute([$bill, $orderId]);
        payment_log($orderId, 'createBill', ['BillCode' => $bill]);
        redirect(bp_pay_url($bill));
    } catch (Throwable $ex) {
        error_log('Billplz: ' . $ex->getMessage());
        payment_log($orderId, 'createBill:error', $ex->getMessage());
        flash('error', 'Gerbang bayaran FPX tidak dapat dihubungi. Pesanan anda telah direkodkan; sila hubungi kami melalui WhatsApp untuk bayaran.');
    }
}
redirect(order_public_url($order));
