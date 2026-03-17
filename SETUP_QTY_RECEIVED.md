## Setup Instructions for Received Quantities Tracking

This feature adds the ability to track quantities before and after withdrawal/receipt.

### Step 1: Database Migration
Run this URL in your browser to add the `qty_received` column:
```
http://localhost/NBLH/db_migrate_qty_received.php
```

### Features Added:

#### admin_stock.php (Admin/Warehouse view):
- ✅ Modified "👁 ดูรายการ" (View Items) button:
  - Shows "ก่อนเบิก" (Before withdrawal) - original ordered amount
  - Shows "หลังเบิก" (After withdrawal) - actual received amount (default: 0 if not recorded)
  - Shows "ราคา" (Price) for each item

- ✅ New "✏️ บันทึกจำนวนรับ" (Record Received Quantities) button in items modal:
  - Allows admins to record actual received quantities
  - Updates the database with actual received amounts

#### admin_supplies.php (User/Requester view):
- ✅ Modified "👁 ดูรายการ" (View Items) button:
  - Shows "ก่อนเบิก" (Before withdrawal) - original ordered amount
  - Shows "หลังเบิก" (After withdrawal) - actual received amount
  - Shows "ราคา" (Price) for each item

### New Files Created:
1. **ajax_update_qty_received.php** - AJAX endpoint to save received quantities
2. **db_migrate_qty_received.php** - Database migration to add `qty_received` column
3. **ajax_view_request_items.php** - Updated to return received quantities

### How to Use:

**For Warehouse Admin:**
1. Go to admin_stock.php (รับพัสดุ / อนุมัติการเบิก)
2. Click "👁 ดูรายการ" button
3. View both ordered and received quantities
4. Click "✏️ บันทึกจำนวนรับ" to record actual received amounts
5. Enter the actual quantities received
6. Click "💾 บันทึก" to save

**For Users (admin_supplies.php):**
1. View items in the "รายการเบิกของฉัน" section
2. Click "👁 ดูรายการ" to see both ordered and received quantities

### Database Changes:
- Added `qty_received` (INT, DEFAULT 0) column to `supply_request_items` table
- This tracks the actual quantity received for each item

### Notes:
- If `qty_received` is 0 or not set, it displays as "-" in the view
- Admin can update quantities anytime when status is "พร้อมรับ" (Ready to Receive) or "รับพัสดุแล้ว" (Received)
