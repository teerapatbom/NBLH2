<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

/* =========================
   AUTH + PERMISSION
========================= */
requireLogin();
if (!hasPermission('REPORT')) {
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์เข้าหน้านี้');
}

/* =========================
   ดึงปีงบประมาณ
========================= */
$yearStmt = $conn->query("
    SELECT FiscalYear
    FROM budgetyear
    ORDER BY FiscalYear DESC
");
$budgetYears = $yearStmt->fetchAll(PDO::FETCH_COLUMN);

/* =========================
   รับค่าการค้นหา
========================= */
$fiscalYear = $_GET['year'] ?? '';
$startDate  = $_GET['start'] ?? '';
$endDate    = $_GET['end'] ?? '';

$where  = [];
$params = [];

if ($fiscalYear !== '') {
    $where[]  = "FiscalYear = ?";
    $params[] = $fiscalYear;
}
if ($startDate !== '') {
    $where[]  = "d.CreatedAt >= ?";
    $params[] = $startDate . ' 00:00:00';
}
if ($endDate !== '') {
    $where[]  = "d.CreatedAt <= ?";
    $params[] = $endDate . ' 23:59:59';
}

/* =========================
   Query เอกสาร (แสดง 10 รายการล่าสุด)
========================= */
$sql = "SELECT d.DocID, d.FiscalYear, d.ControlNumber, d.Title,
               d.SubmitDate, d.Amount, d.Note,
               m.Name AS Submitter
        FROM nblh.documents d
        LEFT JOIN nblh.member m ON d.MemberID = m.MemberID";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY d.DocID DESC LIMIT 10";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================
   คำนวณยอดรวม (รวมทั้งหมดตามเงื่อนไข)
========================= */
$totalSql = "SELECT SUM(d.Amount)
             FROM nblh.documents d";

if ($where) {
    $totalSql .= " WHERE " . implode(" AND ", $where);
}

$totalStmt = $conn->prepare($totalSql);
$totalStmt->execute($params);

$totalAmount = (float)($totalStmt->fetchColumn() ?? 0);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>รายงานสรุปเอกสาร</title>
<?php require_once "layout_head.php"; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
body{
    font-family:'Sarabun',sans-serif;
    background:linear-gradient(135deg,#fff0f5,#ffe6f2);
}

.card-custom{
    border:none;
    border-radius:18px;
    box-shadow:0 10px 30px rgba(0,0,0,0.08);
}

.btn-pink{
    background:linear-gradient(45deg,#ff4da6,#ff80bf);
    color:#fff;
    border:none;
    font-weight:600;
}
.btn-pink:hover{
    transform:translateY(-2px);
    box-shadow:0 5px 15px rgba(255,0,128,0.3);
}

.table thead{
    background:linear-gradient(45deg,#ff99cc,#ff66a3);
    color:white;
}

.table-hover tbody tr:hover{
    background:#fff0f7;
}

.summary-card{
    border-radius:15px;
    background:white;
    padding:20px;
    text-align:center;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}
.summary-title{
    font-size:14px;
    color:#888;
}
.summary-value{
    font-size:22px;
    font-weight:700;
}
.table {
    table-layout: fixed;   /* สำคัญมาก */
}

.table td,
.table th {
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
}

/* บังคับความกว้างเฉพาะคอลัมน์ */
.col-title {
    width: 22%;
}

.col-note {
    width: 20%;
}

</style>
</head>

<body>
<div class="container py-5">

<h3 class="text-center mb-4 fw-bold">
    📋 รายงานทะเบียนรับหนังสือ
</h3>

<!-- ฟอร์มค้นหา -->
<div class="card card-custom p-4 mb-4">
<form method="GET" class="row g-3">

    <div class="col-md-3">
        <label class="form-label">ปีงบประมาณ</label>
        <select name="year" class="form-select">
            <option value="">-- ทุกปี --</option>
            <?php foreach ($budgetYears as $y): ?>
                <option value="<?= htmlspecialchars($y) ?>"
                    <?= $fiscalYear == $y ? 'selected' : '' ?>>
                    <?= htmlspecialchars($y) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">ตั้งแต่วันที่</label>
        <input type="date" name="start" class="form-control"
               value="<?= htmlspecialchars($startDate) ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label">ถึงวันที่</label>
        <input type="date" name="end" class="form-control"
               value="<?= htmlspecialchars($endDate) ?>">
    </div>

    <div class="col-md-3 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-pink flex-grow-1">
            🔍 ค้นหา
        </button>

        <button type="submit"
                formaction="export_report_pdf.php"
                formmethod="GET"
                class="btn btn-danger flex-grow-1 fw-bold">
            📄 PDF
        </button>
    </div>

</form>
</div>


<!-- ตาราง -->
<table class="table table-bordered table-hover align-middle bg-white shadow-sm">
<thead class="text-center">
<tr>
    <th>เลขคุม</th>
    <th>ปีงบ</th>
    <th>เลขที่รับ</th>
    <th class="col-title">ชื่อเรื่อง</th>
    <th>วันที่บันทึก</th>
    <th>จำนวนเงิน</th>
    <th>ผู้ยื่นเรื่อง</th>
    <th class="col-note">หมายเหตุ</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
    <td class="text-center"><?= (int)$row['DocID'] ?></td>
    <td class="text-center"><?= htmlspecialchars($row['FiscalYear']?? '') ?></td>
    <td><?= htmlspecialchars($row['ControlNumber']?? '') ?></td>
    <td class="col-title">
    <?= nl2br(htmlspecialchars($row['Title']?? '')) ?>
</td>
    <td>
        <?= $row['SubmitDate']
            ? date('d/m/Y', strtotime($row['SubmitDate']))
            : '-' ?>
    </td>
    <td class="text-end">
        <?= number_format((float)$row['Amount'], 2) ?>
    </td>
    <td><?= htmlspecialchars($row['Submitter']?? '') ?></td>
    <td class="col-note">
    <?= nl2br(htmlspecialchars($row['Note']?? '')) ?>
</td>
</tr>
<?php endforeach; ?>

<?php if (!$rows): ?>
<tr>
    <td colspan="8" class="text-center text-muted">ไม่พบข้อมูล</td>
</tr>
<?php endif; ?>
</tbody>

<tfoot>
<tr>
    <th colspan="5" class="text-end">รวม:</th>
    <th class="text-end text-success">
        <?= number_format($totalAmount, 2) ?> บาท
    </th>
    <th colspan="2"></th>
</tr>
</tfoot>
</table>

</div>
</body>
</html>
