<?php
declare(strict_types=1);
require_once "security.php";
require_once "connect.php";

requireLogin();
if (!hasPermission('SUPPLIES_STOCK')) {
    http_response_code(403);
   exit('คุณไม่มีสิทธิ์เข้าหน้านี้เข้าได้เฉพาะผู้ดูแลระบบสินค้าเท่านั้น');
}

/* =========================
   เพิ่ม / แก้ไข / ลบ
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($_POST['action'] === 'add') {
        $stmt = $conn->prepare("
            INSERT INTO supplies
            (warehouse_id, ProductCode, supply_name, unit, stock_qty)
            VALUES (?,?,?,?,?)
        ");
        $stmt->execute([
            $_POST['warehouse_id'],
            $_POST['ProductCode'],
            $_POST['supply_name'],
            $_POST['unit'],
            (int)$_POST['stock_qty']
        ]);
    }

    if ($_POST['action'] === 'edit') {
        $stmt = $conn->prepare("
            UPDATE supplies SET
                warehouse_id = ?,
                ProductCode = ?,
                supply_name = ?,
                unit = ?,
                stock_qty = ?
            WHERE supply_id = ?
        ");
        $stmt->execute([
            $_POST['warehouse_id'],
            $_POST['ProductCode'],
            $_POST['supply_name'],
            $_POST['unit'],
            (int)$_POST['stock_qty'],
            (int)$_POST['supply_id']
        ]);
    }

    if ($_POST['action'] === 'delete') {
        $stmt = $conn->prepare("DELETE FROM supplies WHERE supply_id = ?");
        $stmt->execute([(int)$_POST['supply_id']]);
    }

    header("Location: admin_edit_stock.php?warehouse=" . $_POST['warehouse_id']);
    exit;
}

/* =========================
   ดึงคลังสินค้า
========================= */
$warehouses = $conn->query("SELECT * FROM warehouses ORDER BY warehouse_name")
                   ->fetchAll(PDO::FETCH_ASSOC);

$selectedWarehouse = $_GET['warehouse'] ?? '';
$search = trim($_GET['search'] ?? '');

/* =========================
   Pagination
========================= */
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$supplies = [];
$totalRows = 0;

if ($selectedWarehouse) {

    $where = ["s.warehouse_id = ?"];
    $params = [$selectedWarehouse];

    if ($search !== '') {
        $where[] = "(s.ProductCode LIKE ? OR s.supply_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereSql = "WHERE " . implode(" AND ", $where);

    $sql = "
        SELECT s.*, w.warehouse_name
        FROM supplies s
        JOIN warehouses w ON s.warehouse_id = w.warehouse_id
        $whereSql
        ORDER BY s.supply_name
        LIMIT $perPage OFFSET $offset
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $supplies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countSql = "SELECT COUNT(*) FROM supplies s $whereSql";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalRows = $countStmt->fetchColumn();
}

$totalPages = ceil($totalRows / $perPage);
$range = 2; // จำนวนหน้ารอบข้าง
$startPage = max(1, $page - $range);
$endPage   = min($totalPages, $page + $range);

?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>จัดการสินค้า</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
body{
    font-family:'Sarabun',sans-serif;
    background:linear-gradient(135deg,#fff0f6,#ffe6f2);
}
.card-pink{
    border:none;
    border-radius:22px;
    box-shadow:0 15px 40px rgba(255,0,128,0.08);
}
.btn-pink{
    background:linear-gradient(45deg,#ff4da6,#ff80bf);
    border:none;
    color:white;
    font-weight:600;
    border-radius:30px;
}
.btn-pink:hover{
    transform:translateY(-2px);
    box-shadow:0 8px 18px rgba(255,0,128,0.3);
}
.table thead{
    background:linear-gradient(45deg,#ff99cc,#ff66a3);
    color:white;
}
.table-hover tbody tr:hover{
    background:#fff0f7;
}
.badge-stock{
    font-size:13px;
    padding:6px 12px;
    border-radius:20px;
}
.low-stock{
    background:#ff4d4d;
    color:white;
}
.pagination .page-link{
    color:#ff4da6;
}
.pagination .active .page-link{
    background:#ff4da6;
    border-color:#ff4da6;
}
.modal-content{
    border-radius:20px;
}
.modal-header{
    background:linear-gradient(45deg,#ff99cc,#ff66a3);
    color:white;
}
/* Pagination ปรับโทนชมพู */
.pagination .page-link {
    color: #ad1457;
    border-radius: 8px;
    margin: 0 4px;
    font-weight: 600;
    border: 1px solid #f8bbd0;
    transition: 0.2s ease-in-out;
}

.pagination .page-link:hover {
    background-color: #f8bbd0;
    color: #880e4f;
}

.pagination .page-item.active .page-link {
    background-color: #d81b60;
    border-color: #d81b60;
    color: #fff;
}

.pagination .page-item.disabled .page-link {
    opacity: 0.5;
    pointer-events: none;
}

</style>
</head>

<body>
<div class="container py-5">
<div class="card card-pink p-4">

<h3 class="mb-4 fw-bold text-center">📦 ระบบจัดการสินค้าในคลัง</h3>

<form method="GET" class="row g-3 mb-4 align-items-end">
<div class="col-md-4">
<label class="form-label fw-semibold">เลือกคลังสินค้า</label>
<select name="warehouse" class="form-select rounded-pill">
<option value="">-- เลือกคลังสินค้า --</option>
<?php foreach($warehouses as $w): ?>
<option value="<?= $w['warehouse_id'] ?>"
<?= $selectedWarehouse == $w['warehouse_id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($w['warehouse_name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-4">
<label class="form-label fw-semibold">ค้นหาสินค้า</label>
<input type="text" name="search"
value="<?= htmlspecialchars($search) ?>"
class="form-control rounded-pill"
placeholder="ค้นหารหัสหรือชื่อสินค้า">
</div>

<div class="col-md-4 d-flex gap-2">
<button class="btn btn-pink flex-grow-1">🔍 ค้นหา</button>

<?php if ($selectedWarehouse): ?>
<button type="button" class="btn btn-success rounded-pill"
data-bs-toggle="modal"
data-bs-target="#addModal">
➕ เพิ่มสินค้า
</button>
<?php endif; ?>
</div>
</form>

<?php if ($selectedWarehouse): ?>

<div class="mb-3 text-end">
<span class="badge bg-danger fs-6 px-3 py-2 rounded-pill">
จำนวนทั้งหมด <?= number_format($totalRows) ?> รายการ
</span>
</div>

<table class="table table-bordered table-hover bg-white shadow-sm">
<thead class="table-dark text-center">
<tr>
    <th>รหัส</th>
    <th>ชื่อสินค้า</th>
    <th>หน่วย</th>
    <th>คงเหลือ</th>
    <th width="160">จัดการ</th>
</tr>
</thead>
<tbody>
<?php foreach($supplies as $s): ?>
<tr>
    <td><?= htmlspecialchars($s['ProductCode']) ?></td>
    <td><?= htmlspecialchars($s['supply_name']) ?></td>
    <td class="text-center"><?= htmlspecialchars($s['unit']) ?></td>
    <td class="text-center">
    <?php if($s['stock_qty'] <= 5): ?>
        <span class="badge badge-stock low-stock">
            <?= number_format($s['stock_qty']) ?>
        </span>
    <?php else: ?>
        <?= number_format($s['stock_qty']) ?>
    <?php endif; ?>
</td>

    <td class="text-center">

        <!-- ปุ่มแก้ไข -->
        <button class="btn btn-warning btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#editModal<?= $s['supply_id'] ?>">
            ✏
        </button>

        <!-- ปุ่มลบ -->
        <form method="POST" class="d-inline"
              onsubmit="return confirm('ยืนยันการลบสินค้า?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="supply_id" value="<?= $s['supply_id'] ?>">
            <input type="hidden" name="warehouse_id" value="<?= $selectedWarehouse ?>">
            <button class="btn btn-danger btn-sm">🗑</button>
        </form>

    </td>
</tr>

<!-- Modal แก้ไข -->
<div class="modal fade" id="editModal<?= $s['supply_id'] ?>">
<div class="modal-dialog">
<div class="modal-content">
<form method="POST">
<div class="modal-header">
<h5 class="modal-title">แก้ไขสินค้า</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">

<input type="hidden" name="action" value="edit">
<input type="hidden" name="supply_id" value="<?= $s['supply_id'] ?>">

<label>คลังสินค้า</label>
<select name="warehouse_id" class="form-select mb-2">
<?php foreach($warehouses as $w): ?>
<option value="<?= $w['warehouse_id'] ?>"
<?= $s['warehouse_id']==$w['warehouse_id']?'selected':'' ?>>
<?= htmlspecialchars($w['warehouse_name']) ?>
</option>
<?php endforeach; ?>
</select>

<label>รหัสสินค้า</label>
<input type="text" name="ProductCode"
       value="<?= htmlspecialchars($s['ProductCode']) ?>"
       class="form-control mb-2">

<label>ชื่อสินค้า</label>
<input type="text" name="supply_name"
       value="<?= htmlspecialchars($s['supply_name']) ?>"
       class="form-control mb-2">

<label>หน่วยนับ</label>
<input type="text" name="unit"
       value="<?= htmlspecialchars($s['unit']) ?>"
       class="form-control mb-2">

<label>จำนวนสต๊อก</label>
<input type="number" name="stock_qty"
       value="<?= $s['stock_qty'] ?>"
       class="form-control mb-2">

</div>
<div class="modal-footer">
<button class="btn btn-primary">บันทึก</button>
</div>
</form>
</div>
</div>
</div>

<!-- Modal เพิ่มสินค้า -->
<div class="modal fade" id="addModal">
<div class="modal-dialog">
<div class="modal-content">

<form method="POST">
<div class="modal-header">
    <h5 class="modal-title">➕ เพิ่มสินค้าใหม่</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

    <input type="hidden" name="action" value="add">

    <label>คลังสินค้า</label>
    <select name="warehouse_id" class="form-select mb-2" required>
        <?php foreach($warehouses as $w): ?>
            <option value="<?= $w['warehouse_id'] ?>"
                <?= $selectedWarehouse == $w['warehouse_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($w['warehouse_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>รหัสสินค้า</label>
    <input type="text" name="ProductCode"
           class="form-control mb-2"
           maxlength="8" required>

    <label>ชื่อสินค้า</label>
    <input type="text" name="supply_name"
           class="form-control mb-2" required>

    <label>หน่วยนับ</label>
    <input type="text" name="unit"
           class="form-control mb-2" required>

    <label>จำนวนสต๊อก</label>
    <input type="number" name="stock_qty"
           class="form-control mb-2"
           min="0" required>

</div>

<div class="modal-footer">
    <button type="submit" class="btn btn-success">
        บันทึกสินค้า
    </button>
</div>

</form>

</div>
</div>
</div>

<?php endforeach; ?>

<?php if (!$supplies): ?>
<tr>
<td colspan="5" class="text-center text-muted">ไม่พบข้อมูล</td>
</tr>
<?php endif; ?>

</tbody>
</table>

<?php if ($totalPages > 1): ?>
<nav class="mt-4">
<ul class="pagination justify-content-center">

    <!-- หน้าแรก -->
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?warehouse=<?= $selectedWarehouse ?>&search=<?= urlencode($search) ?>&page=1">
           « หน้าแรก
        </a>
    </li>

    <!-- ก่อนหน้า -->
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?warehouse=<?= $selectedWarehouse ?>&search=<?= urlencode($search) ?>&page=<?= max(1, $page - 1) ?>">
           ‹ ก่อนหน้า
        </a>
    </li>

    <!-- ช่วงเลขหน้า -->
    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link"
               href="?warehouse=<?= $selectedWarehouse ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>">
               <?= $i ?>
            </a>
        </li>
    <?php endfor; ?>

    <!-- ถัดไป -->
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?warehouse=<?= $selectedWarehouse ?>&search=<?= urlencode($search) ?>&page=<?= min($totalPages, $page + 1) ?>">
           ถัดไป ›
        </a>
    </li>

    <!-- หน้าสุดท้าย -->
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?warehouse=<?= $selectedWarehouse ?>&search=<?= urlencode($search) ?>&page=<?= $totalPages ?>">
           หน้าสุดท้าย »
        </a>
    </li>

</ul>
</nav>
<?php endif; ?>


<?php endif; ?>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
