<?php
require_once "connect.php";
require_once "security.php";

header('Content-Type: application/json');

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้อง']);
    exit;
}

$memberID = (int)($_POST['member_id'] ?? 0);

if (!$memberID) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบสมาชิก']);
    exit;
}

$permStmt = $conn->prepare("
    INSERT INTO user_permissions (MemberID, perm_code)
    VALUES (?, ?)
");

$defaultPermissions = ['DOC_STATUS', 'SUPPLIES_STATUS'];

foreach ($defaultPermissions as $perm) {
    $permStmt->execute([$memberID, $perm]);
}

echo json_encode([
    'status' => 'success',
    'message' => 'เพิ่มสิทธิ์เรียบร้อยแล้ว'
]);
