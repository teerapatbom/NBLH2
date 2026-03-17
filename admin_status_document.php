<?php
declare(strict_types=1);
require_once "security.php";
require_once "connect.php";

requireLogin();
if (!hasPermission('DOC_STATUS')) {
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์เข้าหน้านี้');
}

/* =========================
   AUTH / ROLE
========================= */
$memberId = (int)$_SESSION['UserID'];

$isAdmin = (
    ($_SESSION['role'] ?? '') === 'admin'
    || (function_exists('hasPermission') && hasPermission('ADMIN'))
);

/* =========================
   DOC TYPE (เฉพาะ USER)
========================= */
$docTypeID   = '';
$docTypeName = 'ทุกกลุ่มงาน';

if (!$isAdmin) {

    $stmt = $conn->prepare("
        SELECT d.DocTypeID, d.DocTypeName
        FROM member m
        LEFT JOIN doctypes d ON m.DocTypeID = d.DocTypeID
        WHERE m.MemberID = ?
    ");
    $stmt->execute([$memberId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $docTypeID   = $row['DocTypeID']   ?? '';
    $docTypeName = $row['DocTypeName'] ?? 'ไม่ระบุกลุ่มงาน';

    // ✅ ถ้าเป็นกลุ่มงานพัสดุ A007 ให้เห็นทั้งหมด
    if ($docTypeID === 'A007') {
        $docTypeID   = '';
        $docTypeName = 'ทุกกลุ่มงาน';
    }
}


/* =========================
   PAGINATION
========================= */
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));

/* =========================
   WHERE CONDITION
========================= */
$search = trim($_GET['search'] ?? '');

$where  = [];
$params = [];

if (!$isAdmin && $docTypeID !== '') {
    $where[] = 'd.DocTypeID = :dtid';
    $params[':dtid'] = $docTypeID;
}

if ($search !== '') {

    $where[] = '
        (
            d.ControlNumber LIKE :q1
            OR d.Title LIKE :q2
            OR CAST(d.Amount AS CHAR) LIKE :q3
        )
    ';

    $params[':q1'] = "%{$search}%";
    $params[':q2'] = "%{$search}%";
    $params[':q3'] = "%{$search}%";
}


$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* COUNT */
$countSql = "
    SELECT COUNT(*)
    FROM documents d
    LEFT JOIN member m ON m.MemberID = d.MemberID
    LEFT JOIN doctypes c ON d.DocTypeID = c.DocTypeID
    $whereSql
";

$countStmt = $conn->prepare($countSql);

foreach ($params as $k => $v) {
    $countStmt->bindValue($k, $v);
}

$countStmt->execute();
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$page   = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

/* FETCH */
$sql = "
    SELECT 
        d.DocID,
        d.ControlNumber,
        d.Title,
        d.Amount,
        d.CreatedAt,
        d.Note,
        d.StatusID,
        s.StatusName,
        c.DocTypeName,
        m.Name,
        d.TypeDurableID
    FROM documents d
    LEFT JOIN statustypes s ON d.StatusID = s.StatusID
    LEFT JOIN doctypes c ON d.DocTypeID = c.DocTypeID
    LEFT JOIN member m ON m.MemberID = d.MemberID
    $whereSql
    ORDER BY d.DocID DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($sql);

foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}

$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================
   STATUS BADGE
========================= */
function getDocumentStatusBadge(?string $statusName): string
{
    $map = [
        '1. ผู้รับผิดชอบรับเรื่อง' => 'status-wait',
        '2. เสนอขออนุมัติสั่งซื้อ' => 'status-approved',
        '3. รับคำขออนุมัติสั่งซื้อ (แฟ้มลง)' => 'status-progress',
        '4. อยู่ระหว่างการสั่งซื้อ' => 'status-reject',
        '5. รอของ / คณะกรรมการตรวจรับ' => 'status-cancel',
        '6. ส่งเอกสารให้การเงิน/บัญชีดำเนินการ' => 'status-compile',
        '7. ดำเนินการเสร็จสิ้นทุกขั้นตอน' => 'status-success'
    ];

    $class = $map[$statusName] ?? 'status-default';

    return "<span class='status-badge {$class}'>"
           . htmlspecialchars($statusName ?? 'ยังไม่กำหนด')
           . "</span>";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ติดตามสถานะเอกสาร</title>
  <?php require_once "layout_head.php"; ?>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
  font-family:'Sarabun',sans-serif;
  background:linear-gradient(135deg,#fde4ec 0%,#ffffff 60%);
  min-height:100vh;
}

/* ===== TITLE ===== */
h3{
  color:#c2185b;
  font-weight:800;
  letter-spacing:.5px;
}

/* ===== SEARCH ===== */
.input-group-text{
  background:#fce4ec;
  border:1px solid #f48fb1;
}
.form-control{
  border:1px solid #f48fb1;
}
.form-control:focus{
  border-color:#ec407a;
  box-shadow:0 0 0 .2rem rgba(236,64,122,.2);
}
.btn-danger{
  background:linear-gradient(45deg,#ec407a,#f06292);
  border:none;
}
.btn-danger:hover{
  background:linear-gradient(45deg,#d81b60,#ec407a);
}

/* ===== CARD ===== */
.card{
  border-radius:22px;
  border:none;
  box-shadow:0 15px 35px rgba(236,64,122,.18);
}
.card-body{
  padding:1.5rem;
}

/* ===== TABLE ===== */
/* ทำให้ table ตัดขอบตาม card */
.card .table {
    border-collapse: separate;
    border-spacing: 0;
}
/* เปลี่ยนพื้นหลังเฉพาะหัวตาราง */
.table-header-pink {
    background: linear-gradient(90deg, #f8bbd0, #fce4ec);
}

.table-header-pink th {
    color: #880e4f;
    font-weight: 600;
    border-bottom: none;
}

/* ปรับตัวอักษร */
.table thead th {
    color: #880e4f;
    font-weight: 600;
    text-align: center;
    border-bottom: none;
}

/* ทำมุมโค้งเฉพาะซ้ายบน */
.table thead th:first-child {
    border-top-left-radius: 18px;
}

/* ทำมุมโค้งเฉพาะขวาบน */
.table thead th:last-child {
    border-top-right-radius: 18px;
}

/* ตัดเส้นขอบบนของ table */
.table {
    border-top: none;
}


/* ===== TEXT WRAP ===== */
.text-wrap{
  max-width:340px;
  word-break:break-word;
}

/* ===== BADGE STATUS ===== */
.badge{
  font-size:.85rem;
}
.badge.bg-info{
  background:linear-gradient(45deg,#29b6f6,#4fc3f7)!important;
}
.badge.bg-secondary{
  background:#bdbdbd!important;
}

/* ===== PAGINATION ===== */
.pagination{
  gap:6px;
}
.pagination .page-link{
  border-radius:12px;
  border:1px solid #f48fb1;
  color:#c2185b;
  font-weight:600;
}
.pagination .page-item.active .page-link{
  background:linear-gradient(45deg,#ec407a,#f06292);
  border-color:#ec407a;
  color:#fff;
}
.pagination .page-link:hover{
  background:#fde4ec;
}

/* ===== FOOTER SPACE ===== */
.mb-4{ margin-bottom:1.5rem!important; }
.text-wrap-cell {
  white-space: normal !important;   /* อนุญาตให้ขึ้นบรรทัดใหม่ */
  word-break: break-word;           /* ตัดคำยาว ๆ */
  overflow-wrap: break-word;        /* รองรับ browser ใหม่ */
  max-width: 300px;                 /* ปรับได้ตามใจ */
}
.btn-outline-danger{
  border-color:#ec407a;
  color:#ec407a;
}
.btn-outline-danger:hover{
  background:#ec407a;
  color:#fff;
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
/* ===== STATUS BADGE CUSTOM ===== */
.status-badge{
  padding:7px 16px;
  border-radius:25px;
  font-weight:600;
  font-size:.85rem;
  display:inline-block;
  min-width:120px;
  text-align:center;
}

/* รออนุมัติ */
.status-wait{
  background:#fff3cd;
  color:#b26a00;
}

/* อนุมัติแล้ว */
.status-approved{
  background:linear-gradient(45deg,#29b6f6,#4fc3f7);
  color:#fff;
}

/* กำลังดำเนินการ */
.status-progress{
  background:linear-gradient(45deg,#ab47bc,#ce93d8);
  color:#fff;
}
/* เสร็จสิ้น */
.status-compile{
  background:linear-gradient(45deg,#836FFF,#C1CDCD);
  color:#fff;
}

/* เสร็จสิ้น */
.status-success{
  background:linear-gradient(45deg,#00EE00,#66bb6a);
  color:#fff;
}

/* ตีกลับ */
.status-reject{
  background:linear-gradient(45deg,#FF6EB4,#FFB5C5);
  color:#fff;
}

/* ยกเลิก */
.status-cancel{
  background:#ef5350;
  color:#fff;
}

/* default */
.status-default{
  background:#e0e0e0;
  color:#424242;
}
/* text wrap */
.text-wrap-cell{
  max-width:380px;
  word-break:break-word;
}
.table-pink {
  background: linear-gradient(90deg, #ec407a, #f48fb1);
  color: #fff;
}

.table-pink th {
  background: transparent;
  color: #fff;
  font-weight: 900;
  border-color: rgba(255,255,255,.25);
}
</style>

</head>

<body>
<div class="container py-4">

<h3 class="text-center mb-4">⏳ ติดตามสถานะเอกสาร</h3>
<div class="text-center mb-3">
  <span class="badge rounded-pill px-4 py-2"
        style="background:#fce4ec;color:#c2185b;font-weight:600;">
    กลุ่มงาน: <?= htmlspecialchars($docTypeName) ?>
  </span>
</div>


<form method="get" class="mb-4">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="input-group shadow-sm">
        <span class="input-group-text bg-white text-danger fw-bold">
          🔍
        </span>
        <input type="text"
               name="search"
               class="form-control"
               placeholder="ค้นหาเลขที่รับ / ชื่อเรื่อง / จำนวนเงิน"
               value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-danger px-4">
          ค้นหา
        </button>
      </div>
    </div>
  </div>
</form>


<!-- TABLE -->
<div class="card">
<div class="card-body table-responsive">

<table class="table table-hover align-middle">
<thead class="text-center table-pink">
<tr>
  <th>เลขที่รับ</th>
  <th>ชื่อเรื่อง</th>
  <th>กลุ่มงาน</th>
  <th>จำนวนเงิน</th>
  <th>เจ้าของเรื่อง</th>
  <th>หมายเหตุ</th>
  <th>สถานะปัจจุบัน</th>
  <th>วันที่บันทึก</th>
  <th>เอกสาร</th>
</tr>
</thead>
<tbody>

<?php foreach ($docs as $d): ?>
<tr>
  <!-- เลขที่รับ -->
  <td>
    <?= htmlspecialchars($d['ControlNumber'] ?? '-') ?>
  </td>

  <!-- ชื่อเรื่อง (ตัดบรรทัดอัตโนมัติ) -->
  <td class="text-wrap" style="max-width:320px; word-break:break-word;">
    <?= htmlspecialchars($d['Title'] ?? '') ?>
  </td>

  <!-- กลุ่มงาน -->
  <td>
    <?= htmlspecialchars($d['DocTypeName'] ?? '-') ?>
  </td>

  <!-- จำนวนเงิน -->
  <td class="text-end">
    <?= $d['Amount'] !== null
        ? number_format((float)$d['Amount'], 2)
        : '-' ?>
  </td>

  <!-- เจ้าของเรื่่อง -->
  <td>
    <?= htmlspecialchars($d['Name'] ?? '-') ?>
  </td>

  <!-- หมายเหตุ -->
<td class="text-wrap-cell">
  <?= nl2br(htmlspecialchars($d['Note'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
</td>

  <!-- สถานะ -->
  <td class="text-center">
<?= getDocumentStatusBadge($d['StatusName'] ?? null) ?>
  </td>

  <!-- วันที่บันทึก -->
  <td class="text-center">
    <?= !empty($d['CreatedAt'])
        ? date('d/m/', strtotime($d['CreatedAt']))
          . (date('Y', strtotime($d['CreatedAt'])) + 543)
        : '-' ?>
  </td>

<td class="text-center">
<?php
$canPrint =
    !empty($d['TypeDurableID']) &&
    (int)$d['StatusID'] === 7;
?>

<?php if ($canPrint): ?>
  <a href="print_withdraw_durable.php?doc_id=<?= $d['DocID'] ?>"
   target="_blank"
   class="btn btn-sm btn-success">
   🖨 พิมพ์ใบเบิก
</a>
<?php else: ?>
  <span class="text-muted small">
    <?= empty($d['TypeDurableID'])
        ? 'ไม่ใช่ครุภัณฑ์'
        : 'ยังไม่เสร็จสิ้น' ?>
  </span>
<?php endif; ?>
</td>



</tr>

<?php endforeach; ?>

<?php if (empty($docs)): ?>
<tr>
  <td colspan="9" class="text-center text-muted">
    ไม่พบข้อมูลเอกสาร
  </td>
</tr>
<?php endif; ?>

</tbody>
</table>
<?php if ($totalPages > 1): ?>

<?php
$maxPages  = 10;
$startPage = (int)(floor(($page - 1) / $maxPages) * $maxPages) + 1;
$endPage   = min($startPage + $maxPages - 1, $totalPages);



if ($endPage - $startPage + 1 < $maxPages) {
    $startPage = max(1, $endPage - $maxPages + 1);
}
?>

<nav class="mt-3">
<ul class="pagination justify-content-center">

    <!-- หน้าแรก -->
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?<?= http_build_query(array_merge($_GET, ['page'=>1])) ?>">
           « หน้าแรก
        </a>
    </li>

    <!-- ก่อนหน้า -->
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?<?= http_build_query(array_merge($_GET, ['page'=>max(1,$page-1)])) ?>">
           ‹ ก่อนหน้า
        </a>
    </li>

    <!-- เลขหน้า -->
    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link"
               href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>">
                <?= $i ?>
            </a>
        </li>
    <?php endfor; ?>

    <!-- ถัดไป -->
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?<?= http_build_query(array_merge($_GET, ['page'=>min($totalPages,$page+1)])) ?>">
           ถัดไป ›
        </a>
    </li>

    <!-- หน้าสุดท้าย -->
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?<?= http_build_query(array_merge($_GET, ['page'=>$totalPages])) ?>">
           หน้าสุดท้าย »
        </a>
    </li>

</ul>
</nav>

<?php endif; ?>



</div>
</div>

</div>
</body>
</html>
