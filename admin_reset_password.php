<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

/* =========================
   Auth Guard
========================= */
requireLogin();

if (($_SESSION['Status'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    exit("Unauthorized");
}

/* =========================
   CSRF Check
========================= */
$csrfToken = $_POST['csrf_token'] ?? '';
if ($csrfToken === '' || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    exit("CSRF detected");
}

/* =========================
   Input
========================= */
$member_id        = (int)($_POST['member_id'] ?? 0);
$new_password     = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if ($member_id <= 0 || $new_password === '' || $confirm_password === '') {
    exit("ข้อมูลไม่ครบถ้วน");
}

if ($new_password !== $confirm_password) {
    exit("รหัสผ่านใหม่กับยืนยันรหัสผ่านไม่ตรงกัน");
}

if (strlen($new_password) < 6) {
    exit("รหัสผ่านต้องยาวอย่างน้อย 6 ตัวอักษร");
}

/* =========================
   Hash password
========================= */
$hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

/* =========================
   Update password
========================= */
$stmt = $conn->prepare("
    UPDATE member
    SET Password = ?, LoginAttempts = 0, IsLocked = 0
    WHERE MemberID = ?
");

$ok = $stmt->execute([
    $hashedPassword,
    $member_id
]);

if ($ok) {
    echo "✅ รีเซ็ตรหัสผ่านเรียบร้อยแล้ว";
} else {
    echo "❌ ไม่สามารถรีเซ็ตรหัสผ่านได้";
}
