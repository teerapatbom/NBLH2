<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

/* =========================
   AUTH + PERMISSION
========================= */
requireLogin();
if (!hasPermission('STOCK_STATUS')) {
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์จัดการคลังพัสดุ');
}

/* =========================
   PAGINATION
========================= */
$perPage = 10; // จำนวนรายการต่อหน้า
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

/* =========================
   FILTER
========================= */
/* =========================
   FILTER
========================= */
$filterDocType = $_GET['doctype_id'] ?? '';
$filterStatus  = $_GET['status_id'] ?? '';

$where  = [];
$params = [];

if ($filterDocType !== '') {
    $where[] = 'm.DocTypeID = :doctype_id';
    $params[':doctype_id'] = $filterDocType;
}

if ($filterStatus !== '') {
    $where[] = 'r.status_id = :status_id';
    $params[':status_id'] = (int)$filterStatus;
}

/* =========================
   DATE FILTER
========================= */
$today = date('Y-m-d');

$fromDate = $_GET['from_date'] ?? $today;
$toDate   = $_GET['to_date'] ?? $today;

$where[] = "DATE(r.request_date) BETWEEN :from_date AND :to_date";
$params[':from_date'] = $fromDate;
$params[':to_date']   = $toDate;

/* 🔥 ต้องอยู่ตรงนี้ */
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';


/* =========================
   MAIN QUERY
========================= */
$sql = "
SELECT 
    r.request_id,
    r.request_date,
    d.DocTypeName,
    r.status_id,
    w.warehouse_name,
    s.status_name,

    (
        SELECT COUNT(*)
        FROM supply_request_messages m
        WHERE m.request_id = r.request_id
        AND m.sender_role = 'user'
        AND m.is_read = 0
    ) AS unread_count

FROM supply_requests r
JOIN member m ON r.user_id = m.MemberID
LEFT JOIN doctypes d
  ON m.DocTypeID COLLATE utf8mb4_unicode_ci
   = d.DocTypeID COLLATE utf8mb4_unicode_ci
JOIN warehouses w ON r.warehouse_id = w.warehouse_id
JOIN supply_status_types s ON r.status_id = s.status_id
$whereSql
ORDER BY r.request_id DESC
LIMIT :limit OFFSET :offset
";


$stmt = $conn->prepare($sql);

/* bind filter params */
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}

/* bind limit / offset */
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);



$countSql = "
SELECT COUNT(*)
FROM supply_requests r
JOIN member m 
    ON r.user_id = m.MemberID
$whereSql
";

$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);

/* =========================
   PAGINATION RANGE (1–10)
========================= */
$totalPages = max(1, $totalPages);
$page = max(1, min($page, $totalPages));

$maxLinks  = 10; // จำนวนเลขหน้าที่แสดงต่อช่วง
$startPage = (int)(floor(($page - 1) / $maxLinks) * $maxLinks) + 1;
$endPage   = min($startPage + $maxLinks - 1, $totalPages);




/* =========================
   DROPDOWN DATA
========================= */
$doctypes = $conn->query("
    SELECT DocTypeID, DocTypeName
    FROM doctypes
    ORDER BY DocTypeID
")->fetchAll(PDO::FETCH_ASSOC);

$statuses = $conn->query("
    SELECT status_id, status_name
    FROM supply_status_types
")->fetchAll(PDO::FETCH_ASSOC);


?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>รับพัสดุ / อนุมัติการเบิกพัสดุ55</title>
  <?php require_once "layout_head.php"; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<style>
/* ===== Theme Color ===== */
:root {
    --pink-main: #f06292;
    --pink-soft: #fde4ec;
    --pink-dark: #ec407a;
    --pink-border: #f8bbd0;
}

/* ===== Page ===== */
body {
  font-family: 'Sarabun', sans-serif;
  background: linear-gradient(to bottom right, #f8bbd0, #ffffff);
  min-height: 100vh;
  padding: 60px 15px;
  color: #9c2743;
}

h4 {
    color: var(--pink-dark);
    font-weight: 600;
}

/* ===== Card / Container ===== */
.container {
    background: #ffffff;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 10px 25px rgba(240, 98, 146, 0.12);
}

/* ===== Table ===== */
.table {
    border-radius: 12px;
    overflow: hidden;
}

.table thead {
    background: linear-gradient(135deg, #f48fb1, #f06292);
    color: #fff;
}

.table-hover tbody tr:hover {
    background-color: var(--pink-soft);
}

/* ===== Buttons ===== */
.btn-primary {
    background-color:  #04f524;
    border-color: var(--pink-main);
}
.btn-primary:hover {
    background-color: var(--pink-dark);
}

.btn-success {
    background-color: #5aee6e;
    border-color: #f48fb1;
}
.btn-success:hover {
    background-color: var(--pink-dark);
}

.btn-warning {
    background-color: #e9e064;
    border-color: #f8bbd0;
    color: #6a1b4d;
}
.btn-warning:hover {
    background-color: #f06292;
    color: #fff;
}

.btn-info {
    background-color: #fce4ec;
    border-color: #f8bbd0;
    color: #ad1457;
}
.btn-info:hover {
    background-color: #f8bbd0;
    color: #fff;
}

/* ===== Badge ===== */
.badge {
    font-size: 0.9rem;
    padding: 6px 10px;
    border-radius: 20px;
}

.bg-info {
    background-color: #fce4ec !important;
    color: #ad1457 !important;
}

.bg-success {
    background-color: #ee0150 !important;
}

.bg-secondary {
    background-color: #3211e9 !important;
}

/* ===== Filter ===== */
.form-select, .form-control {
    border-radius: 12px;
    border: 1px solid var(--pink-border);
}

.form-select:focus, .form-control:focus {
    border-color: var(--pink-main);
    box-shadow: 0 0 0 0.2rem rgba(240,98,146,.15);
}

/* ===== Modal ===== */
.modal-content {
    border-radius: 16px;
}
.modal-header {
    background: linear-gradient(135deg, #f48fb1, #f06292);
    color: #fff;
}
.page-link {
    color: #ec407a;
    border-radius: 10px;
}
.page-item.active .page-link {
    background-color: rgb(240, 98, 146);
    border-color: #f06292;
}
.page-link:hover {
    background-color: #fde4ec;
}
.badge-ready {
    background:#00FFFF;
    color:#000;
}
.badge-preparing {
    background:#FFA500;
    color:#000;
}
.badge-received {
    background:#D02090;
    color:#000;
}
.badge-wait {
    background:#DA70D6;
    color:#000;
}
.badge-warning {
    background:#FFFF00;
    color:#000;
}
.badge-compile {
    background:#33FF00;
    color:#000;
}
.badge-cancel {
    background: #f10d0d;
    color: #fff;
}
        .page-link {
  color: #ad1457;
  border-radius: 8px;
  margin: 0 4px;
  font-weight: 600;
}

.page-item.active .page-link {
  background-color: #d81b60;
  border-color: #d81b60;
}

.page-link:hover {
  background-color: #f8bbd0;
}
.chat-bubble{
    padding:10px 14px;
    border-radius:16px;
    margin-bottom:8px;
    max-width:70%;
}

.chat-user{
    background:#ec407a;
    color:#fff;
}

.chat-admin{
    background:#e3f2fd;
    color:#0d47a1;
    margin-left:auto;
}

</style>

<body class="bg-light">
<div class="container mt-4">

<?php
$notifyStmt = $conn->prepare("
SELECT COUNT(*)
FROM supply_request_messages
WHERE sender_role = 'user'
AND is_read = 0
");
$notifyStmt->execute();
$unread = (int)$notifyStmt->fetchColumn();
?>

<h5>
📦 งานคลังพัสดุ
<?php if ($unread > 0): ?>
<span class="badge bg-danger">🔔 <?= $unread ?></span>
<?php endif; ?>
</h5>


<!-- =========================
     FILTER FORM
========================= -->
<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
       <select name="doctype_id" class="form-select">
    <option value="">-- ทุกหน่วยงาน --</option>
    <?php foreach ($doctypes as $d): ?>
        <option value="<?= htmlspecialchars($d['DocTypeID']) ?>"
            <?= $filterDocType === $d['DocTypeID'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($d['DocTypeName']) ?>
        </option>
    <?php endforeach; ?>
</select>

    </div>

    <div class="col-md-3">
        <select name="status_id" class="form-select">
            <option value="">-- ทุกสถานะ --</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= $s['status_id'] ?>"
                    <?= (string)$filterStatus === (string)$s['status_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['status_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

        <div class="col-md-2">
    <input type="date"
           name="from_date"
           value="<?= htmlspecialchars($fromDate) ?>"
           class="form-control">
</div>

<div class="col-md-2">
    <input type="date"
           name="to_date"
           value="<?= htmlspecialchars($toDate) ?>"
           class="form-control">
</div>

    <div class="col-md-3">
        <button class="btn btn-primary">🔍 ค้นหา</button>
        <a href="admin_stock.php" class="btn btn-secondary">รีเซ็ต</a>
    </div>

</form>

<!-- =========================
     TABLE
========================= -->
<table class="table table-bordered table-hover align-middle">
<thead>
<tr>
    <th>เลขที่</th>
    <th>วันที่</th>
    <th>หน่วยงาน</th>
    <th>คลัง</th>
    <th>เบิก</th>
    <th>สถานะ</th>
    <th width="220">ดำเนินการ</th>
</tr>
</thead>
<tbody>
<?php foreach ($requests as $r): ?>
    <?php
$statusClass = match ($r['status_id']) {
    1 => 'badge-wait',
    2 => 'badge-preparing',
    3 => 'badge-warning',
    4 => 'badge-ready',
    5 => 'badge-compile',
    6 => 'badge-cancel',
    default => 'badge-secondary',
};

?>

<tr>
    <td><?= (int)$r['request_id'] ?></td>
    <td><?= htmlspecialchars((string)$r['request_date']) ?></td>
    <td><?= htmlspecialchars((string)$r['DocTypeName']) ?></td>
    <td><?= htmlspecialchars((string)$r['warehouse_name']) ?></td>

    <td class="text-center">
        <button class="btn btn-warning btn-sm open-chat position-relative"
        data-id="<?= (int)$r['request_id'] ?>">
    💬 ถาม-ตอบ

    <?php if ((int)$r['unread_count'] > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle p-2 bg-success border border-light rounded-circle">
        </span>
    <?php endif; ?>
</button>

        <button class="btn btn-info btn-sm view-items"
                data-id="<?= (int)$r['request_id'] ?>">
            👁 ดูรายการ
        </button>
           <a href="print_supply_pdf.php?request_id=<?= (int)$r['request_id'] ?>"
           target="_blank"
           class="btn btn-secondary btn-sm">📄 PDF</a>
    </td>

    <td>
        <span class="badge <?= $statusClass ?>">
    <?= htmlspecialchars($r['status_name']) ?>
</span>

    </td>

    <td>
<?php if ($r['status_id'] == 1): ?>

    <!-- แก้ไข -->
    <button class="btn btn-warning btn-sm edit-request"
            data-id="<?= (int)$r['request_id'] ?>">
        ✏️ แก้ไข
    </button>

    <button class="btn btn-success btn-sm act"
            data-id="<?= (int)$r['request_id'] ?>" data-status="2">
        ✅ อนุมัติ
    </button>

    <!-- ยกเลิก -->
    <button class="btn btn-danger btn-sm cancel-request"
            data-id="<?= (int)$r['request_id'] ?>">
        ❌ ยกเลิก
    </button>

<?php elseif ($r['status_id'] == 2): ?>
    <button class="btn btn-warning btn-sm act"
            data-id="<?= (int)$r['request_id'] ?>" data-status="3">
        📦 เตรียมพัสดุ
    </button>

<?php elseif ($r['status_id'] == 3): ?>
    <button class="btn btn-primary btn-sm act"
            data-id="<?= (int)$r['request_id'] ?>" data-status="4">
        📍 พร้อมรับ
    </button>

<?php elseif ($r['status_id'] == 4): ?>
    <span class="badge bg-success">รอผู้เบิกรับ</span>

<?php elseif ($r['status_id'] == 6): ?>
    <span class="badge badge-cancel">ยกเลิก</span>

<?php else: ?>
    <span class="badge bg-secondary">เสร็จสิ้น</span>
<?php endif; ?>

    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">✏️ แก้ไขรายการเบิก</h5>
        <button type="button" class="btn btn-sm btn-info" id="viewHistoryBtn">
          📋 ประวัติแก้ไข
        </button>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="editForm">
        <div class="modal-body">

          <input type="hidden" name="request_id" id="editRequestId">

          <table class="table table-bordered">
            <thead class="table-light">
              <tr>
                <th>สินค้า</th>
                <th width="140">
                  <div class="text-center">
                    <strong>ยอดเบิกเก่า</strong>
                  </div>
                </th>
                <th width="140">
                  <div class="text-center">
                    <strong>ยอดเบิกล่าสุด</strong>
                  </div>
                </th>
                <th width="140">
                  <div class="text-center">
                    <strong>ยอดเบิกใหม่</strong>
                  </div>
                </th>
                <th width="160">ราคา</th>
              </tr>
            </thead>
            <tbody id="editItemsBody"></tbody>
          </table>

          <label class="mt-2">📝 หมายเหตุ</label>
          <textarea name="remark" id="editRemark"
                    class="form-control" rows="3"></textarea>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            ยกเลิก
          </button>
          <button class="btn btn-primary">
            💾 บันทึก
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- =========================
     MODAL : VIEW ITEMS
========================= -->
<div class="modal fade" id="itemsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">📋 รายการพัสดุที่เบิก</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>สินค้า</th>
                    <th width="120" class="text-center">ยอดเบิกครั้งแรก</th>
                    <th width="120" class="text-center">ยอดล่าสุด</th>
                </tr>
            </thead>
            <tbody id="itemsBody"></tbody>
        </table>

      </div>
    </div>
  </div>
</div>

<!-- =========================
     MODAL : EDIT RECEIVED QUANTITIES
========================= -->
<div class="modal fade" id="qtyReceivedModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">📊 บันทึกจำนวนรับ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="qtyReceivedForm">
        <input type="hidden" name="request_id" id="qtyReceivedRequestId">
        <div class="modal-body">
          <table class="table table-bordered">
            <thead class="table-light">
              <tr>
                <th>สินค้า</th>
                <th width="100">สั่งมา</th>
                <th width="120">รับได้จริง</th>
              </tr>
            </thead>
            <tbody id="qtyReceivedBody"></tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            ยกเลิก
          </button>
          <button type="submit" class="btn btn-primary">
            💾 บันทึก
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="chatModal">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title">💬 ถาม-ตอบ</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <div id="chatBox"
             style="height:350px;overflow-y:auto;"></div>

        <div class="input-group mt-3">
          <input type="text" id="chatMessage"
                 class="form-control"
                 placeholder="พิมพ์ข้อความ...">
          <button class="btn btn-primary"
                  id="sendChat">ส่ง</button>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- =========================
     MODAL : EDIT HISTORY
========================= -->
<div class="modal fade" id="historyModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">📋 ประวัติการแก้ไข</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="historyContent" style="max-height: 400px; overflow-y: auto;">
          <p class="text-muted">กำลังโหลด...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($totalPages ): ?>
<?php
// กัน page หลุด
$page = max(1, min($page, $totalPages));
?>

<nav class="mt-3">
<ul class="pagination justify-content-center">

    <!-- หน้าแรก -->
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">
           « หน้าแรก
        </a>
    </li>

    <!-- ก่อนหน้า -->
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>">
           ‹ ก่อนหน้า
        </a>
    </li>

    <!-- เลขหน้า (ทุกหน้า) -->
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link"
               href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                <?= $i ?>
            </a>
        </li>
    <?php endfor; ?>

    <!-- ถัดไป -->
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?<?= http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])) ?>">
           ถัดไป ›
        </a>
    </li>

    <!-- หน้าสุดท้าย -->
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">
           หน้าสุดท้าย »
        </a>
    </li>

</ul>
</nav>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function formatPrice(value) {
    const amount = Number(value || 0);
    if (Number.isNaN(amount)) {
        return '0.00';
    }

    return amount.toLocaleString('th-TH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/* เปลี่ยนสถานะ */
$('.act').on('click', function () {
    if (!confirm('ยืนยันการเปลี่ยนสถานะ?')) return;

    const btn = $(this);
    btn.prop('disabled', true);

    $.post('update_supply_status.php', {
        request_id: btn.data('id'),
        status_id: btn.data('status')
    })
    .done(() => location.reload())
    .fail(xhr => {
        alert(xhr.responseText || 'เกิดข้อผิดพลาด');
        btn.prop('disabled', false);
    });
});

/* บันทึกจำนวนรับ */
let currentItemsData = [];

$('#editQtyReceivedBtn').on('click', function () {
    const modal = bootstrap.Modal.getInstance(document.getElementById('itemsModal'));
    if (modal) modal.hide();
    
    $('#qtyReceivedBody').html('');
    
    currentItemsData.forEach(item => {
        $('#qtyReceivedBody').append(`
            <tr>
                <td>${item.supply_name}</td>
                <td class="text-center">${item.qty}</td>
                <td>
                    <input type="number"
                           name="qty_received[${item.item_id}]"
                           value="${item.qty_received || ''}"
                           min="0"
                           class="form-control"
                           placeholder="0">
                </td>
            </tr>
        `);
    });
    
    new bootstrap.Modal(
        document.getElementById('qtyReceivedModal')
    ).show();
});

$('#qtyReceivedForm').on('submit', function (e) {
    e.preventDefault();
    
    const requestId = $('#itemsModal').data('current-request-id') || 0;
    
    $.post('ajax_update_qty_received.php', $(this).serialize() + '&request_id=' + requestId, function (res) {
        alert('บันทึกจำนวนรับแล้ว');
        location.reload();
    }).fail(function (xhr) {
        alert('เกิดข้อผิดพลาด: ' + xhr.responseText);
    });
});

/* Store current items data when view-items is clicked */
$('.view-items').on('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    
    const id = $(this).data('id');
    console.log('View items clicked, request_id:', id);
    
    $('#itemsModal').data('current-request-id', id);
    $('#qtyReceivedRequestId').val(id);

    $('#itemsBody').html('<tr><td colspan="3" class="text-center">กำลังโหลด...</td></tr>');

    $.getJSON('ajax_view_request_items.php', { request_id: id })
        .done(function (res) {
            console.log('Response received:', res);
            
            // Store items data globally
            currentItemsData = res.items || [];
            
            $('#itemsBody').html('');
            
            // รายการสินค้า
            if (res.items && res.items.length > 0) {
                res.items.forEach(r => {
                    const qtyOriginal = r.qty_original || r.qty;
                    const qtyCurrent = r.qty;
                    
                    $('#itemsBody').append(`
                        <tr>
                            <td><strong>${r.supply_name}</strong></td>
                            <td class="text-center"><strong>${qtyOriginal}</strong></td>
                            <td class="text-center"><strong>${qtyCurrent}</strong></td>
                        </tr>
                    `);
                });
            } else {
                $('#itemsBody').html('<tr><td colspan="3" class="text-center text-muted">ไม่มีรายการสินค้า</td></tr>');
            }

            const modal = new bootstrap.Modal(
                document.getElementById('itemsModal'),
                { backdrop: 'static', keyboard: false }
            );
            modal.show();
        })
        .fail(function (xhr, status, error) {
            console.error('AJAX Error:', status, error, xhr.responseText);
            alert('ไม่สามารถโหลดรายการได้: ' + (xhr.responseText || error));
        });
});

</script>
<script>
$('.cancel-request').on('click', function () {
    if (!confirm('ยืนยันยกเลิกรายการเบิกนี้?')) return;

    const id = $(this).data('id');

    $.post('cancel_supply_request.php', {
        request_id: id
    })
    .done(() => location.reload())
    .fail(xhr => alert(xhr.responseText || 'เกิดข้อผิดพลาด'));
});
</script>
<script>
/* เปิด modal แก้ไข */
$('.edit-request').on('click', function () {
    const id = $(this).data('id');

    $('#editItemsBody').html('');
    $('#editRemark').val('');
    $('#editRequestId').val(id);
    $('#viewHistoryBtn').data('request-id', id);

    $.getJSON('ajax_get_request_for_edit.php', { request_id: id }, function (res) {

        res.items.forEach(it => {
            const price = Number(it.price || 0).toFixed(2);
            const qtyOriginal = it.qty_original || it.qty;
            const qtyCurrent = it.qty || qtyOriginal;

            $('#editItemsBody').append(`
                <tr>
                    <td><strong>${it.supply_name}</strong></td>
                    <td class="text-center align-middle">
                        <div class="badge bg-secondary text-white" style="font-size: 1.1em; padding: 8px 12px;">
                            ${qtyOriginal}
                        </div>
                    </td>
                    <td class="text-center align-middle">
                        <div class="badge bg-warning text-dark" style="font-size: 1.1em; padding: 8px 12px;">
                            ${qtyCurrent}
                        </div>
                    </td>
                    <td>
                        <input type="number"
                               name="qty[${it.item_id}]"
                               value="${qtyCurrent}"
                               min="1"
                               class="form-control"
                               required
                               style="font-weight: bold; font-size: 1.05em;">
                    </td>
                    <td>
                        <input type="number"
                               name="price[${it.item_id}]"
                               value="${price}"
                               min="0"
                               step="0.01"
                               class="form-control text-end"
                               required>
                    </td>
                </tr>
            `);
        });

        $('#editRemark').val(res.remark || '');

        new bootstrap.Modal(
            document.getElementById('editModal')
        ).show();
    });
});

/* ดูประวัติแก้ไข */
$('#viewHistoryBtn').on('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    
    const requestId = $(this).data('request-id');
    console.log('View history clicked, requestId:', requestId);
    
    if (!requestId) {
        alert('ไม่พบรหัสการเบิก');
        return;
    }
    
    $('#historyContent').html('<p class="text-muted">กำลังโหลด...</p>');
    
    $.getJSON('ajax_get_edit_history.php', { request_id: requestId })
        .done(function (res) {
            console.log('History response:', res);
            
            if (!res.success || res.data.length === 0) {
                $('#historyContent').html('<p class="text-muted">ไม่มีประวัติการแก้ไข</p>');
            } else {
                let html = '<ul class="list-group list-group-flush">';
                res.data.forEach(h => {
                    html += `
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">${h.created_at}</small>
                                <strong>${h.action_by_name || 'ระบบ'}</strong>
                            </div>
                            <div class="mt-2" style="color: #6a1b4d;">
                                ${h.remark}
                            </div>
                        </li>
                    `;
                });
                html += '</ul>';
                $('#historyContent').html(html);
            }
            
            new bootstrap.Modal(
                document.getElementById('historyModal')
            ).show();
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX error:', status, error, xhr.responseText);
            $('#historyContent').html('<div class="alert alert-danger">ไม่สามารถโหลดประวัติได้: ' + (xhr.responseText || error) + '</div>');
        });
});

/* บันทึกการแก้ไข */
$('#editForm').on('submit', function (e) {
    e.preventDefault();

    $.post('ajax_save_edit_request.php', $(this).serialize())
     .done(() => location.reload())
     .fail(xhr => alert(xhr.responseText || 'เกิดข้อผิดพลาด'));
});
</script>
<script>
    let currentRequest = 0;
let chatInterval = null;

$('.open-chat').click(function(){

    currentRequest = $(this).data('id');
    $('#chatMessage').val('');

    loadChat();

    chatInterval = setInterval(loadChat, 5000);

    const modal = new bootstrap.Modal(
        document.getElementById('chatModal')
    );
    modal.show();

    document.getElementById('chatModal')
        .addEventListener('hidden.bs.modal', function () {
            clearInterval(chatInterval);
        }, { once: true });
});

function loadChat(){

    $('#chatBox').html('');

    $.getJSON('ajax_get_messages_admin.php',
        { request_id: currentRequest },
        function(res){

        res.forEach(msg => {

            const cls = msg.sender_role === 'admin'
                        ? 'chat-admin'
                        : 'chat-user';

            $('#chatBox').append(`
                <div class="chat-bubble ${cls}">
                    ${msg.message}
                    <div style="font-size:11px;opacity:.6">
                        ${msg.created_at}
                    </div>
                </div>
            `);
        });

        $('#chatBox').scrollTop(
            $('#chatBox')[0].scrollHeight
        );
    });
}

$('#sendChat').click(function(){

    const msg = $('#chatMessage').val().trim();
    if(!msg) return;

    $.post('ajax_send_message_admin.php',{
        request_id: currentRequest,
        message: msg
    },function(){
        $('#chatMessage').val('');
        loadChat();
    });
});

</script>
</body>
</html>
