<?php
declare(strict_types=1);

require_once "connect.php";   // PDO MySQL
require_once "security.php";  // session + csrf

/* =========================
   CSRF Check
========================= */
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(response_code: 403);
    exit("CSRF detected");
}

/* =========================
   Input
========================= */
$username = trim($_POST['txtUsername'] ?? '');
$password = $_POST['txtPassword'] ?? '';

if ($username === '' || $password === '') {
    header("Location: index.php?error=1");
    exit;
}

/* =========================
   Get user
========================= */
$stmt = $conn->prepare("
    SELECT MemberID, Username, Password, Status, 
           LoginAttempts, IsLocked, LockUntil
    FROM member
    WHERE Username = ?
    LIMIT 1
");
$stmt->execute([$username]);
$user = $stmt->fetch();

/* =========================
   Timing attack mitigation
========================= */
usleep(random_int(200000, 400000));

if (!$user) {
    header("Location: index.php?error=1");
    exit;
}

/* =========================
   Check Lock Status
========================= */
if ((int)$user['IsLocked'] === 1) {

    // ถ้ามีเวลาหมดอายุ และหมดเวลาแล้ว → ปลดล็อก
    if (!empty($user['LockUntil']) && strtotime($user['LockUntil']) <= time()) {

        $conn->prepare("
            UPDATE member 
            SET IsLocked = 0, LoginAttempts = 0, LockUntil = NULL
            WHERE MemberID = ?
        ")->execute([$user['MemberID']]);

    } else {
        header("Location: index.php?locked=1");
        exit;
    }
}

/* =========================
   Verify password
========================= */
if (!password_verify($password, $user['Password'])) {

    $attempts = (int)$user['LoginAttempts'] + 1;

    if ($attempts >= 5) {

        $lockUntil = date("Y-m-d H:i:s", strtotime("+3 minutes"));

        $conn->prepare("
            UPDATE member
            SET LoginAttempts = ?, 
                IsLocked = 1,
                LockUntil = ?
            WHERE MemberID = ?
        ")->execute([$attempts, $lockUntil, $user['MemberID']]);

        header("Location: index.php?locked=1");
        exit;

    } else {

        $conn->prepare("
            UPDATE member
            SET LoginAttempts = ?
            WHERE MemberID = ?
        ")->execute([$attempts, $user['MemberID']]);

        $remaining = 5 - $attempts;

header("Location: index.php?error=1&attempts=" . $attempts . "&remain=" . $remaining);
exit;
    }
}

/* =========================
   Load permissions
========================= */
$stmt = $conn->prepare("
    SELECT perm_code
    FROM user_permissions
    WHERE MemberID = ?
");
$stmt->execute([$user['MemberID']]);
$_SESSION['permissions'] = array_column($stmt->fetchAll(), 'perm_code');

/* =========================
   Login success
========================= */
$conn->prepare("
    UPDATE member
    SET LoginAttempts = 0,
        IsLocked = 0,
        LockUntil = NULL
    WHERE MemberID = ?
")->execute([$user['MemberID']]);

session_regenerate_id(true);

$_SESSION['UserID'] = $user['MemberID'];
$_SESSION['Status'] = $user['Status'];

header("Location: admin_page.php");
exit;
