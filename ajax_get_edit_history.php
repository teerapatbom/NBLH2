<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

requireLogin();

header('Content-Type: application/json; charset=utf-8');

$requestId = (int)($_GET['request_id'] ?? 0);

if ($requestId <= 0) {
    http_response_code(400);
    exit(json_encode(['error' => 'ข้อมูลไม่ถูกต้อง']));
}

try {
    // ตรวจสอบ columns ทั้ง 2 table
    $infoStmt = $conn->prepare("
        SELECT TABLE_NAME, COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('supply_request_history', 'member')
    ");
    $infoStmt->execute();
    $columns = $infoStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $historyColumns = array_filter($columns, fn($c) => $c['TABLE_NAME'] === 'supply_request_history');
    $memberColumns = array_filter($columns, fn($c) => $c['TABLE_NAME'] === 'member');
    
    $historyColumnNames = array_map(fn($c) => $c['COLUMN_NAME'], $historyColumns);
    $memberColumnNames = array_map(fn($c) => $c['COLUMN_NAME'], $memberColumns);
    
    // เลือก columns ที่มีอยู่
    $dateColumn = in_array('created_at', $historyColumnNames) 
        ? 'h.created_at' 
        : (in_array('created_date', $historyColumnNames) 
            ? 'h.created_date' 
            : "DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')");
    
    $nameColumn = in_array('Name', $memberColumnNames) 
        ? 'u.Name' 
        : (in_array('MemberName', $memberColumnNames) 
            ? 'u.MemberName' 
            : "'Unknown'");

    $stmt = $conn->prepare("
        SELECT 
            h.history_id,
            {$dateColumn} AS created_at,
            h.remark,
            COALESCE({$nameColumn}, 'Unknown') AS action_by_name
        FROM supply_request_history h
        LEFT JOIN member u ON h.action_by = u.MemberID
        WHERE h.request_id = ?
        ORDER BY h.history_id DESC
    ");
    $stmt->execute([$requestId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $history
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
