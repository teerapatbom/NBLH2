<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

requireLogin();
if (!hasPermission('DOC_UPDATE')) {
    http_response_code(403);
    exit('Access denied');
}
/* =========================
   AUTH
========================= */
$memberID = $_SESSION['UserID'];



$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

/* =========================
   UPDATE STATUS
========================= */
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $docIDs   = $_POST['doc_ids'] ?? [];
    $statuses = $_POST['status'] ?? [];

    if (empty($docIDs)) {
        $message = "กรุณาเลือกเอกสารอย่างน้อย 1 รายการ";
    } else {

        $errorList = [];
        $success   = 0;

        foreach ($docIDs as $docID) {

            $docID = (int)$docID;
            $newStatus = isset($statuses[$docID]) ? (int)$statuses[$docID] : 0;

            if (!$newStatus) continue;

            // ดึงสถานะปัจจุบัน
            $stmtCurrent = $conn->prepare("
                SELECT StatusID
                FROM documents
                WHERE DocID = :docid
                  AND MemberID = :member
            ");
            $stmtCurrent->execute([
                ':docid' => $docID,
                ':member' => $memberID
            ]);

            $currentStatus = (int)$stmtCurrent->fetchColumn();

            // ตรวจสอบลำดับ (ต้องเป็น +1 เท่านั้น)
            if ($newStatus != $currentStatus) {

                $stmtUpdate = $conn->prepare("
                    UPDATE documents
                    SET StatusID = :status
                    WHERE DocID = :docid
                      AND MemberID = :member
                ");

                $stmtUpdate->execute([
                    ':status' => $newStatus,
                    ':docid'  => $docID,
                    ':member' => $memberID
                ]);

                $success++;

            } else {
                $errorList[] = "เอกสารเลขที่ {$docID} ไม่สามารถข้ามหรือย้อนสถานะได้";
            }
        }

        if ($success > 0 && empty($errorList)) {
            $message = "อัปเดตสถานะเรียบร้อยแล้ว {$success} รายการ";
        } elseif ($success > 0) {
            $message = "อัปเดตสำเร็จ {$success} รายการ<br>" . implode("<br>", $errorList);
        } else {
            $message = implode("<br>", $errorList);
        }
    }
}


/* =========================
   FETCH STATUS TYPES
========================= */
$statusTypes = $conn->query("
    SELECT StatusID, StatusName
    FROM statustypes
    ORDER BY StatusID
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FETCH DOCUMENTS (BY USER)
========================= */
$filterStatus = $_GET['status_id'] ?? '';
$filterStart = $_GET['start_date'] ?? '';
$filterEnd   = $_GET['end_date'] ?? '';

/* =========================
   BUILD QUERY STRING (FOR PAGINATION)
========================= */
$queryParams = $_GET;
unset($queryParams['page']); // ไม่เอา page เดิม

$queryString = http_build_query($queryParams);

$where = "WHERE d.MemberID = :member";
$params = [':member' => $memberID];

if ($filterStatus !== '') {
    $where .= " AND d.StatusID = :status";
    $params[':status'] = (int)$filterStatus;
}

if ($filterStart !== '') {
    $where .= " AND DATE(d.CreatedAt) >= :start_date";
    $params[':start_date'] = $filterStart;
}

if ($filterEnd !== '') {
    $where .= " AND DATE(d.CreatedAt) <= :end_date";
    $params[':end_date'] = $filterEnd;
}



$sql = "
SELECT d.DocID, d.DocNo, d.Title, d.ControlNumber, d.Amount,
       d.StatusID, s.StatusName, dt.DocTypeName,
       m.Name AS MemberName,
       d.CreatedAt
FROM documents d
LEFT JOIN statustypes s ON d.StatusID = s.StatusID
LEFT JOIN doctypes dt ON d.DocTypeID = dt.DocTypeID
LEFT JOIN member m ON d.MemberID = m.MemberID
$where
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

$countSql = "
SELECT COUNT(*)
FROM documents d
$where
";
$countStmt = $conn->prepare($countSql);
foreach ($params as $k => $v) {
    $countStmt->bindValue($k, $v);
}
$countStmt->execute();
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);


/* =========================
   PAGINATION RANGE (1–10)
========================= */
$totalPages = max(1, $totalPages);
$page = max(1, min($page, $totalPages)); // กัน page หลุด

$maxLinks  = 10; // จำนวนเลขหน้าต่อช่วง
$startPage = (int)(floor(($page - 1) / $maxLinks) * $maxLinks) + 1;
$endPage   = min($startPage + $maxLinks - 1, $totalPages);



?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>อัปเดตสถานะเอกสาร</title>
  <?php require_once "layout_head.php"; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">


<style>
:root{
  --pink-main:#ec407a;
  --pink-soft:#fde4ec;
  --pink-dark:#d81b60;
  --pink-border:#f8bbd0;
}

body{
  font-family:'Sarabun',sans-serif;
  background:linear-gradient(to bottom right,#fce4ec,#ffffff);
}

/* title */
h3{
  color:var(--pink-dark);
  font-weight:700;
}

/* card 느낌 */
.container{
  background:#fff;
  border-radius:18px;
  padding:30px;
  box-shadow:0 10px 25px rgba(236,64,122,.15);
}

/* table */
.table{
  border-radius:14px;
  overflow:hidden;
}

.table thead{
  background:linear-gradient(90deg,var(--pink-main),#f48fb1);
  color:#fff;
}

.table-hover tbody tr:hover{
  background:var(--pink-soft);
}

/* badge */
.badge.bg-info{
  background:#fce4ec!important;
  color:var(--pink-dark)!important;
  font-weight:600;
}

/* form */
.form-select,.form-control{
  border-radius:12px;
  border:1.5px solid var(--pink-border);
}

.form-select:focus,.form-control:focus{
  border-color:var(--pink-main);
  box-shadow:0 0 0 .2rem rgba(20, 114, 11, 0.2);
}

/* buttons */
.btn-primary{
  background:linear-gradient(45deg,var(--pink-main),#f06292);
  border:none;
}

.btn-primary:hover{
  background:var(--pink-dark);
}

.btn-success{
  background:linear-gradient(45deg,#f06292,#f8bbd0);
  border:none;
  font-weight:600;
}

.btn-success:hover{
  background:var(--pink-dark);
}

.btn-secondary{
  background:#fff;
  color:var(--pink-main);
  border:1px solid var(--pink-main);
}

.btn-secondary:hover{
  background:var(--pink-soft);
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

/* text wrap */
.text-wrap-cell{
  max-width:360px;
  word-break:break-word;
}
.table-pink {
  background: linear-gradient(90deg, #ec407a, #f48fb1);
  color: #fff;
}

.table-pink th {
  background: transparent;
  color: #fff;
  font-weight: 600;
  border-color: rgba(255,255,255,.25);
}

</style>
</head>

<body class="bg-light">
<div class="container py-4">

<h3 class="mb-4 text-center">📌 อัปเดตสถานะเอกสาร</h3>

<?php if ($message): ?>
<div class="alert alert-info text-center">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>
<form method="get" class="row g-2 mb-3 align-items-end">

  <div class="col-md-3">
    <label class="form-label fw-bold">🔍 ค้นหาตามสถานะ</label>
    <select name="status_id" class="form-select">
      <option value="">-- ทุกสถานะ --</option>
      <?php foreach ($statusTypes as $s): ?>
        <option value="<?= $s['StatusID'] ?>"
          <?= ($filterStatus == $s['StatusID']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($s['StatusName']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-md-2">
    <label class="form-label fw-bold">📅 วันที่เริ่มต้น</label>
    <input type="date" name="start_date"
           value="<?= htmlspecialchars($filterStart) ?>"
           class="form-control">
  </div>

  <div class="col-md-2">
    <label class="form-label fw-bold">📅 วันที่สิ้นสุด</label>
    <input type="date" name="end_date"
           value="<?= htmlspecialchars($filterEnd) ?>"
           class="form-control">
  </div>

  <div class="col-md-2">
    <button class="btn btn-primary w-100">ค้นหา</button>
  </div>

  <div class="col-md-2">
    <a href="?" class="btn btn-secondary w-100">รีเซ็ต</a>
  </div>

</form>


<form method="post">
<div class="table-responsive">

<!-- bulk -->
<div class="row mb-3 align-items-center p-3 rounded"
     style="background:#fde4ec;border:1px solid #f8bbd0;">
  <div class="col-md-4">
    <label class="form-label fw-bold">
      ⚡ อัปเดตสถานะเอกสารที่เลือกทั้งหมด
    </label>
    <select id="bulkStatus" class="form-select">
      <option value="">-- เลือกสถานะ --</option>
      <?php foreach ($statusTypes as $s): ?>
        <option value="<?= $s['StatusID'] ?>">
          <?= htmlspecialchars($s['StatusName']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-md-3 mt-4 mt-md-0">
    <button type="button"
            class="btn btn-primary w-100"
            id="applyBulk">
      ✔ ใช้สถานะนี้กับทั้งหมด
    </button>
  </div>
</div>

<table class="table table-bordered table-hover align-middle">
<thead class="text-center table-pink">
<tr>
    <th><input type="checkbox" id="checkAll"></th>
    <th>เลขคุม</th>
    <th>เลขที่รับ</th>
    <th>ชื่อเรื่อง</th>
    <th>กลุ่มงาน</th>
    <th>จำนวนเงิน</th>
    <th>สถานะปัจจุบัน</th>
    <th>วันที่บันทึก</th>
    <th>ผู้รับผิดชอบ</th>
    <th>อัปเดตเป็น</th>
</tr>
</thead>
<tbody>

<?php foreach ($docs as $d): ?>
<tr>
    <td class="text-center">
        <input type="checkbox"
               name="doc_ids[]"
               value="<?= (int)$d['DocID'] ?>">
    </td>
     
    <td class="text-center fw-bold text-danger">
    <?= htmlspecialchars($d['DocNo'] ?? '') ?>
</td>

    <td><?= htmlspecialchars($d['ControlNumber']) ?></td>

    <td class="text-wrap-cell">
        <?= htmlspecialchars($d['Title']) ?>
    </td>

    <td><?= htmlspecialchars($d['DocTypeName']) ?></td>

    <td class="text-end">
        <?= number_format((float)$d['Amount'],2) ?>
    </td>

    <td class="text-center">
        <span class="badge bg-info">
            <?= htmlspecialchars($d['StatusName'] ?? 'ยังไม่กำหนด') ?>
        </span>
    </td>
<td class="text-center">
    <?= !empty($d['CreatedAt'])
        ? date('d/m/', strtotime($d['CreatedAt']))
          . (date('Y', strtotime($d['CreatedAt'])) + 543)
        : '-' ?>
  </td>
<td><?= htmlspecialchars($d['MemberName'] ?? '-') ?></td>
    <td>
        <select name="status[<?= (int)$d['DocID'] ?>]" class="form-select">
    <option value="">-- ไม่เปลี่ยน --</option>

    <?php foreach ($statusTypes as $s): ?>
    <?php if ($s['StatusID'] != $d['StatusID']): ?>
        <option value="<?= $s['StatusID'] ?>">
            <?= htmlspecialchars($s['StatusName']) ?>
        </option>
    <?php endif; ?>
<?php endforeach; ?>

</select>

    </td>
</tr>
<?php endforeach; ?>

<?php if (empty($docs)): ?>
<tr>
    <td colspan="10" class="text-center text-muted">
        ไม่พบเอกสารของคุณ
    </td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>

<div class="d-flex justify-content-end gap-2 mt-3">
<button name="update_status"
        class="btn btn-success px-4"
        onclick="return confirm('ยืนยันการอัปเดตสถานะหรือไม่?')">
        💾 บันทึกสถานะ
    </button>
</div>

</form>

</div>
<?php if ($totalPages): ?>
<nav class="mt-3">
<ul class="pagination justify-content-center">

    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?page=1&<?= $queryString ?>">« หน้าแรก</a>
    </li>

    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?page=<?= max(1, $page - 1) ?>&<?= $queryString ?>">
           ‹ ก่อนหน้า
        </a>
    </li>

    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link"
               href="?page=<?= $i ?>&<?= $queryString ?>">
               <?= $i ?>
            </a>
        </li>
    <?php endfor; ?>

    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?page=<?= min($totalPages, $page + 1) ?>&<?= $queryString ?>">
           ถัดไป ›
        </a>
    </li>

    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link"
           href="?page=<?= $totalPages ?>&<?= $queryString ?>">
           หน้าสุดท้าย »
        </a>
    </li>

</ul>
</nav>
<?php endif; ?>


<script>
document.getElementById('checkAll')?.addEventListener('change', function () {
    document.querySelectorAll('input[name="doc_ids[]"]').forEach(cb => {
        cb.checked = this.checked;
    });
});

document.getElementById('applyBulk').addEventListener('click', function () {
  const bulkValue = document.getElementById('bulkStatus').value;
  if (!bulkValue) return alert('กรุณาเลือกสถานะก่อน');

  let checked = false;
  document.querySelectorAll('input[name="doc_ids[]"]').forEach(cb => {
    if (cb.checked) {
      checked = true;
      document.querySelector(
        `select[name="status[${cb.value}]"]`
      ).value = bulkValue;
    }
  });

  if (!checked) alert('กรุณาติ๊กเลือกเอกสารอย่างน้อย 1 รายการ');
});
</script>


</body>
</html>
