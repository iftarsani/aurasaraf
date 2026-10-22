<?php
declare(strict_types=1);

const LOGIN_MAX_FAILS  = 5;   // cubaan gagal
const LOGIN_WINDOW_MIN = 15;  // dalam tempoh (minit)

/** Peranan admin: label untuk paparan. */
function admin_roles(): array
{
    return ['owner' => 'Pemilik', 'staff' => 'Staf'];
}

function admin_current(): ?array
{
    static $admin = false;
    if ($admin === false) {
        $admin = null;
        if (!empty($_SESSION['admin_id'])) {
            $st = db()->prepare('SELECT id, username, name, email, phone, role, must_change_password
                                   FROM admins WHERE id = ?');
            $st->execute([(int)$_SESSION['admin_id']]);
            $admin = $st->fetch() ?: null;
        }
    }
    return $admin;
}

/** Nama untuk dipapar — jatuh balik kepada username jika nama belum diisi. */
function admin_name(?array $a = null): string
{
    $a ??= admin_current();
    if (!$a) return '';
    return trim((string)($a['name'] ?? '')) !== '' ? (string)$a['name'] : (string)$a['username'];
}

function admin_is_owner(?array $a = null): bool
{
    $a ??= admin_current();
    return $a !== null && ($a['role'] ?? '') === 'owner';
}

function require_admin(): array
{
    $a = admin_current();
    if (!$a) redirect('admin/login.php');

    // Selepas kata laluan di-reset oleh pemilik, admin dipaksa menukarnya dahulu.
    // settings.php ialah tempat tukar, logout.php mesti sentiasa boleh dicapai.
    if (!empty($a['must_change_password'])) {
        $here = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if (!in_array($here, ['settings.php', 'logout.php'], true)) {
            flash('error', 'Kata laluan anda telah di-reset. Sila tetapkan kata laluan baharu sebelum meneruskan.');
            redirect('admin/settings.php');
        }
    }
    return $a;
}

/** Halaman yang hanya untuk pemilik. */
function require_owner(): array
{
    $a = require_admin();
    if (!admin_is_owner($a)) {
        flash('error', 'Hanya Pemilik boleh mengurus akaun admin.');
        redirect('admin/index.php');
    }
    return $a;
}

/**
 * Kata laluan sementara yang boleh dibaca kuat kepada orang lain.
 * Aksara mengelirukan (0/O, 1/l/I) dibuang supaya tidak tersalah taip.
 */
function admin_temp_password(): string
{
    $abc = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = [];
    for ($g = 0; $g < 4; $g++) {
        $s = '';
        for ($i = 0; $i < 5; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
        $out[] = $s;
    }
    return implode('-', $out);
}
function login_locked(string $username): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts
        WHERE success = 0 AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MIN . ' MINUTE) AND (ip = ? OR username = ?)');
    $st->execute([client_ip(), $username]);
    return (int)$st->fetchColumn() >= LOGIN_MAX_FAILS;
}
function admin_login(string $username, string $password): bool
{
    $username = substr(trim($username), 0, 50);
    if (login_locked($username)) return false;

    $st = db()->prepare('SELECT id, password_hash FROM admins WHERE username = ?');
    $st->execute([$username]);
    $row = $st->fetch();
    // Sentiasa jalankan password_verify supaya masa respons sekata
    $hash = $row['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuJ3Zr0m4c8m8i0l1b7y0c8m8i0l1b7y0';
    $ok = password_verify($password, $hash) && $row;

    db()->prepare('INSERT INTO login_attempts (ip, username, success) VALUES (?, ?, ?)')
        ->execute([client_ip(), $username, $ok ? 1 : 0]);

    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$row['id'];
        db()->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?')->execute([$row['id']]);
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }
    }
    return (bool)$ok;
}
function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}
