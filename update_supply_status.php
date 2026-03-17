<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

$requestId = intval($_POST['request_id']);
$statusId  = intval($_POST['status_id']);
$userId    = $_SESSION['UserID'];

$conn->beginTransaction();

/* อัปเดตสถานะปัจจุบัน */
$conn->prepare("
    UPDATE supply_requests
    SET status_id = ?
    WHERE request_id = ?
")->execute([$statusId, $requestId]);

/* บันทึก Timeline */
$conn->prepare("
    INSERT INTO supply_request_history
    (request_id, status_id, action_by)
    VALUES (?,?,?)
")->execute([$requestId, $statusId, $userId]);

$conn->commit();

echo "OK";
