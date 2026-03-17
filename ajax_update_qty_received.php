<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

requireLogin();
if (!hasPermission('STOCK_STATUS')) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์');
}

header('Content-Type: application/json; charset=utf-8');

$requestId = (int)($_POST['request_id'] ?? 0);
$qtyReceivedInput = $_POST['qty_received'] ?? [];
$userId = (int)($_SESSION['UserID'] ?? 0);

if ($requestId <= 0) {
    http_response_code(400);
    exit(json_encode(['error' => 'ข้อมูลไม่ถูกต้อง']));
}

try {
    $conn->beginTransaction();

    // ตรวจสอบว่าต้องเป็นสถานะพร้อมรับ (4) หรือรับแล้ว (5) เท่านั้น
    $stmt = $conn->prepare("
        SELECT status_id
        FROM supply_requests
        WHERE request_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request || !in_array((int)$request['status_id'], [4, 5])) {
        throw new Exception('ไม่สามารถบันทึกจำนวนรับได้');
    }

    // อัปเดตจำนวนรับสำหรับแต่ละรายการ
    $updateStmt = $conn->prepare("
        UPDATE supply_request_items
        SET qty_received = ?
        WHERE item_id = ? AND request_id = ?
    ");

    foreach ($qtyReceivedInput as $itemId => $qtyStr) {
        $itemId = (int)$itemId;
        $qty = (int)$qtyStr;

        if ($qty < 0) {
            continue; // ข้ามค่าติดลบ
        }

        $updateStmt->execute([$qty, $itemId, $requestId]);
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'บันทึกจำนวนรับแล้ว'
    ]);

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
