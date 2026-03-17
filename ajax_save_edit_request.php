<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

requireLogin();

function formatMoneyForHistory(float $amount): string
{
    return number_format($amount, 2, '.', '');
}

function buildEditHistoryRemark(
    string $supplyName,
    int $oldQty,
    int $newQty,
    float $oldPrice,
    float $newPrice
): string {
    $remark = sprintf(
        'แก้ไข %s: จำนวน %d -> %d, ราคา %s -> %s',
        mb_substr(trim($supplyName), 0, 80),
        $oldQty,
        $newQty,
        formatMoneyForHistory($oldPrice),
        formatMoneyForHistory($newPrice)
    );

    return mb_substr($remark, 0, 255);
}

$requestId   = (int)($_POST['request_id'] ?? 0);
$remark      = trim((string)($_POST['remark'] ?? ''));
$adminRemark = trim((string)($_POST['admin_remark'] ?? ''));
$userId      = (int)($_SESSION['UserID'] ?? 0);
$qtyInput    = $_POST['qty'] ?? [];
$priceInput  = $_POST['price'] ?? [];

if ($requestId <= 0) {
    http_response_code(400);
    exit('ข้อมูลไม่ถูกต้อง');
}

try {
    $conn->beginTransaction();

    $chk = $conn->prepare("
        SELECT status_id, remark, admin_remark
        FROM supply_requests
        WHERE request_id = ?
        FOR UPDATE
    ");
    $chk->execute([$requestId]);
    $request = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$request || (int)$request['status_id'] !== 1) {
        throw new RuntimeException('ไม่สามารถแก้ไขรายการนี้ได้');
    }

    $itemStmt = $conn->prepare("
        SELECT i.item_id,
               i.qty,
               COALESCE(i.qty_original, i.qty) AS qty_original,
               COALESCE(i.price, 0) AS price,
               s.supply_name
        FROM supply_request_items i
        JOIN supplies s ON i.supply_id = s.supply_id
        WHERE i.request_id = ?
        FOR UPDATE
    ");
    $itemStmt->execute([$requestId]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {
        throw new RuntimeException('ไม่พบรายการสินค้า');
    }

    $updateHead = $conn->prepare("
        UPDATE supply_requests
        SET remark = ?,
            admin_remark = ?
        WHERE request_id = ?
    ");
    $updateHead->execute([
        $remark,
        $adminRemark,
        $requestId
    ]);

    $updateItem = $conn->prepare("
        UPDATE supply_request_items
        SET qty = ?,
            price = ?
        WHERE item_id = ? AND request_id = ?
    ");

    $insertHistory = $conn->prepare("
        INSERT INTO supply_request_history
        (request_id, status_id, action_by, remark)
        VALUES (?,?,?,?)
    ");

    foreach ($items as $item) {
        $itemId = (int)$item['item_id'];

        if (!array_key_exists($itemId, $qtyInput) && !array_key_exists((string)$itemId, $qtyInput)) {
            continue;
        }

        $newQty = (int)($qtyInput[$itemId] ?? $qtyInput[(string)$itemId] ?? 0);
        $rawPrice = $priceInput[$itemId] ?? $priceInput[(string)$itemId] ?? '0';

        if ($newQty <= 0) {
            throw new RuntimeException('จำนวนที่แก้ไขต้องมากกว่า 0');
        }

        if (!is_numeric((string)$rawPrice)) {
            throw new RuntimeException('ราคาต้องเป็นตัวเลข');
        }

        $newPrice = round((float)$rawPrice, 2);
        if ($newPrice < 0) {
            throw new RuntimeException('ราคาต้องไม่ติดลบ');
        }

        $oldQty = (int)$item['qty'];
        $oldPrice = round((float)$item['price'], 2);
        $qtyOriginal = (int)$item['qty_original']; // Preserve original quantity

        $updateItem->execute([
            $newQty,
            $newPrice,
            $itemId,
            $requestId
        ]);

        if ($oldQty !== $newQty || abs($oldPrice - $newPrice) >= 0.01) {
            $insertHistory->execute([
                $requestId,
                1,
                $userId,
                buildEditHistoryRemark(
                    (string)$item['supply_name'],
                    $oldQty,
                    $newQty,
                    $oldPrice,
                    $newPrice
                )
            ]);
        }
    }

    if (($request['remark'] ?? '') !== $remark) {
        $insertHistory->execute([
            $requestId,
            1,
            $userId,
            mb_substr('แก้ไขหมายเหตุรายการเบิก', 0, 255)
        ]);
    }

    $conn->commit();
    echo 'OK';
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(400);
    echo $e->getMessage();
}
