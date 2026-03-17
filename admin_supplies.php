<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

/* =========================
   AUTH + PERMISSION
========================= */
requireLogin();
if (!hasPermission('SUPPLIES_STATUS')) {
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์เข้าหน้านี้');
}

$memberId = (int)$_SESSION['UserID'];

$stmt = $conn->prepare("
    SELECT m.Name,
           m.Position,
           d.DocTypeName
    FROM member m
    LEFT JOIN doctypes d
      ON m.DocTypeID COLLATE utf8mb4_unicode_ci
       = d.DocTypeID COLLATE utf8mb4_unicode_ci
    WHERE m.MemberID = ?
");
$stmt->execute([$memberId]);

$member = $stmt->fetch(PDO::FETCH_ASSOC);

$userName   = $member['Name'] ?? '';
$position   = $member['Position'] ?? '';
$department = $member['DocTypeName'] ?? '-';

/* =========================
   PAGINATION (My Requests)
========================= */
$perPage = 5;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

/* =========================
   คลังพัสดุ
========================= */
$warehouses = $conn->query("
    SELECT warehouse_id, warehouse_name
    FROM warehouses
    ORDER BY warehouse_name
")->fetchAll(PDO::FETCH_ASSOC);

/* รายการเบิก */
$reqStmt = $conn->prepare("
SELECT r.request_id, r.request_date,
       w.warehouse_name,
       r.status_id, s.status_name,

       (
        SELECT COUNT(*)
        FROM supply_request_messages m
        WHERE m.request_id = r.request_id
        AND m.sender_role = 'admin'
        AND m.is_read = 0
       ) AS unread_count

FROM supply_requests r
JOIN warehouses w ON r.warehouse_id = w.warehouse_id
JOIN supply_status_types s ON r.status_id = s.status_id
WHERE r.user_id = :user_id
ORDER BY r.request_id DESC
LIMIT :limit OFFSET :offset
");


$reqStmt->bindValue(':user_id', $memberId, PDO::PARAM_INT);
$reqStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$reqStmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$reqStmt->execute();
$userRequests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);


$countStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM supply_requests
    WHERE user_id = ?
");
$countStmt->execute([$memberId]);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);
/* =========================
   PAGINATION RANGE
========================= */
$totalPages = max(1, $totalPages);
$page = max(1, min($page, $totalPages));

$maxLinks  = 10; // จำนวนเลขหน้าที่โชว์
$startPage = (int)(floor(($page - 1) / $maxLinks) * $maxLinks) + 1;
$endPage   = min($startPage + $maxLinks - 1, $totalPages);
function getSupplyStatusBadge(string $statusName): string
{
    $map = [
        'รออนุมัติ' => 'warning',
        'อนุมัติแล้ว' => 'primary',
        'กำลังจัดเตรียมพัสดุ' => 'info',
        'ยกเลิก' => 'danger',
        'รับพัสดุแล้ว' => 'success'
    ];

    $class = $map[$statusName] ?? 'secondary';

    return "<span class='badge bg-$class'>"
           . htmlspecialchars($statusName)
           . "</span>";
}



?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>เบิกพัสดุ</title>
  <?php require_once "layout_head.php"; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


<style>
:root{
    --pink-main:#f06292;
    --pink-soft:#fde4ec;
    --pink-dark:#ec407a;
    --pink-border:#f8bbd0;
}

body{
    font-family:'Sarabun',sans-serif;
    background:#fff6f9;
}

/* ===== Card ===== */
.container{
    background:#fff;
    border-radius:18px;
    padding:28px;
    box-shadow:0 12px 30px rgba(240,98,146,.15);
}

/* ===== Headings ===== */
h4,h5,h6{
    color:var(--pink-dark);
    font-weight:600;
}

/* ===== Form ===== */
label{
    font-weight:500;
    color:#ad1457;
}
.form-control,.form-select{
    border-radius:12px;
    border:1px solid var(--pink-border);
}
.form-control:focus,.form-select:focus{
    border-color:var(--pink-main);
    box-shadow:0 0 0 .2rem rgba(240,98,146,.15);
}

/* ===== Table ===== */
.table{
    border-radius:14px;
    overflow:hidden;
}
.table thead{
    background:linear-gradient(135deg,#f48fb1,#f06292);
    color:#fff;
}
.table-hover tbody tr:hover{
    background:var(--pink-soft);
}

/* ===== Buttons ===== */
.btn-primary{
    background:var(--pink-main);
    border-color:var(--pink-main);
}
.btn-primary:hover{
    background:var(--pink-dark);
}
.btn-secondary{
    background:#fce4ec;
    border-color:#f8bbd0;
    color:#ad1457;
}
.btn-secondary:hover{
    background:#f8bbd0;
    color:#fff;
}
.btn-danger{
    border-radius:10px;
}
.btn-sm{
    border-radius:10px;
}

/* ===== Timeline (Shopee Style) ===== */
.timeline{
    border-left:3px solid var(--pink-main);
    padding-left:20px;
}
.timeline-item{
    margin-bottom:16px;
    position:relative;
}
.timeline-item::before{
    content:"";
    position:absolute;
    left:-11px;
    top:4px;
    width:14px;
    height:14px;
    background:var(--pink-main);
    border-radius:50%;
}

/* ===== Badge ===== */
.badge{
    padding:6px 12px;
    border-radius:20px;
    font-weight:500;
}

/* ===== Section Divider ===== */
hr{
    border-top:1px dashed var(--pink-border);
    margin:24px 0;
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
.badge-status {
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.95rem;
}

.badge-waiting { background: #ffb74d; color: #fff; }
.badge-approved { background: #ec407a; color: #fff; }
.badge-preparing { background: #42a5f5; color: #fff; }
.badge-cancel { background: #e53935; color: #fff; }
.badge-received { background: #43a047; color: #fff; }
.chat-bubble{
    padding:10px 14px;
    border-radius:16px;
    margin-bottom:8px;
    max-width:70%;
    word-wrap:break-word;
}

.chat-user{
    background:#ec407a;
    color:#fff;
    margin-left:auto;
}

.chat-admin{
    background:#e3f2fd;
    color:#0d47a1;
}


</style>

</head>

<body>
<div class="container mt-4">

<h4>📦 ระบบเบิกพัสดุ</h4>

<!-- ================= FORM เบิก ================= -->
<form method="post" action="save_supply_request.php">

<div class="row g-3">
<div class="col-md-3">
    <label>ผู้เบิก</label>
    <input type="text" class="form-control"
           value="<?= htmlspecialchars($userName) ?>" readonly>
</div>

<div class="col-md-3">
    <label>ตำแหน่ง</label>
    <input type="text" class="form-control"
           value="<?= htmlspecialchars($position) ?>" readonly>
</div>

<div class="col-md-3">
    <label>หน่วยงาน</label>
    <input type="text" class="form-control"
           value="<?= htmlspecialchars($department) ?>" readonly>
</div>


    <div class="col-md-3">
        <label>คลังพัสดุ</label>
        <select id="warehouse" name="warehouse_id" class="form-select" required>
            <option value="">-- เลือกคลัง --</option>
            <?php foreach($warehouses as $w): ?>
                <option value="<?= (int)$w['warehouse_id'] ?>">
                    <?= htmlspecialchars($w['warehouse_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<hr>

<h6>รายการพัสดุ</h6>
<table class="table table-bordered" id="itemsTable">
<thead class="table-light">
<tr>
    <th>สินค้า</th>
    <th width="120">คงเหลือ</th>
    <th width="120">จำนวนเบิก</th>
    <th width="60"></th>
</tr>
</thead>
<tbody></tbody>
</table>

<button type="button" class="btn btn-secondary btn-sm" id="addRow">➕ เพิ่มสินค้า</button>

<hr>

<label>หมายเหตุ</label>
<textarea name="remark" class="form-control"></textarea>

<div class="text-end mt-3">
    <button type="submit"
        id="submitRequest"
        class="btn btn-primary">
        💾 บันทึกการเบิก
    </button>
</div>

</form>

<hr>

<?php
$notifyStmt = $conn->prepare("
SELECT COUNT(*)
FROM supply_request_messages m
JOIN supply_requests r ON m.request_id = r.request_id
WHERE r.user_id = ?
AND m.sender_role = 'admin'
AND m.is_read = 0
");
$notifyStmt->execute([$memberId]);
$unread = (int)$notifyStmt->fetchColumn();
?>

<h5>
📋 รายการเบิกของฉัน
<?php if ($unread > 0): ?>
<span class="badge bg-danger">🔔 <?= $unread ?></span>
<?php endif; ?>
</h5>


<?php if (!$userRequests): ?>
    <div class="alert alert-light">ยังไม่มีรายการเบิก</div>
<?php else: ?>
<table class="table table-bordered align-middle">
<thead class="table-light">
<tr>
    <th>เลขที่</th>
    <th>วันที่</th>
    <th>คลัง</th>
    <th>สถานะ</th>
    <th width="260">ดำเนินการ</th>
</tr>
</thead>
<tbody>
<?php foreach ($userRequests as $r): ?>
<tr>
    <td><?= (int)$r['request_id'] ?></td>
    <td>
<?php
if (!empty($r['request_date'])) {
    $date = new DateTime($r['request_date']);
    $year = $date->format('Y') + 543; // แปลงเป็น พ.ศ.
    echo $date->format('d/m/') . $year;
}
?>
</td>
    <td><?= htmlspecialchars($r['warehouse_name']) ?></td>
    <td>
        <?= getSupplyStatusBadge($r['status_name']) ?>

    </td>
    <td>
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

        <?php if ((int)$r['status_id'] === 4): ?>
        <button class="btn btn-success btn-sm confirm-receive"
                data-id="<?= (int)$r['request_id'] ?>">✅ รับพัสดุแล้ว</button>
        <?php endif; ?>

        <a href="print_supply_pdf.php?request_id=<?= (int)$r['request_id'] ?>"
           target="_blank"
           class="btn btn-secondary btn-sm">📄 PDF</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

</div>

<?php if ($totalPages): ?>
<?php if ($totalPages > 1): ?>
<nav class="mt-3">
<ul class="pagination justify-content-center">

    <!-- หน้าแรก -->
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=1">« หน้าแรก</a>
    </li>

    <!-- ก่อนหน้า -->
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=<?= max(1, $page - 1) ?>">‹ ก่อนหน้า</a>
    </li>

    <!-- เลขหน้า (ตามช่วง) -->
    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
        </li>
    <?php endfor; ?>

    <!-- ถัดไป -->
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>">ถัดไป ›</a>
    </li>

    <!-- หน้าสุดท้าย -->
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=<?= $totalPages ?>">หน้าสุดท้าย »</a>
    </li>

</ul>
</nav>
<?php endif; ?>


<?php endif; ?>

<!-- ================= MODAL รายละเอียด ================= -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">📋 รายละเอียดการเบิก</h5>
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
            <tbody id="detailItems"></tbody>
        </table>
      </div>
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


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let supplies=[];

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

/* โหลดสินค้า */
$('.view-items').on('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    
    const id = $(this).data('id');
    console.log('View items clicked, request_id:', id);

    $('#detailItems').html('<tr><td colspan="3" class="text-center">กำลังโหลด...</td></tr>');

    $.getJSON('ajax_view_request_items.php', { request_id: id })
        .done(function (res) {
            console.log('Response received:', res);
            
            $('#detailItems').html('');

            if (res.items && res.items.length > 0) {
                res.items.forEach(r => {
                    const qtyOriginal = r.qty_original || r.qty;
                    const qtyCurrent = r.qty;
                    
                    $('#detailItems').append(`
                        <tr>
                            <td><strong>${r.supply_name}</strong></td>
                            <td class="text-center"><strong>${qtyOriginal}</strong></td>
                            <td class="text-center"><strong>${qtyCurrent}</strong></td>
                        </tr>
                    `);
                });
            } else {
                $('#detailItems').html('<tr><td colspan="3" class="text-center text-muted">ไม่มีรายการสินค้า</td></tr>');
            }

            const modal = new bootstrap.Modal(
                document.getElementById('detailModal'), 
                { backdrop: 'static', keyboard: false }
            );
            modal.show();
        })
        .fail(function (xhr, status, error) {
            console.error('AJAX Error:', status, error, xhr.responseText);
            alert('ไม่สามารถโหลดรายการได้: ' + (xhr.responseText || error));
        });
});


$('#warehouse').on('change', function () {
    const wid = $(this).val();
    supplies = [];
    $('#itemsTable tbody').empty();

    if (!wid) return;

    $.getJSON('ajax_get_supplies.php', { warehouse_id: wid }, function (res) {
        supplies = res;
    });
});

/* เพิ่มแถว */
$('#addRow').click(function () {
    if (!supplies.length) {
        alert('กรุณาเลือกคลังก่อน');
        return;
    }

    let opt = '';
    supplies.forEach(s => {
        opt += `
          <option value="${s.supply_id}"
                  data-stock="${s.stock_qty}">
            [${s.ProductCode}] ${s.supply_name}
          </option>
        `;
    });

    // 1️⃣ append แถวก่อน
    const row = $(`
    <tr>
        <td>
            <select name="supply_id[]" class="form-select supply select-supply" required>
                <option value="">-- เลือกสินค้า --</option>
                ${opt}
            </select>
        </td>
        <td class="stock text-center">-</td>
        <td>
            <input type="number"
                   name="qty[]"
                   class="form-control"
                   min="1"
                   required>
        </td>
        <td class="text-center">
            <button type="button"
                    class="btn btn-danger btn-sm del">x</button>
        </td>
    </tr>
    `);

    $('#itemsTable tbody').append(row);

    // 2️⃣ ค่อยสั่ง select2 หลังจาก append แล้ว
    row.find('.select-supply').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'ค้นหารหัสหรือชื่อสินค้า',
    
    });
});

$(document).on('change','.supply',function(){
    $(this).closest('tr')
           .find('.stock')
           .text($(this).find(':selected').data('stock'));
});

$(document).on('input','input[name="qty[]"]',function(){
    const stock = parseInt($(this).closest('tr').find('.stock').text());
    if(stock && this.value > stock){
        this.value = stock;
    }
});

$(document).on('click','.del',function(){
    $(this).closest('tr').remove();
});

/* ยืนยันรับพัสดุ */
$('.confirm-receive').on('click',function(){
    if(!confirm('ยืนยันรับพัสดุแล้ว?'))return;
    $.post('confirm_receive.php',{request_id:$(this).data('id')},()=>location.reload());
});
</script>
<script>
document.getElementById('submitRequest')
.addEventListener('click', function(e){
    e.preventDefault();
    Swal.fire({
        title: 'ยืนยันการบันทึก?',
        text: 'คุณต้องการบันทึกการเบิกใช่หรือไม่',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            e.target.closest('form').submit();
        }
    });
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

    // หยุด refresh ตอนปิด modal
    document.getElementById('chatModal')
        .addEventListener('hidden.bs.modal', function () {
            clearInterval(chatInterval);
        }, { once: true });

});


function loadChat(){

    $('#chatBox').html('');

    $.getJSON('ajax_get_messages.php',
        { request_id: currentRequest },
        function(res){

        res.forEach(msg => {

            const cls = msg.sender_role === 'user'
                        ? 'chat-user'
                        : 'chat-admin';

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

    $.post('ajax_send_message.php',{
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
