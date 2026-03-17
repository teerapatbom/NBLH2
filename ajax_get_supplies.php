<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

/* =========================
   AUTH
========================= */
requireLogin();

/* =========================
   PARAM
========================= */
$warehouseId = (int)($_GET['warehouse_id'] ?? 0);

if ($warehouseId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([]);
    exit;
}

/* =========================
   QUERY สินค้าในคลัง
========================= */
$stmt = $conn->prepare("
    SELECT 
        supply_id,
        ProductCode,
        supply_name,
        stock_qty
    FROM supplies
    WHERE warehouse_id = ?
      AND stock_qty > 0
    ORDER BY supply_name
");
$stmt->execute([$warehouseId]);

$supplies = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   RETURN JSON
========================= */
header('Content-Type: application/json; charset=utf-8');
echo json_encode($supplies, JSON_UNESCAPED_UNICODE);
