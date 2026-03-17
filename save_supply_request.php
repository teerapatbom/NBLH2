<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

/* =========================
   AUTH
========================= */
requireLogin();

$userId = $_SESSION['UserID'];
$department = $_SESSION['Department'] ?? '-';

/* =========================
   รับค่า POST
========================= */
$warehouseId = intval($_POST['warehouse_id'] ?? 0);
$remark      = trim($_POST['remark'] ?? '');
$supplyIds   = $_POST['supply_id'] ?? [];
$qtys        = $_POST['qty'] ?? [];

if ($warehouseId <= 0 || empty($supplyIds)) {
    die('ข้อมูลไม่ครบ');
}

try {
    /* =========================
       START TRANSACTION
    ========================= */
    $conn->beginTransaction();

    /* =========================
       1) สร้างหัวเอกสาร
    ========================= */
    $stmt = $conn->prepare("
        INSERT INTO supply_requests
        (request_date, user_id, department, warehouse_id, status_id, remark)
        VALUES (CURDATE(), ?, ?, ?, 1, ?)
    ");
    $stmt->execute([
        $userId,
        $department,
        $warehouseId,
        $remark
    ]);

    $requestId = (int)$conn->lastInsertId();

    /* =========================
       2) วนบันทึกรายการสินค้า
       + ตรวจ stock
    ========================= */
    $checkStock = $conn->prepare("
        SELECT stock_qty
        FROM supplies
        WHERE supply_id = ? AND warehouse_id = ?
        FOR UPDATE
    ");

    // Check if qty_original column exists
    $checkQtyOriginal = $conn->query("
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'supply_request_items'
        AND COLUMN_NAME = 'qty_original'
    ")->fetch();

    $insertItem = $conn->prepare(
        $checkQtyOriginal
            ? "INSERT INTO supply_request_items (request_id, supply_id, qty, qty_original) VALUES (?,?,?,?)"
            : "INSERT INTO supply_request_items (request_id, supply_id, qty) VALUES (?,?,?)"
    );

    foreach ($supplyIds as $i => $supplyId) {
        $supplyId = intval($supplyId);
        $qty      = intval($qtys[$i] ?? 0);

        if ($supplyId <= 0 || $qty <= 0) {
            throw new Exception('จำนวนเบิกไม่ถูกต้อง');
        }

        /* ตรวจ stock */
        $checkStock->execute([$supplyId, $warehouseId]);
        $stock = $checkStock->fetchColumn();

        if ($stock === false) {
            throw new Exception('ไม่พบสินค้าในคลัง');
        }

        if ($qty > $stock) {
            throw new Exception('จำนวนเบิกมากกว่าคงเหลือ');
        }

        /* บันทึกรายการ */
        $insertItem->execute(
            $checkQtyOriginal
                ? [$requestId, $supplyId, $qty, $qty]  // qty_original = qty on creation
                : [$requestId, $supplyId, $qty]
        );
    }

    /* =========================
       3) บันทึก Timeline สถานะแรก
    ========================= */
    $conn->prepare("
        INSERT INTO supply_request_history
        (request_id, status_id, action_by, remark)
        VALUES (?, 1, ?, 'สร้างรายการเบิก')
    ")->execute([
        $requestId,
        $userId
    ]);

    /* =========================
       COMMIT
    ========================= */
    $conn->commit();

    /* =========================
       REDIRECT
    ========================= */
    header("Location: admin_supplies.php?success=1");
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    echo "<h4>❌ บันทึกไม่สำเร็จ</h4>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
