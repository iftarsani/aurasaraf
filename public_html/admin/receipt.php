<?php
/** Papar resit pelanggan (disimpan di luar web root) — admin sahaja. */
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
require_admin();
$o = order_find((int)($_GET['id'] ?? 0));
$file = $o ? basename((string)$o['receipt_file']) : '';
$path = APP_PATH . '/storage/receipts/' . $file;
if ($file === '' || !is_file($path)) { http_response_code(404); exit('Resit tidak dijumpai.'); }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!isset(MIME_RECEIPT[$mime])) { http_response_code(415); exit('Jenis fail tidak disokong.'); }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="resit-' . $o['order_no'] . '.' . MIME_RECEIPT[$mime] . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
