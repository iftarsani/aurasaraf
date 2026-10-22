<?php
declare(strict_types=1);
/**
 * Integrasi Billplz API v3 (https://www.billplz.com/api)
 * Aliran: cipta bil -> pelanggan bayar -> callback (server) + redirect (browser)
 * Status bayaran SENTIASA disahkan semula melalui GET /v3/bills/{id}, bukan percaya parameter POST/GET.
 */

function bp_cfg(): array { global $CONFIG; return $CONFIG['billplz'] ?? []; }
function bp_enabled(): bool { $c = bp_cfg(); return !empty($c['secret_key']) && !empty($c['collection_id']); }
function bp_base(): string
{
    $c = bp_cfg();
    if (!empty($c['api_base'])) return rtrim($c['api_base'], '/'); // untuk ujian/mock sahaja
    return !empty($c['sandbox']) ? 'https://www.billplz-sandbox.com' : 'https://www.billplz.com';
}
function bp_pay_url(string $billId): string { return bp_base() . '/bills/' . rawurlencode($billId); }

/** Panggil API v3. Auth: HTTP Basic, secret key sebagai username, kata laluan kosong. */
function bp_request(string $method, string $path, array $data = []): array
{
    $ch = curl_init(bp_base() . '/api/v3/' . ltrim($path, '/'));
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => (bp_cfg()['secret_key'] ?? '') . ':',
        CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true,
    ];
    if ($method === 'POST') $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) throw new RuntimeException('Billplz tidak dapat dihubungi: ' . $err);
    $json = json_decode((string)$body, true);
    if (!is_array($json)) throw new RuntimeException("Respons Billplz tidak sah (HTTP {$code}): " . substr((string)$body, 0, 200));
    if ($code >= 400 || isset($json['error'])) throw new RuntimeException("Billplz ralat (HTTP {$code}): " . json_encode($json['error'] ?? $json, JSON_UNESCAPED_UNICODE));
    return $json;
}

/** Cipta bil untuk pesanan. Pulangkan ID bil. */
function bp_create_bill(array $order): string
{
    $c = bp_cfg();
    $email = $order['email'] ?: (setting('notify_email') ?: 'pelanggan@' . (parse_url(url(), PHP_URL_HOST) ?: 'example.com'));

    $res = bp_request('POST', 'bills', [
        'collection_id'     => $c['collection_id'],
        'email'             => $email,
        'mobile'            => norm_phone($order['phone']),
        'name'              => mb_substr(trim($order['customer_name']), 0, 255),
        'amount'            => (int)round((float)$order['total'] * 100), // dalam sen
        'description'       => mb_substr(setting('shop_name', 'Minyak Aura Saraf') . ' pesanan ' . $order['order_no'], 0, 200),
        'callback_url'      => url('payment-callback.php'),
        'redirect_url'      => url('payment-return.php'),
        'reference_1_label' => 'No. Pesanan',
        'reference_1'       => $order['order_no'],
    ]);
    $id = (string)($res['id'] ?? '');
    if ($id === '') throw new RuntimeException('Billplz gagal cipta bil: ' . json_encode($res));
    return $id;
}

/**
 * Sahkan bayaran terus dengan Billplz.
 * Pulang ['paid'=>bool, 'ref'=>string, 'amount'=>float, 'raw'=>array]
 */
function bp_verify(array $order): array
{
    if (empty($order['bill_code'])) return ['paid' => false, 'ref' => '', 'amount' => 0.0, 'raw' => []];
    $b = bp_request('GET', 'bills/' . rawurlencode($order['bill_code']));
    $okStatus = ($b['paid'] ?? false) === true && ($b['state'] ?? '') === 'paid';
    $okBill   = (string)($b['id'] ?? '') === (string)$order['bill_code']
             && (string)($b['collection_id'] ?? '') === (string)(bp_cfg()['collection_id'] ?? '');
    $okRef    = (string)($b['reference_1'] ?? '') === $order['order_no'];
    $amount   = (int)($b['paid_amount'] ?? 0) / 100;
    // Jumlah dibayar mesti >= jumlah pesanan
    if ($okStatus && $okBill && $okRef && $amount + 0.001 >= (float)$order['total']) {
        return ['paid' => true, 'ref' => (string)$b['id'], 'amount' => $amount, 'raw' => $b];
    }
    return ['paid' => false, 'ref' => '', 'amount' => 0.0, 'raw' => $b];
}

/** Semak + kemas kini pesanan jika sudah dibayar. Pulang true jika pesanan kini berstatus dibayar. */
function bp_sync_order(array $order, string $source): bool
{
    if (!in_array($order['status'], ['new', 'pending_payment'], true)) {
        return in_array($order['status'], ['paid', 'shipped', 'completed'], true);
    }
    $v = bp_verify($order);
    payment_log((int)$order['id'], 'verify:' . $source, $v['raw']);
    if ($v['paid']) { order_mark_paid((int)$order['id'], $v['ref']); return true; }
    return false;
}
