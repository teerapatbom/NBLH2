<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

$requestId = (int)($_POST['request_id'] ?? 0);
if ($requestId <= 0) {
    http_response_code(400);
    exit('ข้อมูลไม่ถูกต้อง');
}

/* status_id = 6 คือ ยกเลิก */
$stmt = $conn->prepare("
    UPDATE supply_requests
    SET status_id = 6
    WHERE request_id = :id
      AND status_id = 1
");
$stmt->execute([':id' => $requestId]);

echo 'ok';
