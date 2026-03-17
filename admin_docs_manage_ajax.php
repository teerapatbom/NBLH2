<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

/* =========================
   AUTH + PERMISSION
========================= */
requireLogin();
if (!hasPermission('DOC_MANAGE')) {
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์เข้าหน้านี้');
}

/* =========================
   HELPER
========================= */
function clean(string $v): string {
    return trim($v);
}

$message = "";

/* =========================
   SAVE (ADD / EDIT)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {

    $id        = (int)($_POST['docId'] ?? 0);
    $fy        = trim($_POST['fiscalYear'] ?? '');
    $type      = trim($_POST['docType'] ?? '');
    $date      = $_POST['submitDate'] ?? '';
    $control   = trim($_POST['controlNumber'] ?? '');
    $title     = trim($_POST['title'] ?? '');
    $amount    = ($_POST['amount'] !== '') ? (float)$_POST['amount'] : null;
    $submitter = trim($_POST['submitter'] ?? '');
    $note      = trim($_POST['note'] ?? '');
    $docTypeID     = $_POST['docTypeID'] ?? null;
    $memberID      = !empty($_POST['member_id']) ? (int)$_POST['member_id'] : null;
    $typeDurableID = !empty($_POST['type_durable_id']) ? (int)$_POST['type_durable_id'] : null;

$errors = [];

if ($fy === '')            $errors[] = 'ปีงบประมาณ';
if ($docTypeID === null)   $errors[] = 'กลุ่มงาน';
if ($date === '')          $errors[] = 'วันที่รับ';
if ($control === '')       $errors[] = 'เลขที่รับ';
if ($title === '')         $errors[] = 'ชื่อเรื่อง';
if ($memberID === null)    $errors[] = 'เจ้าของเรื่อง';
if ($typeDurableID === null)    $errors[] = 'ประเภท';

if (!empty($errors)) {
    $message = 'กรุณากรอกข้อมูลให้ครบ: ' . implode(', ', $errors);
    } else {

        /* =========================
           UPDATE
        ========================= */
        if ($id > 0) {

            $stmt = $conn->prepare("
                UPDATE documents SET
                    FiscalYear    = :fy,
                    SubmitDate    = :sd,
                    ControlNumber = :cn,
                    Title         = :tt,
                    Amount        = :am,
                    Note          = :nt,
                    DocTypeID    = :dtid,
                    MemberID     = :mid,
                    TypeDurableID= :tdid
                WHERE DocID = :id
            ");

            $stmt->execute([
    ':fy'   => $fy,
    ':sd'   => $date,
    ':cn'   => $control,
    ':tt'   => $title,
    ':am'   => $amount,
    ':nt'   => $note,
    ':dtid' => $docTypeID,
    ':mid'  => $memberID,
    ':tdid' => $typeDurableID,
    ':id'   => $id
]);

            $message = "แก้ไขเอกสารสำเร็จ";

        }

        
        /* =========================
           INSERT
        ========================= */
        else {

        // ========================
// สร้างเลขรัน DocNo อัตโนมัติ
// ========================

// หาค่าสูงสุดของปีนี้
$runStmt = $conn->prepare("
    SELECT MAX(DocNo) as maxno 
    FROM documents 
    WHERE FiscalYear = :fy
");
$runStmt->execute([':fy' => $fy]);
$row = $runStmt->fetch(PDO::FETCH_ASSOC);

$nextRun = 1;

if (!empty($row['maxno'])) {
    // ตัดเอาเลข 4 ตัวท้าย
    $lastRun = substr($row['maxno'], -4);
    $nextRun = (int)$lastRun + 1;
}

// เติม 0 ให้ครบ 4 หลัก
$runNumber = str_pad((string)$nextRun, 4, '0', STR_PAD_LEFT);

$year2 = substr($fy, -2);  // เอา 2 ตัวท้ายของปี
$newDocNo = $year2 . $runNumber;
$stmt = $conn->prepare("
    INSERT INTO documents
    (DocNo, FiscalYear, SubmitDate, ControlNumber,
     Title, Amount, Note,
     CreatedAt, IsDeleted,
     DocTypeID, MemberID, TypeDurableID)
    VALUES
    (:docno, :fy, :sd, :cn,
     :tt, :am, :nt,
     NOW(), 0,
     :dtid, :mid, :tdid)
");

$stmt->execute([
    ':docno'=> $newDocNo,
    ':fy'   => $fy,
    ':sd'   => $date,
    ':cn'   => $control,
    ':tt'   => $title,
    ':am'   => $amount,
    ':nt'   => $note,
    ':dtid' => $docTypeID,
    ':mid'  => $memberID,
    ':tdid' => $typeDurableID
]);

            $message = "เพิ่มเอกสารสำเร็จ";
        }
    }
}


/* =========================
   DELETE (SOFT)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $did = (int)$_POST['delete_id'];

    $stmt = $conn->prepare(
        "UPDATE documents SET IsDeleted = 1 WHERE DocID = ?"
    );
    $stmt->execute([$did]);

    $message = "ลบเอกสารเรียบร้อย";
}

/* =========================
   EDIT DATA
========================= */
$editDoc = null;
if (isset($_GET['edit_id'])) {
    $stmt = $conn->prepare(
        "SELECT * FROM documents WHERE DocID = ?"
    );
    $stmt->execute([(int)$_GET['edit_id']]);
    $editDoc = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =========================
   BADGE
========================= */
function getDocTypeBadge(?string $name): string {
    if (!$name) return '-';

    $map = [
        'สัญญา'   => 'danger',
        'คำสั่ง'  => 'warning',
        'หนังสือ' => 'success',
        'รายงาน'  => 'info'
    ];

    $class = $map[$name] ?? 'secondary';
    return "<span class='badge bg-$class'>" . htmlspecialchars($name) . "</span>";
}


/* =========================
   MASTER DATA
========================= */
$years = $conn->query(
    "SELECT FiscalYear FROM budgetyear ORDER BY FiscalYear DESC"
)->fetchAll(PDO::FETCH_COLUMN);

// ดึงปีล่าสุด
$latestYear = $conn->query("
    SELECT FiscalYear 
    FROM budgetyear 
    ORDER BY FiscalYear DESC 
    LIMIT 1
")->fetchColumn();

$docTypes = $conn->query(
    "SELECT DocTypeID, DocTypeName FROM doctypes ORDER BY DocTypeID"
)->fetchAll(PDO::FETCH_ASSOC);

$members = $conn->query("
    SELECT MemberID, Name
    FROM member where Doctypeid='A007'
    ORDER BY Name
")->fetchAll(PDO::FETCH_ASSOC);


$durables = $conn->query("
    SELECT TypeDurableID, TypeDurableName
    FROM typedurable
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   SELECTED VALUE
========================= */
$selectedYear = $_POST['fiscalYear']
    ?? ($editDoc['FiscalYear'] ?? ($years[0] ?? ''));

$selectedType = $_POST['docType']
    ?? ($editDoc['DocType'] ?? '');

$selectedSubmitter = $_POST['submitter']
    ?? ($editDoc['Submitter'] ?? '');


/* =========================
   PAGINATION
========================= */
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

/* =========================
   SEARCH
========================= */
$search = trim($_GET['search'] ?? '');
$q = "%$search%";

$countSql = "
SELECT COUNT(*)
FROM documents d
LEFT JOIN member m ON d.MemberID = m.MemberID
WHERE d.IsDeleted = 0
  AND d.FiscalYear = :fy
  AND (
       d.DocNo LIKE :q0
    OR d.Title LIKE :q1
    OR d.ControlNumber LIKE :q2
    OR m.Name LIKE :q3
  )
";

$countStmt = $conn->prepare($countSql);

$countStmt->execute([
    ':fy' => $latestYear,
    ':q0' => $q,
    ':q1' => $q,
    ':q2' => $q,
    ':q3' => $q
]);

$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);

/* ป้องกัน page หลุด */
$totalPages = max(1, $totalPages);
$page = min($page, $totalPages);

$offset = ($page - 1) * $perPage;

/* =========================
   แบ่งช่วง 1–10
========================= */
$maxLinks  = 10;
$startPage = (int)(floor(($page - 1) / $maxLinks) * $maxLinks) + 1;
$endPage   = min($startPage + $maxLinks - 1, $totalPages);

/* =========================
   FETCH DOCUMENTS
========================= */
$stmt = $conn->prepare("
    SELECT 
        d.*,
        m.Name AS MemberName,
        m.Position,
        t.DocTypeName
    FROM documents d
    LEFT JOIN member m ON d.MemberID = m.MemberID
    LEFT JOIN doctypes t ON d.DocTypeID = t.DocTypeID
    WHERE d.IsDeleted = 0
      AND d.FiscalYear = :fy
      AND (
           d.DocNo LIKE :q0
        OR d.Title LIKE :q1
        OR d.ControlNumber LIKE :q2
        OR m.Name LIKE :q3
      )
    ORDER BY d.DocID DESC
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':fy', $latestYear);
$stmt->bindValue(':q0', $q);
$stmt->bindValue(':q1', $q);
$stmt->bindValue(':q2', $q);
$stmt->bindValue(':q3', $q);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);



/* =========================
   SAFETY: ป้องกัน foreach error
========================= */
if (!$docs) {
    $docs = [];
}

// หา DocID สูงสุดของปีนั้น
$stmt = $conn->prepare("
    SELECT MAX(DocNo) 
    FROM documents 
    WHERE FiscalYear = ?
");
$stmt->execute([$latestYear]);

$lastDocID = (int)$stmt->fetchColumn();
$nextDocID = $lastDocID + 1;
   
?>



<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>จัดการเอกสาร</title>
    <?php require_once "layout_head.php"; ?>
<!-- Font -->
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <style>
    body {
      font-family: 'Sarabun', sans-serif;
      background: linear-gradient(to bottom right, #f8bbd0, #ffffff);
      min-height: 100vh;
      padding-top: 30px;
    }

    h3 {
      color: #ad1457;
      font-weight: 700;
    }

    .form-control:focus, .form-select:focus {
      border-color: #ec407a;
      box-shadow: 0 0 0 0.2rem rgba(236, 64, 122, 0.25);
    }

    .btn-pink {
      background: linear-gradient(45deg, #ec407a, #f06292);
      border: none;
      color: white;
      border-radius: 50px;
      padding: 10px 24px;
      font-weight: bold;
      box-shadow: 0 5px 15px rgba(236, 64, 122, 0.3);
    }

    .btn-pink:hover {
      background-color: #d81b60;
    }

    .card {
      border: none;
      border-radius: 1rem;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .card-header {
      background: linear-gradient(45deg, #ec407a, #f48fb1);
      color: white;
      font-weight: bold;
    }

    .table th {
      background-color: #f06292 !important;
      color: white;
    }

    .badge.bg-primary {
      background-color: #ec407a !important;
    }

    .select2-container--default .select2-selection--single {
      border-radius: 0.5rem;
      border: 1px solid #ec407a;
      padding: 0.4rem;
    }

    .select2-selection__rendered {
      color: #555;
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

.select2-container {
  width:100% !important;
}

.select2-container--default .select2-selection--single {
  height:38px;
  padding-top:4px;
}

.select2-search__field{
  padding:6px;
}
  </style>
</head>
<body>
<div class="container py-4">

<h3 class="mb-4 text-center">📋 จัดการเอกสาร</h3>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

    <!-- CSS สไตล์ชมพู-ขาว -->
<style>
  .search-bar .input-group-text {
    background-color: #fff;
    border-color: #ec407a;
    color: #ec407a;
  }

  .search-bar input.form-control {
    border-color: #ec407a;
    background-color: #fff;
    color: #333;
  }

  .search-bar input.form-control:focus {
    box-shadow: 0 0 0 0.2rem rgba(236, 64, 122, 0.25);
    border-color: #ec407a;
  }

  .btn-pink {
    background: linear-gradient(45deg, #ec407a, #fce4ec);
    border: 1px solid #ec407a;
    color: white;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    transition: 0.3s ease;
  }

  .btn-pink:hover {
    background: linear-gradient(45deg, #d81b60, #f8bbd0);
    color: white;
  }

  .btn-reset {
    background-color: #fff;
    color: #ec407a;
    border: 1px solid #ec407a;
    transition: 0.3s ease;
  }

  .btn-reset:hover {
    background-color: #fce4ec;
    color: #d81b60;
  }
</style>

<!-- ฟอร์มค้นหา + ปุ่มรีเซ็ต -->
<form method="get" class="input-group mb-4 shadow-lg search-bar" style="max-width:700px;margin:auto;">
  <span class="input-group-text rounded-start-pill">
    <i class="bi bi-search fs-5"></i>
  </span>
  <input type="search" name="search"
         class="form-control form-control-lg rounded-0"
         placeholder="ค้นหาเลขที่รับ / ชื่อเรื่อง / จำนวนเงิน / เลขคุม"
         value="<?= htmlspecialchars($search ?? '') ?>">
  <button class="btn btn-lg fw-semibold btn-pink">ค้นหา</button>
  <button type="button"
          class="btn btn-lg fw-semibold btn-reset rounded-end-pill"
          onclick="window.location.href=window.location.pathname;">
    รีเซ็ต
  </button>
</form>

<style>
  /* ปรับ card หัวฟอร์ม */
  .card-header.bg-info {
    background: linear-gradient(90deg, #ec407a, #f8bbd0) !important;
    color: white;
    font-weight: bold;
    font-size: 1.1rem;
  }

  .card {
    border-radius: 16px;
    border: 1px solid #f8bbd0;
  }

  .form-control, .form-select {
    border-radius: 10px;
    border-color: #ec407a;
  }

  .form-control:focus, .form-select:focus {
    box-shadow: 0 0 0 0.2rem rgba(236, 64, 122, 0.25);
    border-color: #ec407a;
  }

  textarea.form-control {
    resize: none;
  }

  .btn-success {
    background: linear-gradient(45deg, #ec407a, #f8bbd0);
    border: none;
    font-weight: bold;
  }

  .btn-success:hover {
    background: linear-gradient(45deg, #d81b60, #f48fb1);
  }

  .btn-secondary {
    background-color: #fce4ec;
    color: #ec407a;
    border: 1px solid #ec407a;
  }

  .btn-secondary:hover {
    background-color: #f8bbd0;
    color: #d81b60;
  }

  .btn-danger {
    background-color: #e53935;
    border: none;
  }

  .table thead {
    background: linear-gradient(90deg, #ec407a, #f48fb1);
    color: white;
  }

  .badge.bg-primary {
    background-color: #ec407a !important;
  }

  .btn-outline-warning, .btn-outline-danger {
    border-radius: 8px;
  }

  .modal-header.bg-danger {
    background: linear-gradient(45deg, #e53935, #ef9a9a);
  }

  .modal-content {
    border-radius: 16px;
  }

  .btn-close {
    filter: invert(1);
  }
  .form-label {
  font-weight: 600;
  color: #ad1457;
  margin-bottom: 6px;
}

.form-soft {
  border-radius: 12px;
  border: 1.5px solid #ec407a;
  transition: 0.25s ease;
}

.form-soft:focus {
  border-color: #d81b60;
  box-shadow: 0 0 0 0.2rem rgba(236, 64, 122, 0.25);
}

.form-soft-lg {
  font-size: 1.05rem;
  font-weight: 600;
}

.card-body {
  padding: 2rem;
}
.docid-center {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
}

.docid-box {
  background: linear-gradient(45deg, #fce4ec, #f8bbd0);
  border: 2px solid #ec407a;
  border-radius: 18px;
  padding: 13px 25px;
  text-align: center;
  min-width: 180px;
  box-shadow: 0 6px 16px rgba(236, 64, 122, 0.25);
}

.docid-box.next {
  background: linear-gradient(45deg, #e8f5e9, #c8e6c9);
  border-color: #2e7d32;
}

.docid-label {
  font-size: 0.95rem;
  font-weight: 600;
  color: #555;
}

.docid-number {
  font-size: 1.8rem;
  font-weight: 800;
  color: #ad1457;
  margin-top: 4px;
}

.docid-box.next .docid-number {
  color: #2e7d32;
}

.docid-arrow {
  font-size: 2rem;
  font-weight: bold;
  color: #888;
}
.text-wrap-cell {
  white-space: normal !important;   /* อนุญาตให้ขึ้นบรรทัดใหม่ */
  word-break: break-word;           /* ตัดคำยาว ๆ */
  overflow-wrap: break-word;        /* รองรับ browser ใหม่ */
  max-width: 300px;                 /* ปรับได้ตามใจ */
}


</style>


<div class="card mb-4 shadow-sm">
  <div class="card-header bg-gradient bg-info text-white">
        <div class="docid-center mb-4">

  <div class="docid-box">
    <div class="docid-label">📄 เลขล่าสุด</div>
    <div class="docid-number current"><?= $lastDocID ?: '-' ?></div>
  </div>

  <div class="docid-arrow">➜</div>

  <div class="docid-box next">
    <div class="docid-label">➕ เลขถัดไป</div>
    <div class="docid-number"><?= $nextDocID ?></div>
  </div>
</div>
    <?= $editDoc ? "✏️ แก้ไขเอกสาร" : "➕ เพิ่มเอกสารใหม่" ?>
  </div>




<div class="card-body">
<form method="post">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="docId" value="<?= (int)($editDoc['DocID'] ?? 0) ?>">

<div class="row g-4">

  <!-- ปีงบ -->
  <div class="col-md-2">
    <label class="form-label">ปีงบประมาณ</label>
    <select name="fiscalYear" class="form-select form-soft" required>
      <?php foreach ($years as $year): ?>
        <option value="<?= htmlspecialchars($year) ?>"
          <?= $selectedYear == $year ? 'selected' : '' ?>>
          <?= htmlspecialchars($year) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

<!-- กลุ่มงาน -->
<div class="col-md-4">
  <label class="form-label">กลุ่มงาน</label>

  <!-- select เดิม (เก็บชื่อ) -->
<select id="docTypeSelect" name="docTypeID" class="form-select form-soft" required>
  <option value="">-- เลือกกลุ่มงาน --</option>
  <?php foreach ($docTypes as $type): ?>
    <option value="<?= htmlspecialchars($type['DocTypeID']) ?>"
      <?= ($editDoc['DocTypeID'] ?? '') === $type['DocTypeID'] ? 'selected' : '' ?>>
      <?= htmlspecialchars($type['DocTypeID'].' - '.$type['DocTypeName']) ?>
    </option>
  <?php endforeach; ?>
</select>
</div>


  <!-- วันที่รับ -->
  <div class="col-md-2">
    <label class="form-label">วันที่รับ</label>
    <input type="date" name="submitDate"
           class="form-control form-soft" required
           value="<?= htmlspecialchars($editDoc['SubmitDate'] ?? '') ?>">
  </div>

  <!-- เลขที่รับ -->
  <div class="col-md-4">
    <label class="form-label">เลขที่รับ</label>
    <input name="controlNumber"
           class="form-control form-soft"
           value="<?= htmlspecialchars($editDoc['ControlNumber'] ?? '') ?>" required>
  </div>

  <!-- ชื่อเรื่อง -->
  <div class="col-md-6">
    <label class="form-label">ชื่อเรื่อง</label>
    <input name="title"
           class="form-control form-soft form-soft-lg"
           value="<?= htmlspecialchars($editDoc['Title'] ?? '') ?>" required>
  </div>

  <!-- จำนวนเงิน -->
  <div class="col-md-3">
    <label class="form-label">จำนวนเงิน</label>
    <input type="number" step="0.01" name="amount"
           class="form-control form-soft text-end"
           value="<?= htmlspecialchars($editDoc['Amount'] ?? '') ?>">
  </div>

<!-- เจ้าของเรื่อง -->
<div class="col-md-3">
  <label class="form-label">เจ้าของเรื่อง</label>
<select name="member_id" class="form-select form-soft" required>
  <option value="">-- เลือกเจ้าของเรื่อง --</option>
  <?php foreach ($members as $m): ?>
    <option value="<?= (int)$m['MemberID'] ?>"
      <?= ($editDoc['MemberID'] ?? '') == $m['MemberID'] ? 'selected' : '' ?>>
      <?= htmlspecialchars($m['Name']) ?>
    </option>
  <?php endforeach; ?>
</select>
</div>



<div class="col-md-4">
  <label class="form-label">ประเภท</label>
  <select name="type_durable_id" class="form-select form-soft" required>
    <option value="">-- เลือกประเภท --</option>
    <?php foreach ($durables as $t): ?>
      <option value="<?= $t['TypeDurableID'] ?>"
        <?= ($editDoc['TypeDurableID'] ?? '') == $t['TypeDurableID'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($t['TypeDurableName']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

  <!-- หมายเหตุ -->
  <div class="col-12">
    <label class="form-label">หมายเหตุ</label>
    <textarea name="note" rows="3"
      class="form-control form-soft"><?= htmlspecialchars($editDoc['Note'] ?? '') ?></textarea>
  </div>

</div>

<div class="d-flex justify-content-end gap-3 mt-4">

  <button type="submit"
          name="save"
          class="btn btn-success px-4"
          onclick="return confirm('ยืนยันการ<?= $editDoc ? "แก้ไข" : "เพิ่ม" ?>เอกสารหรือไม่?')">
    💾 <?= $editDoc ? "บันทึกการแก้ไข" : "เพิ่มเอกสาร" ?>
  </button>

  <a href="?" class="btn btn-outline-secondary px-4">
    ยกเลิก
  </a>

</div>


</form>
</div>

</div>

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead class="table-dark text-center">
<tr>
<th>เลขคุม</th><th>ปีงบ</th><th>กลุ่มงาน</th><th>เลขที่รับ</th>
<th>ชื่อเรื่อง</th><th>จำนวนเงิน</th><th>เจ้าของเรื่อง</th>
<th>หมายเหตุ</th><th>วันที่รับ</th><th>วันที่บันทึก</th><th>จัดการ</th>
</tr>
</thead>
<tbody>

<?php foreach ($docs as $d): ?>
<tr>
<td><?= htmlspecialchars($d['DocNo'] ?? '') ?></td>
<td><span class="badge bg-primary"><?= htmlspecialchars($d['FiscalYear']) ?></span></td>
<td><?= getDocTypeBadge($d['DocTypeName'] ?? '-') ?></td>
<td><?= htmlspecialchars($d['ControlNumber']) ?></td>
<td class="text-wrap-cell">
  <?= htmlspecialchars($d['Title']) ?>
</td>
<td><?= number_format((float)$d['Amount'],2) ?></td>
<td>
  <?= htmlspecialchars($d['MemberName'] ?? '-') ?>
</td>
<td class="text-wrap-cell">
  <?= nl2br(htmlspecialchars($d['Note']?? '')) ?>
</td>

<!-- วันที่รับ -->
<td class="text-center">
<?= $d['SubmitDate']
    ? date('d/m/', strtotime($d['SubmitDate'])) .
      (date('Y', strtotime($d['SubmitDate'])) + 543)
    : '-' ?>
</td>

<!-- วันที่บันทึก -->
<td class="text-center">
<?= $d['CreatedAt']
    ? date('d/m/', strtotime($d['CreatedAt'])) .
      (date('Y', strtotime($d['CreatedAt'])) + 543) .
      date(' H:i', strtotime($d['CreatedAt'])) . ' น.'
    : '-' ?>
</td>

<td>
  <div class="d-flex gap-1 justify-content-center">
    <a href="?edit_id=<?= (int)$d['DocID'] ?>" class="btn btn-sm btn-outline-warning">✏️</a>
    <form method="post" onsubmit="return confirm('ลบ <?= htmlspecialchars($d['Title']) ?> หรือไม่?')">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="delete_id" value="<?= (int)$d['DocID'] ?>">
      <button class="btn btn-sm btn-outline-danger">🗑️</button>
    </form>
  </div>
</td>
</tr>
<?php endforeach; ?>

<?php if (empty($docs)): ?>
<tr><td colspan="11" class="text-center text-muted">ไม่พบข้อมูล</td></tr>
<?php endif; ?>

</tbody>
</table>
<?php if ($totalPages > 1): ?>
<nav class="mt-4">
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

  <!-- เลขหน้าแบบช่วง 1–10 -->
  <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
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

</div>
</div>


<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <input type="hidden" name="delete_id" value="<?= $editDoc['DocID'] ?? 0 ?>">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">ยืนยันการลบ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        คุณแน่ใจหรือไม่ว่าต้องการลบเอกสาร "<strong><?= htmlspecialchars($editDoc['Title']) ?></strong>" ?
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-danger">ลบ</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
      </div>
    </form>
  </div>
</div>


  <script>
function refreshDocID() {
  fetch('get_docid.php')
    .then(res => res.json())
    .then(d => {
      document.querySelector('.docid-current').textContent = '#' + d.last;
      document.querySelector('.docid-next').textContent = '#' + d.next;
    });
}
</script>
<script>
$(document).ready(function(){

  $('#docTypeSelect').select2({
      placeholder: "🔍 ค้นหากลุ่มงาน...",
      width: '100%',
      allowClear: true,
      minimumResultsForSearch: 0,
      dropdownAutoWidth: true
  });

});
</script>
</body>
</html>
