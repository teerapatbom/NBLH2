<?php
require_once "security.php";
require_once "connect.php";
requireLogin();

$requestId = (int)$_GET['request_id'];

$stmt = $conn->prepare("
SELECT sender_role,message,created_at
FROM supply_request_messages
WHERE request_id = ?
ORDER BY created_at ASC
");
$stmt->execute([$requestId]);

// mark read (ข้อความจาก user)
$conn->prepare("
UPDATE supply_request_messages
SET is_read = 1
WHERE request_id = ?
AND sender_role = 'user'
")->execute([$requestId]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
