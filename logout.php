<?php
declare(strict_types=1);

require_once "security.php"; // ต้องมี session_start()

/* =========================
   Prevent Back Cache
========================= */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/* =========================
   Regenerate Session ID
========================= */
session_regenerate_id(true);

/* =========================
   Clear Session Data
========================= */
$_SESSION = [];

/* =========================
   Delete Session Cookie
========================= */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Strict'
        ]
    );
}

/* =========================
   Destroy Session
========================= */
session_destroy();

/* =========================
   Redirect Safely
========================= */
$redirect = isset($_GET['auto']) ? 'timeout=1' : 'logout=1';
header("Location: index.php?$redirect");
exit;
