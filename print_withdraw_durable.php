<?php
declare(strict_types=1);
require_once "security.php";
require_once "connect.php";

requireLogin();

/* =========================
   FUNCTION
========================= */
function thaiDate(string $date): string {
    if (!$date) return '-';
    $ts = strtotime($date);
    return date('d/m/', $ts) . (date('Y', $ts) + 543);
}

/* =========================
   PARAM
========================= */
$docId = (int)($_GET['doc_id'] ?? 0);
if ($docId <= 0) {
    exit('ไม่พบเอกสาร');
}

/* =========================
   FETCH DOCUMENT
========================= */
$stmt = $conn->prepare("
    SELECT 
        d.DocID,
        d.ControlNumber,
        d.Title,
        d.Amount,
        d.CreatedAt,
        d.Note,
        d.StatusID,
        d.TypeDurableID,
        s.StatusName,
        c.DocTypeName,
        m.Name AS MemberName
    FROM documents d
    LEFT JOIN statustypes s ON d.StatusID = s.StatusID
    LEFT JOIN doctypes c ON d.DocTypeID = c.DocTypeID
    LEFT JOIN member m ON m.MemberID = d.MemberID
    WHERE d.DocID = ?
      AND d.StatusID = 7
      AND d.TypeDurableID IS NOT NULL
");
$stmt->execute([$docId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    exit('เอกสารยังไม่สามารถพิมพ์ใบเบิกได้');
}
/* =========================
   SAVE ITEMS (FROM MODAL)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_items'])) {

    $itemNames = $_POST['item_name'] ?? [];
    $qtys      = $_POST['qty'] ?? [];
    $prices    = $_POST['price'] ?? [];
    $units     = $_POST['unit'] ?? [];

    // ลบรายการเดิมก่อน (กันซ้ำ)
    $del = $conn->prepare("DELETE FROM document_items WHERE DocID = ?");
    $del->execute([$docId]);

    $stmtInsert = $conn->prepare("
        INSERT INTO document_items (DocID, ItemName, Qty, Price, UnitName)
        VALUES (?, ?, ?, ?, ?)
    ");

    for ($i = 0; $i < count($itemNames); $i++) {

        $name  = trim($itemNames[$i]);
        $qty   = (float)($qtys[$i] ?? 0);
        $price = (float)($prices[$i] ?? 0);
        $unit  = trim($units[$i] ?? '');

        if ($name === '') continue;

        $stmtInsert->execute([
            $docId,
            $name,
            $qty,
            $price,
            $unit
        ]);
    }

    header("Location: ?doc_id=" . $docId);
    exit;
}


/* =========================
   FETCH ITEMS
========================= */
$stmtItem = $conn->prepare("
    SELECT 
        ItemName,
        Qty,
        Price,
        UnitName
    FROM document_items
    WHERE DocID = ?
");
$stmtItem->execute([$docId]);
$items = $stmtItem->fetchAll(PDO::FETCH_ASSOC);
if (!$items) $items = [];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ใบเบิกครุภัณฑ์</title>

<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
@page {
  margin: 15mm;
}

@media print {
  .no-print {
    display: none !important;
  }
}



body {
  font-family: "Sarabun", sans-serif;
  font-size: 14px;
  color: #000;
}

.header {
  text-align: center;
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 10px;
}

.box {
  border: 1px solid #000;
  padding: 6px;
  margin-bottom: 6px;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  border: 1px solid #000;
  padding: 4px;
  vertical-align: middle;
}

.text-center { text-align: center; }
.text-right { text-align: right; }

.signature {
  height: 70px;
}

.small {
  font-size: 12px;
}
</style>
</head>

<body>

<div class="header">
  ใบเบิกหรือใบส่งคืนพัสดุ
</div>

<div class="box">
  เลขที่ใบเบิก: <strong><?= htmlspecialchars($doc['ControlNumber'] ?? '-') ?></strong>
  &nbsp;&nbsp;&nbsp;
  วันที่: <?= thaiDate($doc['CreatedAt']) ?>
</div>

<table>
<tr>
  <td width="20%">จาก</td>
  <td width="30%"><?= htmlspecialchars($doc['MemberName'] ?? '-') ?></td>
  <td width="20%">ถึง</td>
  <td width="30%">เจ้าหน้าที่พัสดุ</td>
</tr>
<tr>
  <td>หน่วยงาน</td>
  <td colspan="3"><?= htmlspecialchars($doc['DocTypeName'] ?? '-') ?></td>
</tr>
<tr>
  <td>ชื่อเรื่อง</td>
  <td colspan="3"><?= htmlspecialchars($doc['Title'] ?? '-') ?></td>
</tr>
</table>

<?php if (!isset($_GET['print'])): ?>
<div style="margin:10px 0;" class="no-print">
    
    <button onclick="openModal()" 
        style="padding:8px 16px; background:#e91e63; color:#fff; border:none; border-radius:20px; cursor:pointer; font-weight:600;">
        + เพิ่ม / แก้ไขรายการ
    </button>

    <a href="?doc_id=<?= $docId ?>&print=1"
       style="padding:8px 16px; background:#4caf50; color:#fff; text-decoration:none; border-radius:20px; margin-left:8px; font-weight:600;">
        🖨 พิมพ์ใบเบิก
    </a>

</div>
<?php endif; ?>



<table style="margin-top:10px;">
<thead>
<tr class="text-center">
  <th width="5%">ลำดับ</th>
  <th width="45%">รายการ</th>
  <th width="10%">หน่วยนับ</th>
  <th width="10%">จำนวน</th>
  <th width="15%">ราคาต่อหน่วย</th>
  <th width="15%">ราคารวม</th>
</tr>
</thead>
<tbody>

<?php
$i = 1;
$totalQty = 0;
$totalPrice = 0;

if (empty($items)):
?>
<tr>
  <td colspan="6" class="text-center">ไม่มีรายการครุภัณฑ์</td>
</tr>
<?php
else:
foreach ($items as $it):
  $qty = (float)($it['Qty'] ?? 0);
  $price = (float)($it['Price'] ?? 0);
  $sum = $qty * $price;

  $totalQty += $qty;
  $totalPrice += $sum;
?>
<tr>
  <td class="text-center"><?= $i++ ?></td>
  <td><?= htmlspecialchars($it['ItemName'] ?? '-') ?></td>
  <td class="text-center"><?= htmlspecialchars($it['UnitName'] ?? '-') ?></td>
  <td class="text-center"><?= number_format($qty) ?></td>
  <td class="text-right"><?= number_format($price, 2) ?></td>
  <td class="text-right"><?= number_format($sum, 2) ?></td>
</tr>
<?php endforeach; endif; ?>

<tr>
  <td colspan="3" class="text-center"><strong>รวม</strong></td>
  <td class="text-center"><strong><?= number_format($totalQty) ?></strong></td>
  <td></td>
  <td class="text-right"><strong><?= number_format($totalPrice,2) ?></strong></td>
</tr>

</tbody>
</table>

<table style="margin-top:15px;">
<tr class="text-center">
  <td class="signature">
    ลงชื่อ ___________________________<br>
    ผู้ขอเบิก
  </td>
  <td class="signature">
    ลงชื่อ ___________________________<br>
    ผู้อนุมัติ
  </td>
</tr>
<tr class="text-center">
  <td class="signature">
    ลงชื่อ ___________________________<br>
    ผู้จ่าย / ผู้รับ
  </td>
  <td class="signature">
    วันที่ ____ / ____ / ______
  </td>
</tr>
</table>

<!-- ITEM MODAL -->
<div id="itemModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.5);">

<div style="background:#fff; width:90%; max-width:1000px; margin:40px auto; padding:20px;">

<h3>กรอกรายการที่จะเบิก</h3>

<form method="post">

<table border="1" width="100%" id="itemTable" style="border-collapse:collapse;">
<tr style="background:#f0f0f0;">
    <th>รายการ</th>
    <th width="120">หน่วย</th>
    <th width="100">จำนวน</th>
    <th width="120">ราคา</th>
    <th width="150">รวม</th>
    <th width="60"></th>
</tr>

<?php if (!empty($items)): ?>
<?php foreach ($items as $it): ?>
<tr>
    <td><input type="text" name="item_name[]" 
        value="<?= htmlspecialchars($it['ItemName']) ?>" required></td>

    <td><input type="text" name="unit[]" 
        value="<?= htmlspecialchars($it['UnitName']) ?>"></td>

    <td><input type="number" step="0.01" name="qty[]" 
        class="qty" value="<?= (float)$it['Qty'] ?>"></td>

    <td><input type="number" step="0.01" name="price[]" 
        class="price" value="<?= (float)$it['Price'] ?>"></td>

    <td><input type="text" class="total" 
        value="<?= number_format((float)$it['Qty'] * (float)$it['Price'],2) ?>" readonly></td>

    <td><button type="button" onclick="removeRow(this)">ลบ</button></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
    <td><input type="text" name="item_name[]" required></td>
    <td><input type="text" name="unit[]"></td>
    <td><input type="number" step="0.01" name="qty[]" class="qty"></td>
    <td><input type="number" step="0.01" name="price[]" class="price"></td>
    <td><input type="text" class="total" readonly></td>
    <td><button type="button" onclick="removeRow(this)">ลบ</button></td>
</tr>
<?php endif; ?>


</table>

<br>
<button type="button" onclick="addRow()">+ เพิ่มรายการ</button>

<h4 style="text-align:right;">
รวมทั้งหมด: <span id="grandTotal">0.00</span> บาท
</h4>

<br>
<button type="submit" name="save_items">บันทึก</button>
<button type="button" onclick="closeModal()">ปิด</button>

</form>
</div>
</div>

<?php if (isset($_GET['print'])): ?>
<script>
window.print();
</script>
<?php endif; ?>

<script>
function openModal(){
    document.getElementById('itemModal').style.display='block';
    calculateGrand();
}

function closeModal(){
    document.getElementById('itemModal').style.display='none';
}

function addRow(){
    let table = document.getElementById('itemTable');
    let row = table.rows[1].cloneNode(true);
    row.querySelectorAll('input').forEach(i=>i.value='');
    table.appendChild(row);
}

function removeRow(btn){
    let rows = document.querySelectorAll('#itemTable tr');
    if(rows.length > 2){
        btn.parentElement.parentElement.remove();
        calculateGrand();
    }
}

document.addEventListener('input', function(e){
    if(e.target.classList.contains('qty') || e.target.classList.contains('price')){
        let row = e.target.closest('tr');
        let qty = parseFloat(row.querySelector('.qty').value)||0;
        let price = parseFloat(row.querySelector('.price').value)||0;
        row.querySelector('.total').value = (qty*price).toFixed(2);
        calculateGrand();
    }
});

function calculateGrand(){
    let total = 0;
    document.querySelectorAll('.total').forEach(el=>{
        total += parseFloat(el.value)||0;
    });
    document.getElementById('grandTotal').innerText = total.toFixed(2);
}
</script>

</body>
</html>
