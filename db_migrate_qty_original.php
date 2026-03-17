<?php
/**
 * Database migration helper
 * Adds qty_original column to supply_request_items table
 * This column tracks the original quantity when the item was first added
 */

require_once "connect.php";

try {
    $conn->beginTransaction();
    
    // Check if qty_original column exists
    $checkColumn = $conn->query("
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'supply_request_items' 
        AND COLUMN_NAME = 'qty_original'
    ")->fetch();
    
    if (!$checkColumn) {
        // Add qty_original column
        $conn->exec("ALTER TABLE supply_request_items ADD COLUMN qty_original INT NOT NULL DEFAULT 0");
        // Initialize with current qty values
        $conn->exec("UPDATE supply_request_items SET qty_original = qty WHERE qty_original = 0");
        echo "✓ Column qty_original added successfully<br>";
    } else {
        echo "✓ Column qty_original already exists<br>";
    }
    
    // Check if qty_received column exists (optional but recommended)
    $checkQtyRcv = $conn->query("
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'supply_request_items' 
        AND COLUMN_NAME = 'qty_received'
    ")->fetch();
    
    if (!$checkQtyRcv) {
        $conn->exec("ALTER TABLE supply_request_items ADD COLUMN qty_received INT DEFAULT 0");
        echo "✓ Column qty_received added successfully<br>";
    } else {
        echo "✓ Column qty_received already exists<br>";
    }
    
    try {
        $conn->commit();
    } catch (Exception $commitEx) {
        // Transaction already ended, that's ok
    }
    
    echo "<br><div style='background: #e8f5e9; padding: 15px; border-radius: 8px; margin-top: 20px;'>
       <strong style='color: #2e7d32; font-size: 1.1em;'>✅ Migration Completed Successfully!</strong>
       <hr style='border: none; border-top: 1px solid #4caf50; margin: 10px 0;'>
       <strong>Database now tracks 3 quantity levels:</strong>
       <ul style='margin: 10px 0; padding-left: 20px;'>
           <li><strong>qty_original</strong> - Original quantity when item was first ordered (never changes)</li>
           <li><strong>qty</strong> - Current quantity (updated when edited)</li>
           <li><strong>qty_received</strong> - Actual quantity physically received</li>
       </ul>
       <hr style='border: none; border-top: 1px solid #4caf50; margin: 10px 0;'>
       <strong>What you'll see in the edit modal:</strong>
       <ul style='margin: 10px 0; padding-left: 20px;'>
           <li>ORIGINAL ยอดแรก - <span style='background: #90caf9; padding: 2px 8px; border-radius: 4px;'>blue badge</span></li>
           <li>CURRENT ยอดเบิกเก่า - <span style='background: #fff9c4; padding: 2px 8px; border-radius: 4px;'>yellow badge</span></li>
           <li>NEW ยอดเบิกล่าสุด - <span style='background: #f5f5f5; padding: 2px 8px; border: 1px solid #ccc; border-radius: 4px;'>editable input</span></li>
       </ul>
    </div>";
    
} catch (Throwable $e) {
    echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px; margin-top: 20px;'>
           <strong style='color: #c62828;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</strong><br>
           <small>File: " . htmlspecialchars($e->getFile()) . " (Line " . $e->getLine() . ")</small>
          </div>";
    try { $conn->rollBack(); } catch (Exception $ex) {}
}
?>
