<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

/* =========================
   AUTH
========================= */
requireLogin();

$userId = (int)$_SESSION['UserID'];
$requestId = (int)($_POST['request_id'] ?? 0);

if ($requestId <= 0) {
    http_response_code(400);
    exit('ข้อมูลไม่ถูกต้อง');
}

try {
    $conn->beginTransaction();

    /* =========================
       ตรวจว่าเป็นรายการของ user นี้จริง
       และสถานะต้องเป็น "พร้อมรับ" (4)
    ========================= */
    $stmt = $conn->prepare("
        SELECT status_id
        FROM supply_requests
        WHERE request_id = ? AND user_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$requestId, $userId]);
    $currentStatus = $stmt->fetchColumn();

    if (!$currentStatus) {
        throw new Exception('ไม่พบรายการเบิก หรือคุณไม่มีสิทธิ์');
    }

    if ((int)$currentStatus !== 4) {
        throw new Exception('รายการนี้ยังไม่อยู่ในสถานะพร้อมรับ');
    }

    /* =========================
       อัปเดตสถานะเป็น รับพัสดุแล้ว (5)
    ========================= */
    $conn->prepare("
        UPDATE supply_requests
        SET status_id = 5
        WHERE request_id = ?
    ")->execute([$requestId]);

    /* =========================
       บันทึกประวัติสถานะ
    ========================= */
    $conn->prepare("
        INSERT INTO supply_request_history
        (request_id, status_id, action_by, remark)
        VALUES (?,?,?,?)
    ")->execute([
        $requestId,
        5,
        $userId,
        'ผู้เบิกรับพัสดุแล้ว'
    ]);

    $conn->commit();

    echo 'OK';

} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(400);
    echo $e->getMessage();
}
