<?php
/**
 * SALIN fail ini sebagai config.php dan isi nilai sebenar.
 * JANGAN commit config.php ke Git (sudah ada dalam .gitignore).
 */
return [
    // URL penuh ke folder public, TANPA slash di hujung.
    // XAMPP  : http://localhost/aurasaraf/public_html
    // Server : https://namadomain.com.my
    'base_url' => 'http://localhost/aurasaraf/public_html',

    'db' => [
        'host'    => 'localhost',
        'name'    => 'aurasaraf',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // Billplz. Biarkan secret_key kosong untuk matikan pilihan FPX.
    'billplz' => [
        'sandbox'       => true,   // true = billplz-sandbox.com, false = billplz.com (LIVE)
        'secret_key'    => '',     // API Secret Key (Settings > Keys & Integration)
        'collection_id' => '',     // ID collection (Billing > Collections)
    ],

    // SPX Express Open API. Biarkan kosong untuk matikan integrasi SPX.
    // app_id/app_secret: diberi PIC SPX melalui e-mel (satu set untuk ujian, satu untuk live).
    // user_id/user_secret: akaun penghantar SPX kedai (daripada SPX atau API Create Account).
    'spx' => [
        'sandbox'     => true,  // true = test-stable.spx.com.my, false = spx.com.my (LIVE)
        'app_id'      => '',
        'app_secret'  => '',
        'user_id'     => '',
        'user_secret' => '',
    ],

    // J&T Express Open Platform. Buang blok ini untuk matikan integrasi J&T.
    // sandbox => true dengan medan kosong guna akaun ujian awam J&T (tiada parcel sebenar).
    // LIVE: api_account/private_key di ylopen.jtexpress.my (Konsol -> Pusat Peribadi);
    // customer_code dan password (kata laluan biasa) diberi oleh cawangan J&T anda.
    'jnt' => [
        'sandbox'       => true,  // true = demoopenapi.jtexpress.my, false = ylopenapi.jtexpress.my (LIVE)
        'api_account'   => '',
        'private_key'   => '',
        'customer_code' => '',
        'password'      => '',
    ],

    'timezone' => 'Asia/Kuala_Lumpur',
    'debug'    => false, // true hanya semasa development
];
