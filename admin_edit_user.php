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
$member_id  = (int)($_POST['member_id'] ?? 0);
$name       = trim($_POST['name'] ?? '');
$username   = trim($_POST['username'] ?? '');
$position   = trim($_POST['position'] ?? '');
$doctype_id = trim($_POST['doctype_id'] ?? '');
$status     = $_POST['status'] ?? '';

if (
    $member_id <= 0 ||
    $name === '' ||
    $username === '' ||
    $position === '' ||
    $doctype_id === '' ||
    $status === ''
) {
    exit("ข้อมูลไม่ครบถ้วน");
}

/* =========================
   Validate
========================= */
if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
    exit("Username ไม่ถูกต้อง");
}

if (!in_array($status, ['USER', 'ADMIN'], true)) {
    exit("สถานะไม่ถูกต้อง");
}

/* =========================
   Check duplicate username
========================= */
$stmt = $conn->prepare("
    SELECT 1
    FROM member
    WHERE Username = ? AND MemberID != ?
    LIMIT 1
");
$stmt->execute([$username, $member_id]);

if ($stmt->fetch()) {
    exit("Username นี้ถูกใช้ไปแล้ว");
}

/* =========================
   Update user
========================= */
$stmt = $conn->prepare("
  UPDATE member SET
    Name = :name,
    Username = :username,
    Position = :position,
    Status = :status,
    DocTypeID = :doctype
  WHERE MemberID = :id
");

$success = $stmt->execute([
  ':name'     => $name,
  ':username'=> $username,
  ':position'=> $position,
  ':status'  => $status,
  ':doctype' => $doctype_id,
  ':id'      => $member_id
]);

/* =========================
   Result
========================= */
if ($success) {
    echo "✅ แก้ไขข้อมูลเรียบร้อยแล้ว";
} else {
    echo "❌ ไม่สามารถแก้ไขข้อมูลได้";
}
