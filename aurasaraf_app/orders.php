<?php
declare(strict_types=1);

function order_no_generate(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $r = '';
        for ($i = 0; $i < 4; $i++) $r .= $chars[random_int(0, strlen($chars) - 1)];
        $no = 'AS' . date('ymd') . $r;
        $st = db()->prepare('SELECT 1 FROM orders WHERE order_no = ?');
        $st->execute([$no]);
    } while ($st->fetchColumn());
    return $no;
}
function order_find(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function order_find_by_no(string $no): ?array
{
    $st = db()->prepare('SELECT * FROM orders WHERE order_no = ?');
    $st->execute([$no]);
    return $st->fetch() ?: null;
}
function order_items(int $orderId): array
{
    $st = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $st->execute([$orderId]);
    return $st->fetchAll();
}
function order_public_url(array $o): string
{
    return url('order.php?no=' . rawurlencode($o['order_no']) . '&t=' . rawurlencode($o['access_token']));
}
/** Pautan invois/resit untuk pelanggan (guna access_token yang sama). */
function order_invoice_url(array $o): string
{
    return url('invoice.php?no=' . rawurlencode($o['order_no']) . '&t=' . rawurlencode($o['access_token']));
}

/**
 * Ubah stok + rekod pergerakan. MESTI dipanggil di dalam transaction.
 * $delta positif = masuk, negatif = keluar.
 *
 * Baki negatif ditolak secara lalai kerana ia mustahil secara fizikal dan
 * lazimnya berpunca daripada silap taip admin. $allowNegative dibuka HANYA
 * untuk jualan pesanan yang sudah dibayar — menolak pergerakan di situ
 * bermakna bayaran yang sudah diterima gagal direkod, jauh lebih buruk
 * daripada baki negatif yang memang jujur menunjukkan stok terlebih jual.
 */
function stock_move(int $productId, int $delta, string $type, array $meta = [], bool $allowNegative = false): int
{
    $pdo = db();
    $st = $pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
    $st->execute([$productId]);
    $cur = $st->fetchColumn();
    if ($cur === false) throw new RuntimeException('Produk tidak dijumpai.');
    $new = (int)$cur + $delta;
    if ($new < 0 && !$allowNegative) {
        throw new RuntimeException('Baki stok tidak mencukupi (baki ' . (int)$cur . ', diminta ' . abs($delta) . ').');
    }
    $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$new, $productId]);
    $pdo->prepare('INSERT INTO stock_movements
        (product_id, type, qty, balance_after, batch_no, expiry_date, unit_cost, supplier, note, order_id, admin_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([
        $productId, $type, $delta, $new,
        $meta['batch_no'] ?? null, $meta['expiry_date'] ?? null, $meta['unit_cost'] ?? null,
        $meta['supplier'] ?? null, $meta['note'] ?? null, $meta['order_id'] ?? null, $meta['admin_id'] ?? null,
    ]);
    return $new;
}

/** Tanda dibayar + tolak stok. Idempotent: selamat dipanggil berkali-kali (callback + return + admin). */
function order_mark_paid(int $orderId, string $ref = '', ?int $adminId = null): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $st->execute([$orderId]);
        $o = $st->fetch();
        if (!$o || !in_array($o['status'], ['new', 'pending_payment'], true)) { $pdo->rollBack(); return false; }

        if (!(int)$o['stock_deducted']) {
            foreach (order_items($orderId) as $it) {
                if ($it['product_id']) {
                    // allowNegative: bayaran sudah diterima, jadi pergerakan ini
                    // tidak boleh gagal walaupun stok sudah kehabisan sementara.
                    stock_move((int)$it['product_id'], -(int)$it['qty'], 'sale',
                        ['order_id' => $orderId, 'admin_id' => $adminId, 'note' => 'Pesanan ' . $o['order_no']], true);
                }
            }
        }
        $pdo->prepare("UPDATE orders SET status='paid', paid_at=NOW(), stock_deducted=1,
            payment_ref = IF(? <> '', ?, payment_ref) WHERE id = ?")->execute([$ref, $ref, $orderId]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    notify_owner('Bayaran diterima: ' . $o['order_no'],
        "Pesanan {$o['order_no']} telah DIBAYAR.\nJumlah: " . money($o['total']) . "\nRujukan: {$ref}\n\n" . url('admin/order.php?id=' . $orderId));
    notify_customer_paid($o);
    return true;
}

/**
 * Tanda pesanan DIHANTAR dan simpan kurier + no. tracking.
 *
 * Urutan status dikuatkuasakan di sini, bukan di halaman: hanya 'paid' (kali
 * pertama) atau 'shipped' (kemas kini tracking) dibenarkan. Diletakkan di sini
 * kerana lebih daripada satu skrin menandakan penghantaran — halaman pesanan
 * dan skrin pos pukal — dan peraturannya mesti sama di kedua-duanya.
 *
 * Tidak menyentuh stok: stok ditolak masa order_mark_paid(), bukan masa pos.
 */
function order_mark_shipped(int $orderId, string $courier, string $trackingNo): bool
{
    $o = order_find($orderId);
    if (!$o || !in_array($o['status'], ['paid', 'shipped'], true)) return false;

    $courier    = mb_substr($courier, 0, 60);
    $trackingNo = mb_substr(trim($trackingNo), 0, 60);
    db()->prepare("UPDATE orders SET status = 'shipped', courier = ?, tracking_no = ? WHERE id = ?")
        ->execute([$courier, $trackingNo, $orderId]);

    // E-mel pembeli hanya bila baru dipos, atau bila kurier/tracking berubah
    // (contoh: tracking ditambah kemudian atau dibetulkan). Simpan semula
    // nilai yang sama tidak menghantar e-mel berulang.
    if ($o['status'] === 'paid' || $o['courier'] !== $courier || $o['tracking_no'] !== $trackingNo) {
        notify_customer_shipped(['courier' => $courier, 'tracking_no' => $trackingNo] + $o);
    }
    return true;
}

/** Batal pesanan; pulangkan stok jika sudah ditolak. */
function order_cancel(int $orderId, ?int $adminId = null): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $st->execute([$orderId]);
        $o = $st->fetch();
        if (!$o || $o['status'] === 'cancelled') { $pdo->rollBack(); return false; }
        if ((int)$o['stock_deducted']) {
            foreach (order_items($orderId) as $it) {
                if ($it['product_id']) {
                    stock_move((int)$it['product_id'], (int)$it['qty'], 'return',
                        ['order_id' => $orderId, 'admin_id' => $adminId, 'note' => 'Batal ' . $o['order_no']]);
                }
            }
        }
        $pdo->prepare("UPDATE orders SET status='cancelled', stock_deducted=0 WHERE id = ?")->execute([$orderId]);
        $pdo->commit();
        return true;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

function payment_log(?int $orderId, string $source, $payload): void
{
    db()->prepare('INSERT INTO payment_logs (order_id, source, payload, ip) VALUES (?,?,?,?)')
        ->execute([$orderId, $source, is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE), client_ip()]);
}

/**
 * Hantar e-mel teks biasa. Gagal hantar tidak menjejaskan pesanan — hanya
 * dicatat dalam error_log. Alamat disahkan dengan filter_var dan subjek/nama
 * dikod base64 supaya input pelanggan tidak boleh menyuntik header.
 */
function send_mail(string $to, string $subject, string $body, string $replyTo = ''): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $host = parse_url(url(), PHP_URL_HOST) ?: 'localhost';
    $from = '=?UTF-8?B?' . base64_encode((string)setting('shop_name', 'Minyak Aura Saraf')) . '?=';
    $headers = "From: {$from} <no-reply@{$host}>\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0";
    $replyTo = trim($replyTo);
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers .= "\r\nReply-To: {$replyTo}";
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    if (!$ok) error_log('send_mail gagal ke ' . $to . ': ' . $subject);
    return $ok;
}

/** E-mel ringkas kepada pemilik (jika notify_email ditetapkan). */
function notify_owner(string $subject, string $body): void
{
    send_mail((string)setting('notify_email'), $subject, $body);
}

/**
 * E-mel pengesahan kepada pembeli apabila bayaran diterima. Dipanggil oleh
 * order_mark_paid() hanya pada peralihan pertama ke 'paid', jadi pembeli
 * menerima satu e-mel sahaja walaupun callback, return dan admin semuanya
 * mengesahkan bayaran yang sama. Pesanan tanpa e-mel dilangkau.
 */
function notify_customer_paid(array $o): void
{
    if (trim((string)$o['email']) === '') return;

    $lines = '';
    foreach (order_items((int)$o['id']) as $it) {
        $lines .= "  - {$it['name']} x {$it['qty']} = " . money($it['line_total']) . "\n";
    }
    $shop = (string)setting('shop_name', 'Minyak Aura Saraf');
    $wa   = trim((string)setting('whatsapp'));

    $body = "Salam {$o['customer_name']},\n\n"
          . "Terima kasih! Bayaran untuk pesanan anda telah kami terima.\n\n"
          . "No. pesanan : {$o['order_no']}\n"
          . "Cara bayaran: " . pay_label($o['payment_method']) . "\n\n"
          . "Item:\n{$lines}\n"
          . "Subtotal     : " . money($o['subtotal']) . "\n"
          . "Penghantaran : " . money($o['shipping']) . "\n"
          . "Jumlah       : " . money($o['total']) . "\n\n"
          . "Dihantar kepada:\n{$o['customer_name']} ({$o['phone']})\n{$o['address']}\n{$o['postcode']} {$o['city']}, {$o['state']}\n\n"
          . "Pesanan akan diproses dalam 1–2 hari bekerja. Nombor tracking boleh disemak di halaman status pesanan sebaik sahaja barang dipos.\n\n"
          . "Status pesanan : " . order_public_url($o) . "\n"
          . "Invois / resit : " . order_invoice_url($o) . "\n\n"
          . ($wa !== '' ? "Ada pertanyaan? WhatsApp kami di {$wa}.\n\n" : '')
          . "Terima kasih kerana membeli,\n{$shop}\n";

    send_mail((string)$o['email'], "Bayaran diterima — Pesanan {$o['order_no']}", $body, (string)setting('shop_email'));
}

/** E-mel kepada pembeli apabila pesanan dipos, dengan kurier dan no. tracking. */
function notify_customer_shipped(array $o): void
{
    if (trim((string)$o['email']) === '') return;

    $shop = (string)setting('shop_name', 'Minyak Aura Saraf');
    $wa   = trim((string)setting('whatsapp'));
    $cour = $o['courier'] !== '' ? courier_label($o['courier']) : '';
    $jejak = tracking_url($o['courier'], $o['tracking_no']);

    $info = '';
    if ($cour !== '')            $info .= "Kurier      : {$cour}\n";
    if ($o['tracking_no'] !== '') $info .= "No. tracking: {$o['tracking_no']}\n";
    if ($jejak !== '' && $o['tracking_no'] !== '') {
        $info .= tracking_is_direct($o['courier'])
            ? "Jejak       : {$jejak}\n"
            : "Jejak       : {$jejak}\n              (salin no. tracking di atas ke laman ini)\n";
    }
    if ($o['tracking_no'] === '') $info .= "No. tracking akan dimaklumkan sebaik sahaja tersedia.\n";

    $body = "Salam {$o['customer_name']},\n\n"
          . "Berita baik! Pesanan {$o['order_no']} anda telah dipos.\n\n"
          . $info . "\n"
          . "Dihantar kepada:\n{$o['customer_name']} ({$o['phone']})\n{$o['address']}\n{$o['postcode']} {$o['city']}, {$o['state']}\n\n"
          . "Status pesanan : " . order_public_url($o) . "\n\n"
          . ($wa !== '' ? "Ada pertanyaan? WhatsApp kami di {$wa}.\n\n" : '')
          . "Terima kasih kerana membeli,\n{$shop}\n";

    send_mail((string)$o['email'], "Pesanan {$o['order_no']} telah dipos", $body, (string)setting('shop_email'));
}
