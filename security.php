<?php
declare(strict_types=1);

/* =========================
   Secure Session Settings
========================= */
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', '1800'); // 30 นาที

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

session_name('SECURE_SYSTEM_SESSION');
session_start();

/* =========================
   Regenerate Session ID
========================= */
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} elseif (time() - $_SESSION['CREATED'] > 600) {
    session_regenerate_id(true);
    $_SESSION['CREATED'] = time();
}

/* =========================
   CSRF Token
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

function verifyCSRF(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (
            empty($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            http_response_code(403);
            exit('Invalid CSRF Token');
        }
    }
}

/* =========================
   XSS Protection
========================= */
function e(string|null $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/* =========================
   Login Required
========================= */
function requireLogin(): void {
    if (empty($_SESSION['UserID'])) {
        header("Location: index.php");
        exit;
    }
}

/* =========================
   Permission Check
========================= */
function hasPermission(string $perm): bool
{
    if (($_SESSION['Status'] ?? '') === 'ADMIN') {
        return true;
    }

    return in_array($perm, $_SESSION['permissions'] ?? [], true);
}

/* =========================
   Auto Logout (15 นาที)
========================= */
$timeout_duration = 15 * 60;

if (!empty($_SESSION['LAST_ACTIVITY']) &&
    (time() - $_SESSION['LAST_ACTIVITY'] > $timeout_duration)) {

    session_unset();
    session_destroy();

    header("Location: index.php?timeout=1");
    exit;
}

$_SESSION['LAST_ACTIVITY'] = time();

/* =========================
   Security Headers
========================= */
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer-when-downgrade");
