<?php
declare(strict_types=1);
/**
 * Integrasi SPX Express (Shopee Xpress) Open API — Malaysia.
 * Dokumentasi: aurasaraf_app/docs/spx/ (salinan daripada spx.com.my/integration).
 *
 * Semua panggilan: POST JSON ke {host}/open/api/v1/..., dengan header app-id,
 * timestamp (saat), random-num dan check-sign. check-sign ialah
 * HMAC-SHA256(app_secret, "{app_id}_{timestamp}_{random}_{body}") dalam hex.
 * Body yang ditandatangani MESTI bait yang sama dihantar — jadi JSON dikod
 * sekali sahaja dan rentetan itu digunakan untuk kedua-duanya.
 *
 * Aliran: tempah (batch_create_order) -> no. tracking disimpan + pesanan
 * DIHANTAR -> AWB (batch_get_shipping_label) -> status dikemas kini melalui
 * webhook (spx-webhook.php) atau semakan semula (batch_search_order).
 */

function spx_cfg(): array { global $CONFIG; return $CONFIG['spx'] ?? []; }

/** Kelayakan API lengkap: app (platform) + user (akaun penghantar). */
function spx_enabled(): bool
{
    $c = spx_cfg();
    return !empty($c['app_id']) && !empty($c['app_secret']) && !empty($c['user_id']) && !empty($c['user_secret']);
}

function spx_ready(): bool { return spx_enabled() && ship_sender_ready(); }

function spx_base(): string
{
    $c = spx_cfg();
    if (!empty($c['api_base'])) return rtrim($c['api_base'], '/'); // untuk ujian/mock sahaja
    return !empty($c['sandbox']) ? 'https://test-stable.spx.com.my' : 'https://spx.com.my';
}

function spx_sign(string $body, int $timestamp, int $random): string
{
    $c = spx_cfg();
    return hash_hmac('sha256', sprintf('%d_%d_%d_%s', (int)($c['app_id'] ?? 0), $timestamp, $random, $body), (string)($c['app_secret'] ?? ''));
}

function spx_log(?int $orderId, string $source, $payload): void
{
    db()->prepare('INSERT INTO spx_logs (order_id, source, payload, ip) VALUES (?,?,?,?)')
        ->execute([$orderId, $source, mb_substr(is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 60000), client_ip()]);
}

/**
 * Panggil API. user_id/user_secret ditambah automatik. Pulangkan `data`.
 * Respons penuh dicatat dalam spx_logs; user_secret tidak pernah dicatat.
 * @throws RuntimeException bila rangkaian gagal atau ret_code bukan 0
 */
function spx_request(string $path, array $data = [], ?int $orderId = null): array
{
    $c = spx_cfg();
    $data = ['user_id' => (int)$c['user_id'], 'user_secret' => (string)$c['user_secret']] + $data;
    $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ts   = time();
    $rand = random_int(1, PHP_INT_MAX);

    $ch = curl_init(spx_base() . '/open/api/v1/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'app-id: ' . (int)$c['app_id'],
            'timestamp: ' . $ts,
            'random-num: ' . $rand,
            'check-sign: ' . spx_sign($body, $ts, $rand),
        ],
        CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $logReq = $data; unset($logReq['user_id'], $logReq['user_secret']);
    if ($resp === false) {
        spx_log($orderId, $path . ':error', ['req' => $logReq, 'err' => $err]);
        throw new RuntimeException('SPX tidak dapat dihubungi: ' . $err);
    }
    $json = json_decode((string)$resp, true);
    spx_log($orderId, $path, ['req' => $logReq, 'http' => $code, 'res' => $json ?? substr((string)$resp, 0, 500)]);
    if (!is_array($json)) throw new RuntimeException("Respons SPX tidak sah (HTTP {$code}).");
    $rc = (int)($json['ret_code'] ?? $json['retcode'] ?? -1);
    if ($rc !== 0) throw new RuntimeException('SPX ralat ' . $rc . ': ' . trim((string)($json['message'] ?? '')));
    return is_array($json['data'] ?? null) ? $json['data'] : [];
}

/** Semak kelayakan akaun (3.2 Check Account Credentials). */
function spx_verify_account(): bool
{
    return (bool)(spx_request('account/verify')['match_result'] ?? false);
}

/* ---------- Kod status parcel ---------- */

/** Kod status SPX => label Melayu. */
function spx_statuses(): array
{
    return [
        '1001' => 'Menunggu pickup / drop-off', '2001' => 'Dalam transit', '2006' => 'Sedang dihantar',
        '3001' => 'Ditahan',       '4001' => 'Telah diterima',     '5001' => 'Pickup gagal',
        '5002' => 'Rosak',         '5003' => 'Hilang',             '6001' => 'Sedang dipulangkan',
        '6002' => 'Pemulangan gagal', '6003' => 'Dipulangkan ke pengirim', '7001' => 'Dibatalkan',
    ];
}
function spx_status_label(string $code, string $fallback = ''): string { return spx_statuses()[$code] ?? $fallback; }

/** Kod yang memerlukan perhatian pemilik — e-mel dihantar bila pertama kali muncul. */
const SPX_PROBLEM_CODES = ['3001', '5001', '5002', '5003', '6001', '6002', '6003'];

/* ---------- Berat & alamat ---------- */

/** Jumlah berat pesanan dalam gram: berat produk × kuantiti + bungkusan. */
function order_weight_g(array $productQty): int
{
    $g = 0;
    if ($productQty) {
        $ph = implode(',', array_fill(0, count($productQty), '?'));
        $st = db()->prepare("SELECT id, weight_g FROM products WHERE id IN ($ph)");
        $st->execute(array_keys($productQty));
        foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $id => $w) $g += max(0, (int)$w) * (int)$productQty[$id];
    }
    return max(1, $g + max(0, (int)setting('spx_pack_g', 50)));
}

/** Nama negeri untuk SPX: buang awalan "W.P." yang tiada dalam fail alamat kurier. */
function spx_state(string $state): string { return trim(preg_replace('/^W\.P\.\s*/', '', $state)); }

/** Telefon format SPX MY: 60 + nombor, 10–12 digit. */
function spx_phone(string $p): string { return norm_phone($p); }

/* ---------- Kadar pos ---------- */

/**
 * Anggaran kos pos SPX (2.1 Check Shipping Fee) untuk satu destinasi, dalam RM.
 * Dicache 12 jam ikut poskod + berat (dibundar ke 100g) supaya borang checkout
 * tidak memanggil SPX setiap kali kuantiti ditukar, dan supaya harga yang
 * dipapar sama dengan yang dicaj semasa hantar pesanan.
 * parcel_value wajib walaupun dokumen tandakan pilihan (sandbox pulang 11001 tanpanya).
 * Pulang null jika SPX gagal — pemanggil mesti guna kadar tetap.
 */
function spx_quote(string $postcode, string $state, string $city, int $grams, float $value): ?float
{
    if (!spx_ready() || !preg_match('/^\d{5}$/', $postcode)) return null;
    $grams = (int)(ceil($grams / 100) * 100);

    $hit = quote_cache_get('spx', $postcode, $grams);
    if ($hit !== null) return $hit;

    // Had kadar per IP untuk panggilan yang tidak kena cache — tanpa ini sesiapa
    // boleh mengitar semua poskod dan menghabiskan kuota API kedai.
    $st = db()->prepare("SELECT COUNT(*) FROM spx_logs WHERE source LIKE 'order/batch_check_order%' AND ip = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)");
    $st->execute([client_ip()]);
    if ((int)$st->fetchColumn() >= 20) return null;

    try {
        $d = spx_request('order/batch_check_order', ['orders' => [[
            'base_info'        => ['service_type' => 1],
            'sender_info'      => [
                'sender_state' => spx_state((string)setting('spx_sender_state')), 'sender_city' => (string)setting('spx_sender_city'),
                'sender_post_code' => (string)setting('spx_sender_postcode'), 'sender_detail_address' => (string)setting('spx_sender_address'),
            ],
            'fulfillment_info' => ['collect_type' => spx_collect_type()],
            'deliver_info'     => ['deliver_state' => spx_state($state), 'deliver_city' => $city, 'deliver_post_code' => $postcode],
            'parcel_info'      => ['parcel_weight' => round($grams / 1000, 2), 'parcel_value' => round(max($value, 1), 2)],
        ]]]);
    } catch (Throwable $ex) {
        error_log('spx_quote: ' . $ex->getMessage());
        return null;
    }
    $fee = $d['orders'][0]['estimated_shipping_fee'] ?? null;
    if (!is_numeric($fee) || (float)$fee <= 0) return null;

    return quote_cache_put('spx', $postcode, $grams, (float)$fee);
}

/** 1 = kurier datang ambil, 2 = drop-off di kaunter (lalai). */
function spx_collect_type(): int { return setting('spx_collect') === '1' ? 1 : 2; }

/* ---------- Tempahan ---------- */

/** order_id yang dihantar ke SPX. Cubaan kedua dst. (selepas batal) diberi akhiran kerana SPX menolak order_id berulang. */
function spx_order_ref(array $o, int $attempt): string
{
    return $attempt <= 1 ? $o['order_no'] : $o['order_no'] . '-' . $attempt;
}

/** Slot pickup terawal (1.1 Get Pickup Timeslot). */
function spx_first_pickup(): array
{
    foreach (spx_request('order/get_pickup_time', ['service_type' => 1]) as $day) {
        foreach ($day['slots'] ?? [] as $slot) {
            return ['pickup_time' => (int)$day['pickup_time'], 'pickup_time_range_id' => (int)$slot['pickup_time_range_id']];
        }
    }
    throw new RuntimeException('SPX tiada slot pickup tersedia. Tukar ke drop-off dalam Tetapan atau cuba esok.');
}

function spx_order_payload(array $o, array $fulfil): array
{
    $items = order_items((int)$o['id']);
    $qty = []; $names = []; $units = 0;
    foreach ($items as $it) {
        if ($it['product_id']) $qty[(int)$it['product_id']] = ($qty[(int)$it['product_id']] ?? 0) + (int)$it['qty'];
        $names[] = $it['name'] . ' x' . (int)$it['qty'];
        $units  += (int)$it['qty'];
    }
    return [
        'order_id'  => spx_order_ref($o, (int)$o['spx_attempt'] + 1),
        'base_info' => ['service_type' => 1],
        'sender_info' => [
            'sender_post_code'      => (string)setting('spx_sender_postcode'),
            'sender_name'           => mb_substr((string)setting('spx_sender_name'), 0, 64),
            'sender_phone'          => spx_phone((string)setting('spx_sender_phone')),
            'sender_detail_address' => mb_substr((string)setting('spx_sender_address'), 0, 256),
        ],
        'fulfillment_info' => ['payment_role' => 1, 'cod_collection' => 0, 'collect_type' => spx_collect_type()] + $fulfil,
        'deliver_info' => [
            'deliver_state'          => spx_state($o['state']),
            'deliver_city'           => mb_substr($o['city'], 0, 64),
            'deliver_post_code'      => $o['postcode'],
            'deliver_name'           => mb_substr($o['customer_name'], 0, 64),
            'deliver_phone'          => spx_phone($o['phone']),
            'deliver_detail_address' => mb_substr($o['address'], 0, 256),
            'deliver_instruction'    => mb_substr($o['note'], 0, 256),
        ],
        'parcel_info' => [
            'parcel_weight'        => round(order_weight_g($qty) / 1000, 2),
            'parcel_item_name'     => mb_substr(implode(', ', $names) ?: 'Minyak urut', 0, 256),
            'parcel_item_quantity' => max(1, $units),
            'parcel_value'         => round((float)$o['subtotal'], 2),
        ],
    ];
}

/**
 * Tempah penghantaran SPX untuk pesanan berstatus 'paid' (maks 100 sekali).
 * Setiap yang berjaya: no. tracking disimpan dan pesanan ditanda DIHANTAR
 * melalui order_mark_shipped() — e-mel pembeli ikut aliran biasa.
 * Pulang ['ok' => [id => tracking], 'fail' => [id => mesej]].
 */
function spx_book(array $orderIds): array
{
    if (!spx_ready()) throw new RuntimeException('SPX belum diset sepenuhnya (kelayakan API atau alamat pengirim).');
    $orders = []; $fail = [];
    foreach (array_slice(array_unique(array_map('intval', $orderIds)), 0, 100) as $id) {
        $o = order_find($id);
        if (!$o || $o['status'] !== 'paid') { $fail[$id] = 'Hanya pesanan DIBAYAR yang belum dipos boleh ditempah.'; continue; }
        $orders[$id] = $o;
    }
    if (!$orders) return ['ok' => [], 'fail' => $fail];

    $fulfil = spx_collect_type() === 1 ? spx_first_pickup() : [];
    $byRef = []; $payload = [];
    foreach ($orders as $id => $o) {
        $p = spx_order_payload($o, $fulfil);
        $byRef[$p['order_id']] = $id;
        $payload[] = $p;
    }
    $d = spx_request('order/batch_create_order', ['orders' => $payload], count($orders) === 1 ? array_key_first($orders) : null);

    $ok = [];
    foreach ($d['orders'] ?? [] as $r) {
        $id = $byRef[(string)($r['order_id'] ?? '')] ?? null;
        $tn = trim((string)($r['tracking_no'] ?? ''));
        if (!$id || $tn === '') continue;
        $fee = is_numeric($r['estimated_shipping_fee'] ?? null) ? round((float)$r['estimated_shipping_fee'], 2) : null;
        db()->prepare("UPDATE orders SET spx_attempt = spx_attempt + 1, spx_fee = ?, spx_status_code = '', spx_status = '', spx_status_at = NULL WHERE id = ?")
            ->execute([$fee, $id]);
        order_mark_shipped($id, 'spx', $tn);
        spx_log($id, 'booked', ['tracking_no' => $tn, 'fee' => $fee]);
        $ok[$id] = $tn;
    }
    foreach ($d['fail_list'] ?? [] as $f) {
        $id = $byRef[(string)($f['order_id'] ?? '')] ?? null;
        if (!$id) continue;
        $msg = trim((string)($f['message'] ?? '') . ' ' . (string)($f['debug_msg'] ?? ''));
        // order_id sudah wujud di SPX: tempahan sebelum ini berjaya tetapi respons
        // tidak sampai (contoh: timeout). Naikkan cubaan supaya admin boleh semak
        // di portal SPX; tempahan semula akan guna order_id baharu.
        if ((int)($f['ret_code'] ?? 0) === 13103) {
            db()->prepare('UPDATE orders SET spx_attempt = spx_attempt + 1 WHERE id = ?')->execute([$id]);
            $msg = 'Pesanan ini sudah ditempah di SPX sebelum ini (' . $orders[$id]['order_no'] . '). Semak portal SPX sebelum tempah semula.';
        }
        $fail[$id] = $msg !== '' ? $msg : 'Ditolak oleh SPX (kod ' . (int)($f['ret_code'] ?? 0) . ').';
    }
    foreach ($orders as $id => $o) if (!isset($ok[$id]) && !isset($fail[$id])) $fail[$id] = 'Tiada jawapan SPX untuk pesanan ini.';
    return ['ok' => $ok, 'fail' => $fail];
}

/** Pautan AWB (sah 30 minit, maks 10 kali buka). Hanya pautan ke domain SPX diterima. */
function spx_awb_link(array $trackingNos): string
{
    $d = spx_request('order/batch_get_shipping_label', ['tracking_no_list' => array_values($trackingNos)]);
    $link = (string)($d['awb_link'] ?? '');
    $host = strtolower((string)parse_url($link, PHP_URL_HOST));
    if (parse_url($link, PHP_URL_SCHEME) !== 'https' || !preg_match('/(^|\.)spx\.com\.my$/', $host)) {
        $why = $d['fail_list'][0]['message'] ?? 'pautan AWB tidak diterima';
        throw new RuntimeException('SPX: ' . $why);
    }
    return $link;
}

/** Batal parcel di SPX (hanya boleh sebelum pickup/drop-off). */
function spx_cancel_remote(array $o): void
{
    $d = spx_request('order/batch_cancel_order', ['tracking_no_list' => [$o['tracking_no']]], (int)$o['id']);
    if (!in_array($o['tracking_no'], $d['tracking_no_list'] ?? [], true)) {
        throw new RuntimeException('SPX tidak membatalkan parcel: ' . ($d['fail_list'][0]['message'] ?? 'sebab tidak diberi'));
    }
}

/**
 * Batal tempahan SPX dan kembalikan pesanan ke DIBAYAR supaya boleh ditempah
 * semula (contoh: alamat salah). Pesanan tidak dibatalkan; stok tidak disentuh.
 */
function spx_unbook(array $o): void
{
    if ($o['status'] !== 'shipped' || $o['courier'] !== 'spx' || $o['tracking_no'] === '') {
        throw new RuntimeException('Pesanan ini tiada tempahan SPX yang aktif.');
    }
    spx_cancel_remote($o);
    db()->prepare("UPDATE orders SET status = 'paid', courier = '', tracking_no = '', spx_status_code = '7001', spx_status = 'Cancelled', spx_status_at = NOW()
                    WHERE id = ? AND status = 'shipped'")->execute([(int)$o['id']]);
    spx_log((int)$o['id'], 'unbooked', ['tracking_no' => $o['tracking_no']]);
}

/* ---------- Status parcel ---------- */

/**
 * Rekod status parcel. Status lebih lama daripada yang tersimpan diabaikan
 * (webhook boleh sampai tidak mengikut urutan). Parcel diterima (4001)
 * menukar pesanan DIHANTAR kepada SELESAI.
 */
function spx_apply_status(array $o, string $code, string $status, int $ts): void
{
    $code = substr(preg_replace('/\D/', '', $code), 0, 8);
    if ($code === '') return;
    $at = date('Y-m-d H:i:s', $ts > 0 ? $ts : time());
    if ($o['spx_status_at'] && strtotime($o['spx_status_at']) > strtotime($at)) return;

    db()->prepare('UPDATE orders SET spx_status_code = ?, spx_status = ?, spx_status_at = ? WHERE id = ?')
        ->execute([$code, mb_substr($status, 0, 60), $at, (int)$o['id']]);

    if ($code === '4001' && $o['status'] === 'shipped') {
        db()->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND status = 'shipped'")->execute([(int)$o['id']]);
    }
    if ($code !== $o['spx_status_code'] && in_array($code, SPX_PROBLEM_CODES, true)) {
        notify_owner('SPX: ' . spx_status_label($code, $status) . ' — ' . $o['order_no'],
            "Parcel {$o['tracking_no']} untuk pesanan {$o['order_no']} kini: " . spx_status_label($code, $status) . "\n\n" . url('admin/order.php?id=' . (int)$o['id']));
    }
}

/** Tarik status terkini dari SPX (1.3 Track Order) untuk pesanan SPX yang diberi. */
function spx_sync(array $orders): int
{
    $byTn = [];
    foreach ($orders as $o) if ($o['courier'] === 'spx' && $o['tracking_no'] !== '') $byTn[$o['tracking_no']] = $o;
    if (!$byTn) return 0;
    $n = 0;
    foreach (array_chunk($byTn, 100, true) as $chunk) {
        $d = spx_request('order/batch_search_order', ['tracking_no_list' => array_keys($chunk)], count($chunk) === 1 ? (int)reset($chunk)['id'] : null);
        foreach ($d['orders'] ?? [] as $r) {
            $o = $chunk[(string)($r['tracking_no'] ?? '')] ?? null;
            if (!$o) continue;
            $ts = 0;
            foreach ($r['routes'] ?? [] as $rt) $ts = max($ts, (int)($rt['timestamp'] ?? 0));
            spx_apply_status($o, (string)($r['status_code'] ?? ''), (string)($r['status'] ?? ''), $ts);
            $n++;
        }
        $ids = implode(',', array_map(fn($o) => (int)$o['id'], $chunk));
        db()->exec("UPDATE orders SET spx_synced_at = NOW() WHERE id IN ($ids)");
    }
    return $n;
}
