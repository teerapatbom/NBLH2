<?php
require_once "connect.php";

$request_id = (int)($_GET['request_id'] ?? 0);

header('Content-Type: application/json; charset=utf-8');

/* ดึงหมายเหตุ */
$remarkStmt = $conn->prepare("
    SELECT remark
    FROM supply_requests
    WHERE request_id = ?
");
$remarkStmt->execute([$request_id]);
$remark = $remarkStmt->fetchColumn();

/* Check if qty_received column exists */
$checkQtyReceived = $conn->query("
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'supply_request_items' 
    AND COLUMN_NAME = 'qty_received'
")->fetch();

/* ดึงรายการพัสดุ */
$itemStmt = $conn->prepare("
    SELECT s.supply_name,
           i.item_id,
           COALESCE(i.qty_original, i.qty) AS qty_original,
           i.qty,
           COALESCE(i.qty_received, 0) AS qty_received,
           COALESCE(i.price, 0) AS price
    FROM supply_request_items i
    JOIN supplies s ON i.supply_id = s.supply_id
    WHERE i.request_id = ?
    ORDER BY i.item_id
");

$itemStmt->execute([$request_id]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'remark' => $remark,
    'items'  => $items
]);
