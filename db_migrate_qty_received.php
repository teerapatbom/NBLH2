<?php
/**
 * Database migration helper
 * Ensures qty_received column exists in supply_request_items table
 */

require_once "connect.php";

try {
    // Check if column already exists
    $checkStmt = $conn->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME='supply_request_items' 
        AND COLUMN_NAME='qty_received'
    ");
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        // Column doesn't exist, add it
        $conn->exec("
            ALTER TABLE supply_request_items 
            ADD COLUMN qty_received INT DEFAULT 0
        ");
        
        echo "✓ Column qty_received added successfully";
    } else {
        echo "✓ Column qty_received already exists";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
