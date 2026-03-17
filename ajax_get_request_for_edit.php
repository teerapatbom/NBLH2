<?php
require_once "security.php";
require_once "connect.php";
requireLogin();

$requestId = (int)($_GET['request_id'] ?? 0);

header('Content-Type: application/json; charset=utf-8');

$chk = $conn->prepare("
    SELECT remark FROM supply_requests
    WHERE request_id = ? AND status_id = 1
");
$chk->execute([$requestId]);
$head = $chk->fetch(PDO::FETCH_ASSOC);
if (!$head) {
    http_response_code(403);
    exit('ไม่สามารถแก้ไขรายการนี้ได้');
}

// Check if qty_original column exists
$checkColumn = $conn->prepare("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME='supply_request_items' 
    AND COLUMN_NAME='qty_original'
");
$checkColumn->execute();
$columnExists = $checkColumn->rowCount() > 0;

if ($columnExists) {
    $itemStmt = $conn->prepare("
        SELECT i.item_id,
               s.supply_name,
               COALESCE(i.qty_original, i.qty) AS qty_original,
               i.qty,
               COALESCE(i.price, 0) AS price
        FROM supply_request_items i
        JOIN supplies s ON i.supply_id = s.supply_id
        WHERE i.request_id = ?
        ORDER BY i.item_id
    ");
} else {
    // Fallback: qty_original = qty (same value) if column doesn't exist
    $itemStmt = $conn->prepare("
        SELECT i.item_id,
               s.supply_name,
               i.qty as qty_original,
               i.qty,
               COALESCE(i.price, 0) AS price
        FROM supply_request_items i
        JOIN supplies s ON i.supply_id = s.supply_id
        WHERE i.request_id = ?
        ORDER BY i.item_id
    ");
}

$itemStmt->execute([$requestId]);

echo json_encode([
    'remark' => $head['remark'],
    'items'  => $itemStmt->fetchAll(PDO::FETCH_ASSOC)
]);
