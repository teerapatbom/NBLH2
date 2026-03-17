<?php
require_once "security.php";
require_once "connect.php";
requireLogin();

if ($_SESSION['Status'] !== 'ADMIN') exit("Unauthorized");

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    exit("CSRF detected");
}

$member_id = (int)($_POST['member_id'] ?? 0);
$perms = $_POST['permissions'] ?? [];

$conn->prepare("DELETE FROM user_permissions WHERE MemberID=?")
     ->execute([$member_id]);

$stmt = $conn->prepare(
  "INSERT INTO user_permissions (MemberID, perm_code) VALUES (?, ?)"
);

foreach ($perms as $p) {
  $stmt->execute([$member_id, $p]);
}

echo "✅ บันทึกสิทธิ์เรียบร้อยแล้ว";
