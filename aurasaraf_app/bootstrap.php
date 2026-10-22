<?php
declare(strict_types=1);

define('APP_PATH', __DIR__);
define('PUBLIC_PATH', dirname(__DIR__) . '/public_html');

$configFile = APP_PATH . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('config.php belum wujud. Salin config.sample.php kepada config.php dan isi maklumat database.');
}
$CONFIG = require $configFile;

date_default_timezone_set($CONFIG['timezone'] ?? 'Asia/Kuala_Lumpur');
error_reporting(E_ALL);
ini_set('display_errors', !empty($CONFIG['debug']) ? '1' : '0');
ini_set('log_errors', '1');

// Sesi selamat
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('ASSESS');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $https,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    // Simpan sesi dalam folder sendiri (luar web root). Tetapan save_path hosting kadang-kadang
    // rosak selepas tukar versi PHP di cPanel — sesi hilang setiap request dan semua borang
    // gagal CSRF. GC dihidupkan kerana sesetengah server matikan pembersihan sesi lalai.
    $sessDir = APP_PATH . '/storage/sessions';
    if (!is_dir($sessDir)) @mkdir($sessDir, 0700, true);
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.gc_maxlifetime', '7200');
    }
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        global $CONFIG;
        $d = $CONFIG['db'];
        $dsn = "mysql:host={$d['host']};dbname={$d['name']};charset=" . ($d['charset'] ?? 'utf8mb4');
        try {
            $pdo = new PDO($dsn, $d['user'], $d['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $ex) {
            error_log('DB connect: ' . $ex->getMessage());
            http_response_code(500);
            exit('Tidak dapat sambung ke database. Semak config.php.');
        }
    }
    return $pdo;
}

require APP_PATH . '/helpers.php';
require APP_PATH . '/auth.php';
require APP_PATH . '/orders.php';
require APP_PATH . '/billplz.php';
require APP_PATH . '/spx.php';
require APP_PATH . '/jnt.php';
