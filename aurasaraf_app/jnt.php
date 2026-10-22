<?php
declare(strict_types=1);
/**
 * Integrasi J&T Express Open Platform — Malaysia (ylopen.jtexpress.my/apiDoc).
 * Ringkasan dokumentasi: aurasaraf_app/docs/jnt/README.md.
 *
 * Semua panggilan: POST borang (x-www-form-urlencoded) dengan satu medan
 * bizContent = JSON, dan header apiAccount, timestamp (milisaat) serta digest.
 * digest = base64(md5(bizContent + privateKey)) — md5 dalam bentuk bait mentah.
 * bizContent yang ditandatangani MESTI rentetan yang sama dihantar.
 * Dalam bizContent, `password` ialah MD5(kata laluan biasa + "jadada369t3")
 * huruf besar (disahkan dengan data ujian rasmi: Sfx6H8d4 -> AA7EDDC3…).
 *
 * Aliran: tempah (order/addOrder) -> no. tracking (billCode) disimpan + pesanan
 * DIHANTAR -> label PDF (order/printOrder) -> status melalui webhook
 * (jnt-webhook.php, perlu langganan trace/subscribe) atau semakan semula
 * (logistics/trace).
 */

/** Kelayakan sandbox awam daripada dokumen J&T ("联调环境使用…文档中提供的固定数据"). */
const JNT_SANDBOX_CREDS = [
    'api_account' => '640826271705595946', 'private_key' => '8e88c8477d4e4939859c560192fcafbc',
    'customer_code' => 'ITTEST0001', 'password' => 'Sfx6H8d4',
];

/** Konfigurasi berkesan. Sandbox tanpa kelayakan sendiri guna akaun ujian awam J&T. */
function jnt_cfg(): array
{
    global $CONFIG;
    $c = $CONFIG['jnt'] ?? [];
    if ($c && !empty($c['sandbox'])) {
        foreach (JNT_SANDBOX_CREDS as $k => $v) if (empty($c[$k])) $c[$k] = $v;
    }
    return $c;
}

function jnt_enabled(): bool
{
    $c = jnt_cfg();
    return !empty($c['api_account']) && !empty($c['private_key']) && !empty($c['customer_code']) && !empty($c['password']);
}
function jnt_ready(): bool { return jnt_enabled() && ship_sender_ready(); }
function jnt_sandbox(): bool { return !empty(jnt_cfg()['sandbox']); }

function jnt_base(): string
{
    $c = jnt_cfg();
    if (!empty($c['api_base'])) return rtrim($c['api_base'], '/'); // untuk ujian/mock sahaja
    return !empty($c['sandbox']) ? 'https://demoopenapi.jtexpress.my/webopenplatformapi/api' : 'https://ylopenapi.jtexpress.my/webopenplatformapi/api';
}

function jnt_digest(string $bizContent): string
{
    return base64_encode(md5($bizContent . (string)(jnt_cfg()['private_key'] ?? ''), true));
}

/** Kata laluan dalam bizContent (bukan kata laluan biasa). */
function jnt_password(): string { return strtoupper(md5((string)(jnt_cfg()['password'] ?? '') . 'jadada369t3')); }

function jnt_log(?int $orderId, string $source, $payload): void
{
    db()->prepare('INSERT INTO jnt_logs (order_id, source, payload, ip) VALUES (?,?,?,?)')
        ->execute([$orderId, $source, mb_substr(is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 60000), client_ip()]);
}

/**
 * Panggil API. customerCode/password ditambah automatik. Pulangkan `data`.
 * Respons dicatat dalam jnt_logs tanpa password; fail PDF label diringkaskan.
 * @throws RuntimeException bila rangkaian gagal atau code bukan 1
 */
function jnt_request(string $path, array $biz = [], ?int $orderId = null): array
{
    $c   = jnt_cfg();
    $biz = ['customerCode' => (string)$c['customer_code'], 'password' => jnt_password()] + $biz;
    $json = json_encode($biz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init(jnt_base() . '/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['bizContent' => $json]), CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
            'apiAccount: ' . (string)$c['api_account'],
            'digest: ' . jnt_digest($json),
            'timestamp: ' . (int)floor(microtime(true) * 1000),
        ],
        CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $logReq = $biz; unset($logReq['password']);
    if ($resp === false) {
        jnt_log($orderId, $path . ':error', ['req' => $logReq, 'err' => $err]);
        throw new RuntimeException('J&T tidak dapat dihubungi: ' . $err);
    }
    $j = json_decode((string)$resp, true);
    $logRes = $j;
    if (isset($logRes['data']['base64EncodeContent']) && $logRes['data']['base64EncodeContent'] !== '') {
        $logRes['data']['base64EncodeContent'] = '[PDF ' . strlen((string)$logRes['data']['base64EncodeContent']) . ' aksara base64]';
    }
    jnt_log($orderId, $path, ['req' => $logReq, 'http' => $code, 'res' => $logRes ?? substr((string)$resp, 0, 500)]);
    if (!is_array($j)) throw new RuntimeException("Respons J&T tidak sah (HTTP {$code}).");
    if ((string)($j['code'] ?? '') !== '1') {
        throw new RuntimeException('J&T ralat ' . ($j['code'] ?? $j['status'] ?? '?') . ': ' . trim((string)($j['msg'] ?? $j['message'] ?? '')));
    }
    return is_array($j['data'] ?? null) ? $j['data'] : [];
}

/**
 * Semak kelayakan: tanya pesanan yang tidak wujud. Kelayakan betul pulang
 * code 1 tanpa data; salah pulang "customerCode or password is wrong" atau
 * ralat tandatangan header.
 */
function jnt_verify_account(): bool
{
    jnt_request('order/getOrders', ['txlogisticId' => 'SEMAK' . date('ymdHis')]);
    return true;
}

/* ---------- Kod status (scanTypeCode) ---------- */

function jnt_statuses(): array
{
    return [
        '200' => 'Diserah di kaunter J&T', '10' => 'Diambil kurier', '20' => 'Keluar dari hab', '30' => 'Tiba di hab',
        '94'  => 'Sedang dihantar',        '100' => 'Telah diterima', '110' => 'Masalah parcel',
        '172' => 'Sedang dipulangkan',     '173' => 'Dipulangkan ke pengirim',
        '300' => 'Rosak', '301' => 'Hilang', '302' => 'Dilupuskan', '303' => 'Ditolak penerima',
        '304' => 'Ditahan kastam', '305' => 'Tamat tempoh simpanan', '306' => 'Dilupuskan',
        '700' => 'Tiba di stesen pengambilan', '701' => 'Notis ambil dihantar', '702' => 'Diambil di stesen',
        '703' => 'Tidak diambil, dipulangkan', '704' => 'Diserahkan dari stesen',
        'cancel' => 'Dibatalkan',
    ];
}
function jnt_status_label(string $code, string $fallback = ''): string { return jnt_statuses()[$code] ?? $fallback; }

/** 100 = diterima penerima; 702 = diambil sendiri di stesen (POP). */
const JNT_DELIVERED_CODES = ['100', '702'];
const JNT_PROBLEM_CODES   = ['110', '172', '173', '300', '301', '302', '303', '304', '305', '306', '703'];

/* ---------- Kadar pos ---------- */

/**
 * Anggaran kos pos J&T (spmComCost/getComCost) dalam RM; cache 12 jam seperti SPX.
 * API ini perlu kebenaran pada akaun (akaun ujian awam tiada kebenaran), jadi
 * ia mungkin pulang null sehingga J&T membukanya — pemanggil guna kadar tetap.
 */
function jnt_quote(string $postcode, string $state, string $city, int $grams, float $value): ?float
{
    if (!jnt_ready() || !preg_match('/^\d{5}$/', $postcode)) return null;
    $grams = (int)(ceil($grams / 100) * 100);
    $hit = quote_cache_get('jnt', $postcode, $grams);
    if ($hit !== null) return $hit;

    $st = db()->prepare("SELECT COUNT(*) FROM jnt_logs WHERE source LIKE 'spmComCost/getComCost%' AND ip = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)");
    $st->execute([client_ip()]);
    if ((int)$st->fetchColumn() >= 20) return null;

    try {
        $d = jnt_request('spmComCost/getComCost', [
            'weight'   => max(0.01, min(30, round($grams / 1000, 2))),
            'sender'   => ['prov' => spx_state((string)setting('spx_sender_state')), 'city' => (string)setting('spx_sender_city'), 'area' => '',
                           'address' => (string)setting('spx_sender_address'), 'postCode' => (string)setting('spx_sender_postcode')],
            'receiver' => ['prov' => spx_state($state), 'city' => $city, 'area' => '', 'address' => $city, 'postCode' => $postcode],
        ]);
    } catch (Throwable $ex) {
        error_log('jnt_quote: ' . $ex->getMessage());
        return null;
    }
    $fee = $d['totalPrice'] ?? null;
    if (!is_numeric($fee) || (float)$fee <= 0) return null;
    return quote_cache_put('jnt', $postcode, $grams, (float)$fee);
}

/* ---------- Tempahan ---------- */

/** txlogisticId: order_no, kemudian order_no-N selepas batal (id lama memulangkan tracking lama). */
function jnt_ref(array $o, int $attempt): string
{
    return $attempt <= 1 ? $o['order_no'] : $o['order_no'] . '-' . $attempt;
}

function jnt_order_payload(array $o): array
{
    $items = order_items((int)$o['id']);
    $qty = []; $list = [];
    foreach ($items as $it) {
        if ($it['product_id']) $qty[(int)$it['product_id']] = ($qty[(int)$it['product_id']] ?? 0) + (int)$it['qty'];
    }
    $kg = max(0.01, round(order_weight_g($qty) / 1000, 2));
    $n  = max(1, array_sum(array_map(fn($it) => (int)$it['qty'], $items)));
    foreach ($items as $it) {
        $list[] = [
            'itemName' => mb_substr($it['name'], 0, 100), 'englishName' => mb_substr($it['name'], 0, 100),
            'number' => max(1, (int)$it['qty']), 'itemValue' => number_format(max(0.01, (float)$it['price']), 2, '.', ''),
            'weight' => number_format(max(0.01, $kg * (int)$it['qty'] / $n), 2, '.', ''), 'itemCurrency' => 'MYR',
        ];
    }
    if (!$list) $list[] = ['itemName' => 'Minyak urut', 'number' => 1, 'itemValue' => number_format(max(0.01, (float)$o['subtotal']), 2, '.', ''), 'weight' => (string)$kg, 'itemCurrency' => 'MYR'];

    return [
        'txlogisticId' => jnt_ref($o, (int)$o['jnt_attempt'] + 1),
        'actionType'   => 'add',
        'serviceType'  => setting('jnt_service') === '1' ? '1' : '6',
        'payType'      => in_array(setting('jnt_paytype'), ['PP_PM', 'PP_CASH'], true) ? (string)setting('jnt_paytype') : 'PP_PM',
        'expressType'  => 'EZ',
        'sender' => [
            'name' => mb_substr((string)setting('spx_sender_name'), 0, 100), 'phone' => norm_phone((string)setting('spx_sender_phone')),
            'countryCode' => 'MYS', 'address' => mb_substr((string)setting('spx_sender_address'), 0, 200), 'postCode' => (string)setting('spx_sender_postcode'),
        ],
        'receiver' => [
            'name' => mb_substr($o['customer_name'], 0, 100), 'phone' => norm_phone($o['phone']), 'countryCode' => 'MYS',
            'address' => mb_substr(trim($o['address'] . ', ' . $o['city']), 0, 200), 'postCode' => $o['postcode'],
        ],
        'items'       => $list,
        'packageInfo' => ['packageQuantity' => 1, 'weight' => number_format($kg, 2, '.', ''), 'packageValue' => number_format(max(0.01, (float)$o['subtotal']), 2, '.', ''), 'goodsType' => 'ITN8'],
        'remark'      => mb_substr($o['note'], 0, 200),
    ];
}

/**
 * Tempah J&T untuk pesanan berstatus 'paid' (satu panggilan bagi setiap pesanan;
 * J&T tiada tempahan pukal). Yang berjaya: no. tracking disimpan dan pesanan
 * ditanda DIHANTAR melalui order_mark_shipped(), kemudian dilanggan untuk webhook.
 * Pulang ['ok' => [id => billCode], 'fail' => [id => mesej]].
 */
function jnt_book(array $orderIds): array
{
    if (!jnt_ready()) throw new RuntimeException('J&T belum diset sepenuhnya (kelayakan API atau alamat pengirim).');
    $ok = []; $fail = [];
    foreach (array_slice(array_unique(array_map('intval', $orderIds)), 0, 100) as $id) {
        $o = order_find($id);
        if (!$o || $o['status'] !== 'paid') { $fail[$id] = 'Hanya pesanan DIBAYAR yang belum dipos boleh ditempah.'; continue; }
        try {
            // Jika respons hilang (timeout), tempah semula dengan txlogisticId yang
            // sama memulangkan billCode yang sama — selamat diulang.
            $d    = jnt_request('order/addOrder', jnt_order_payload($o), $id);
            $bill = trim((string)($d['billCode'] ?? ''));
            if ($bill === '') throw new RuntimeException('J&T tidak memulangkan no. tracking.');
        } catch (Throwable $ex) {
            $fail[$id] = $ex->getMessage();
            continue;
        }
        $fee = is_numeric($d['totalFreight'] ?? null) && (float)$d['totalFreight'] > 0 && (float)$d['totalFreight'] < 1000 ? round((float)$d['totalFreight'], 2) : null;
        db()->prepare("UPDATE orders SET jnt_attempt = jnt_attempt + 1, jnt_fee = ?, jnt_status_code = '', jnt_status = '', jnt_status_at = NULL WHERE id = ?")
            ->execute([$fee, $id]);
        order_mark_shipped($id, 'jnt', $bill);
        jnt_log($id, 'booked', ['billCode' => $bill, 'fee' => $fee]);
        try {
            jnt_request('trace/subscribe', ['list' => [['traceNode' => '10&20&30&94&100&110&172&173&200', 'billCode' => $bill]]], $id);
        } catch (Throwable $ex) {
            error_log('jnt subscribe: ' . $ex->getMessage()); // tidak kritikal: semakan semula masih berfungsi
        }
        $ok[$id] = $bill;
    }
    return ['ok' => $ok, 'fail' => $fail];
}

/**
 * Label (AWB) dalam bentuk bait PDF. J&T memulangkan PDF base64, atau pautan
 * PDF (urlContent) yang diambil di pelayan — hanya dari domain jtexpress.my.
 */
function jnt_label_pdf(array $o): string
{
    $d = jnt_request('order/printOrder', ['txlogisticId' => jnt_ref($o, (int)$o['jnt_attempt']), 'billCode' => $o['tracking_no']], (int)$o['id']);
    $pdf = base64_decode((string)($d['base64EncodeContent'] ?? ''), true) ?: '';
    if ($pdf === '' && ($url = (string)($d['urlContent'] ?? '')) !== '') {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !preg_match('/(^|\.)jtexpress\.my$/', $host)) throw new RuntimeException('Pautan label J&T tidak diterima.');
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_FOLLOWLOCATION => false]);
        $pdf = (string)curl_exec($ch);
        curl_close($ch);
    }
    if (!str_starts_with($pdf, '%PDF')) throw new RuntimeException('J&T tidak memulangkan label PDF.');
    return $pdf;
}

/** Batal parcel di J&T (hanya sebelum diambil/diserah). */
function jnt_cancel_remote(array $o): void
{
    jnt_request('order/cancelOrder', ['txlogisticId' => jnt_ref($o, (int)$o['jnt_attempt']), 'billCode' => $o['tracking_no'], 'reason' => 'Dibatalkan oleh kedai'], (int)$o['id']);
}

/** Batal tempahan J&T dan kembalikan pesanan ke DIBAYAR (pesanan tidak dibatalkan; stok tidak disentuh). */
function jnt_unbook(array $o): void
{
    if ($o['status'] !== 'shipped' || $o['courier'] !== 'jnt' || $o['tracking_no'] === '') {
        throw new RuntimeException('Pesanan ini tiada tempahan J&T yang aktif.');
    }
    jnt_cancel_remote($o);
    db()->prepare("UPDATE orders SET status = 'paid', courier = '', tracking_no = '', jnt_status_code = 'cancel', jnt_status = 'Cancelled', jnt_status_at = NOW()
                    WHERE id = ? AND status = 'shipped'")->execute([(int)$o['id']]);
    jnt_log((int)$o['id'], 'unbooked', ['billCode' => $o['tracking_no']]);
}

/* ---------- Status parcel ---------- */

/** Imbasan terkini daripada senarai details (ikut scanTime). */
function jnt_latest_scan(array $details): ?array
{
    $best = null;
    foreach ($details as $d) {
        if (!is_array($d) || !isset($d['scanTypeCode'])) continue;
        if ($best === null || strcmp((string)($d['scanTime'] ?? ''), (string)($best['scanTime'] ?? '')) > 0) $best = $d;
    }
    return $best;
}

/**
 * Rekod status parcel. Imbasan lebih lama daripada yang tersimpan diabaikan.
 * Diterima (100) atau diambil di stesen (702) menukar DIHANTAR kepada SELESAI.
 */
function jnt_apply_status(array $o, string $code, string $name, string $scanTime): void
{
    $code = substr(preg_replace('/\D/', '', $code), 0, 8);
    if ($code === '') return;
    $ts = strtotime($scanTime) ?: time();
    if ($o['jnt_status_at'] && strtotime($o['jnt_status_at']) > $ts) return;

    db()->prepare('UPDATE orders SET jnt_status_code = ?, jnt_status = ?, jnt_status_at = ? WHERE id = ?')
        ->execute([$code, mb_substr($name, 0, 60), date('Y-m-d H:i:s', $ts), (int)$o['id']]);

    if (in_array($code, JNT_DELIVERED_CODES, true) && $o['status'] === 'shipped') {
        db()->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND status = 'shipped'")->execute([(int)$o['id']]);
    }
    if ($code !== $o['jnt_status_code'] && in_array($code, JNT_PROBLEM_CODES, true)) {
        notify_owner('J&T: ' . jnt_status_label($code, $name) . ' — ' . $o['order_no'],
            "Parcel {$o['tracking_no']} untuk pesanan {$o['order_no']} kini: " . jnt_status_label($code, $name) . "\n\n" . url('admin/order.php?id=' . (int)$o['id']));
    }
}

/** Satu entri jejak J&T ({billCode, details[]}) => kemas kini pesanan. Pulang true jika ada imbasan. */
function jnt_apply_trace(array $o, array $trace): bool
{
    $s = jnt_latest_scan((array)($trace['details'] ?? []));
    if (!$s) return false;
    jnt_apply_status($o, (string)$s['scanTypeCode'], (string)($s['scanType'] ?? $s['scanTypeName'] ?? ''), (string)($s['scanTime'] ?? ''));
    return true;
}

/** Tarik jejak terkini (logistics/trace, satu parcel setiap panggilan). Pulang bilangan yang ada imbasan. */
function jnt_sync(array $orders): int
{
    $n = 0;
    foreach ($orders as $o) {
        if ($o['courier'] !== 'jnt' || $o['tracking_no'] === '') continue;
        try {
            $d = jnt_request('logistics/trace', ['billCode' => $o['tracking_no']], (int)$o['id']);
            $traces = isset($d['billCode']) ? [$d] : $d;
            foreach ($traces as $t) {
                if (is_array($t) && (string)($t['billCode'] ?? '') === $o['tracking_no'] && jnt_apply_trace($o, $t)) $n++;
            }
        } finally {
            db()->prepare('UPDATE orders SET jnt_synced_at = NOW() WHERE id = ?')->execute([(int)$o['id']]);
        }
    }
    return $n;
}
