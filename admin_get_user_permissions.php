<?php
require_once "connect.php";
require_once "security.php";
requireLogin();

if (!hasPermission('USER_MANAGE')) {
    http_response_code(403);
    exit;
}

$memberId = (int)($_GET['member_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT perm_code
    FROM user_permissions
    WHERE MemberID = ?
");
$stmt->execute([$memberId]);

echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
