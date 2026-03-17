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
$member_id = (int)($_POST['member_id'] ?? 0);
if ($member_id <= 0) {
    exit("ข้อมูลไม่ครบถ้วน");
}

/* =========================
   Prevent self-delete
========================= */
if ($member_id === (int)$_SESSION['UserID']) {
    exit("ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้");
}

/* =========================
   Delete user
========================= */
$stmt = $conn->prepare("
    DELETE FROM member
    WHERE MemberID = ?
");

$ok = $stmt->execute([$member_id]);

if ($ok) {
    echo "✅ ลบผู้ใช้เรียบร้อยแล้ว";
} else {
    echo "❌ ไม่สามารถลบผู้ใช้ได้";
}
