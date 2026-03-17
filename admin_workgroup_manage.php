<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

/* =========================
   Auth + Permission
========================= */
requireLogin();

if (!hasPermission('WORKGROUP')) {
    http_response_code(403);
    exit("คุณไม่มีสิทธิ์เข้าหน้านี้");
}

/* =========================
   CSRF token
========================= */
$csrf = $_SESSION['csrf_token'];
/* =========================
   Pagination
========================= */
$perPage = 6;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$search = trim($_GET['search'] ?? '');

/* =========================
   COUNT TOTAL
========================= */
if ($search !== '') {
    $countStmt = $conn->prepare("
        SELECT COUNT(*)
        FROM doctypes
        WHERE DocTypeID LIKE ? OR DocTypeName LIKE ?
    ");
    $countStmt->execute(["%$search%", "%$search%"]);
} else {
    $countStmt = $conn->query("SELECT COUNT(*) FROM doctypes");
}

$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

/* =========================
   FETCH DATA (LIMIT)
========================= */
if ($search !== '') {
    $stmt = $conn->prepare("
        SELECT *
        FROM doctypes
        WHERE DocTypeID LIKE ? OR DocTypeName LIKE ?
        ORDER BY DocTypeID
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, "%$search%");
    $stmt->bindValue(2, "%$search%");
    $stmt->bindValue(3, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(4, $offset, PDO::PARAM_INT);
} else {
    $stmt = $conn->prepare("
        SELECT *
        FROM doctypes
        ORDER BY DocTypeID
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
}

$stmt->execute();
$rows = $stmt->fetchAll();

/* =========================
   ADD DocType
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_docType'])) {

    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        exit("CSRF detected");
    }

    $docTypeID   = trim($_POST['docTypeID'] ?? '');
    $docTypeName = trim($_POST['docTypeName'] ?? '');

    if ($docTypeID === '' || $docTypeName === '') {
        header("Location: admin_workgroup_manage.php?warning=กรุณากรอกข้อมูลให้ครบถ้วน");
        exit;
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM doctypes
        WHERE LOWER(DocTypeName) = LOWER(?) OR DocTypeID = ?
    ");
    $stmt->execute([$docTypeName, $docTypeID]);

    if ($stmt->fetchColumn() > 0) {
        header("Location: admin_workgroup_manage.php?warning=รหัสหรือชื่อกลุ่มงานซ้ำ");
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO doctypes (DocTypeID, DocTypeName)
        VALUES (?, ?)
    ");
    $stmt->execute([$docTypeID, $docTypeName]);

    header("Location: admin_workgroup_manage.php?success=เพิ่มกลุ่มงานเรียบร้อยแล้ว");
    exit;
}



/* =========================
   DELETE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {

// ก่อน DELETE
$chk = $conn->prepare("SELECT COUNT(*) FROM member WHERE DocTypeID = ?");
$chk->execute([$id]);

if ($chk->fetchColumn() > 0) {
    header("Location: admin_workgroup_manage.php?warning=ไม่สามารถลบได้ มีผู้ใช้งานอยู่ในกลุ่มงานนี้");
    exit;
}

    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        exit("CSRF detected");
    }

    $id = trim($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM doctypes WHERE DocTypeID = ?");
    $stmt->execute([$id]);

    header("Location: admin_workgroup_manage.php?success=ลบกลุ่มงานเรียบร้อยแล้ว");
    exit;
}

/* =========================
   EDIT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_docType'])) {

    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        exit("CSRF detected");
    }

    $id   = trim($_POST['docType_id']);
    $name = trim($_POST['docTypeName'] ?? '');

    if ($name === '') {
        header("Location: admin_workgroup_manage.php?warning=กรุณากรอกชื่อกลุ่มงาน");
        exit;
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM doctypes
        WHERE LOWER(DocTypeName) = LOWER(?) AND DocTypeID != ?
    ");
    $stmt->execute([$name, $id]);

    
    if ($stmt->fetchColumn() > 0) {
        header("Location: admin_workgroup_manage.php?warning=ชื่อกลุ่มงานซ้ำ");
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE doctypes
        SET DocTypeName = ?
        WHERE DocTypeID = ?
    ");
    $stmt->execute([$name, $id]);

    header("Location: admin_workgroup_manage.php?success=แก้ไขกลุ่มงานเรียบร้อยแล้ว");
    exit;
    
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title> จัดการกลุ่มงาน</title>
    <?php require_once "layout_head.php"; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet" />
  <style>
    body {
      font-family: 'Sarabun', sans-serif;
      background: linear-gradient(to bottom right, #f8bbd0, #ffffff);
      min-height: 100vh;
      padding: 2rem 1rem;
      color: #9c2750;
    }
    .container {
      max-width: 720px;
      margin: 0 auto;
      background: #fff;
      border-radius: 20px;
      padding: 2.5rem 3rem;
      box-shadow: 0 10px 30px rgba(216, 27, 96, 0.25);
    }
    header {
      font-weight: 700;
      font-size: 2rem;
      color: #d81b60;
      text-align: center;
      margin-bottom: 2rem;
      text-shadow: 1px 1px 6px #f8bbd0aa;
    }
    .alert {
      border-radius: 12px;
      font-weight: 600;
      max-width: 700px;
      margin: 0 auto 1.5rem;
      box-shadow: 0 3px 10px #f8bbd0cc;
    }
    form.row.g-2.mb-4.align-items-center input.form-control {
      border: 2px solid #d81b60;
      border-radius: 12px;
      padding: 0.6rem 1rem;
      font-weight: 600;
      text-align: center;
      color: #9c2750;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    form.row.g-2.mb-4.align-items-center input.form-control:focus {
      border-color: #b0124b;
      box-shadow: 0 0 10px #b0124baa;
      outline: none;
    }
    form.row.g-2.mb-4.align-items-center button.btn-primary {
      background-color: #d81b60;
      border-color: #d81b60;
      border-radius: 50px;
      font-weight: 700;
      padding: 0.55rem 1.8rem;
      box-shadow: 0 5px 18px rgba(216, 27, 96, 0.3);
      transition: background-color 0.3s ease, box-shadow 0.3s ease;
    }
    form.row.g-2.mb-4.align-items-center button.btn-primary:hover,
    form.row.g-2.mb-4.align-items-center button.btn-primary:focus {
      background-color: #b0124b;
      border-color: #b0124b;
      box-shadow: 0 7px 22px rgba(176, 18, 75, 0.6);
    }

    .table-responsive {
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 8px 28px rgba(216, 27, 96, 0.15);
    }
    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0 12px;
    }
    thead tr {
      background-color: #d81b60;
      color: white;
      border-radius: 12px;
    }
    thead th {
      padding: 1rem 1.2rem;
      font-weight: 700;
      text-align: center;
    }
    tbody tr {
      background: #fff0f6;
      border-radius: 12px;
      transition: box-shadow 0.3s ease, transform 0.3s ease;
    }
    tbody tr:hover {
      background: #ffd6e8;
      box-shadow: 0 8px 20px rgba(216, 27, 96, 0.3);
      transform: translateY(-4px);
    }
    tbody td {
      padding: 1rem;
      text-align: center;
      font-weight: 600;
      color: #9c2750;
      border: none !important;
    }
    tbody td.actions {
      white-space: nowrap;
    }
    button.btn-sm {
      border-radius: 8px;
      padding: 0.4rem 0.9rem;
      font-weight: 600;
      cursor: pointer;
      border: none;
      transition: background-color 0.3s ease;
    }
    button.btn-warning {
      background-color: #f48fb1;
      color: white;
      box-shadow: 0 3px 10px #f48fb144;
    }
    button.btn-warning:hover {
      background-color: #c2185b;
      box-shadow: 0 5px 15px #c2185b77;
    }
    button.btn-danger {
      background-color: #ec407a;
      color: white;
      box-shadow: 0 3px 10px #ec407a44;
    }
    button.btn-danger:hover {
      background-color: #ad1457;
      box-shadow: 0 5px 15px #ad145777;
    }

    /* Modal */
    .modal-content {
      border-radius: 16px;
      box-shadow: 0 15px 40px rgba(216, 27, 96, 0.3);
    }
    .modal-header {
      border-bottom: none;
      font-weight: 700;
      color: #d81b60;
      font-size: 1.5rem;
      justify-content: center;
    }
    .modal-footer {
      border-top: none;
      justify-content: center;
    }
    .form-control {
      border-radius: 12px;
      border: 2px solid #d81b60;
      padding: 12px 15px;
      font-weight: 600;
      font-size: 1.1rem;
      color: #9c2750;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .form-control:focus {
      border-color: #b0124b;
      box-shadow: 0 0 10px #b0124baa;
      outline: none;
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
  </style>
</head>
<body>
  <div class="container py-4">
    <header>🗄️จัดการกลุ่มงาน</header>

  <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
  <?php elseif (isset($_GET['warning'])): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($_GET['warning']) ?></div>
  <?php endif; ?>

  <form action="" method="POST" class="row g-2 mb-4 align-items-center">
    <input type="hidden" name="csrf_token"
  value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">
      <div class="col-md-3">
        <input type="text" name="docTypeID" class="form-control" placeholder="รหัสกลุ่มงาน" required />
      </div>
      <div class="col-md-6">
        <input type="text" name="docTypeName" class="form-control" placeholder="ชื่อกลุ่มงาน" required />
      </div>
      <div class="col-md-3">
        <button type="submit" name="add_docType" class="btn btn-primary w-100">
          <i class="bi bi-plus-circle"></i> เพิ่ม
        </button>
      </div>
    </form>
    <form method="GET" class="row g-2 mb-4 align-items-center">
  <div class="col-md-9">
    <input type="text" name="search" class="form-control" placeholder="ค้นหาชื่อกลุ่มงาน..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
  </div>
  <div class="col-md-3">
    <button type="submit" class="btn btn-outline-secondary w-100">
      <i class="bi bi-search"></i> ค้นหา
    </button>
  </div>
</form>

    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th style="width: 20%;">รหัส</th>
            <th style="width: 60%;">ชื่อกลุ่มงาน</th>
            <th style="width: 20%;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
     <?php foreach ($rows as $row): ?>
<tr>
  <td><?= htmlspecialchars($row['DocTypeID']) ?></td>
  <td><?= htmlspecialchars($row['DocTypeName']) ?></td>
  <td class="actions">
  <div class="d-flex gap-2 justify-content-center">
    <button type="button"
        class="btn btn-warning btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#editModal<?= $row['DocTypeID'] ?>">
  <i class="bi bi-pencil"></i> แก้ไข
</button>

   <form method="POST" onsubmit="return confirm('ยืนยันการลบ?');" style="margin: 0;">
  <input type="hidden" name="csrf_token"
    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">
  <input type="hidden" name="delete_id" value="<?= $row['DocTypeID'] ?>">
  <button type="submit" class="btn btn-danger btn-sm">
    <i class="bi bi-trash"></i> ลบ
  </button>
</form>

          <!-- Modal แก้ไข -->
          <div class="modal fade" id="editModal<?= $row['DocTypeID'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
  <form method="POST">
    <input type="hidden" name="csrf_token"
           value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">

    <div class="modal-header">
      <h5 class="modal-title">แก้ไขกลุ่มงาน</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>

    <div class="modal-body">
      <input type="hidden" name="docType_id"
             value="<?= $row['DocTypeID'] ?>">
      <input type="text"
             name="docTypeName"
             value="<?= htmlspecialchars($row['DocTypeName']) ?>"
             class="form-control"
             required>
    </div>

    <div class="modal-footer">
      <button type="submit"
              name="edit_docType"
              class="btn btn-primary">บันทึก</button>
      <button type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal">ยกเลิก</button>
    </div>
  </form>
</div>

            </div>
          </div>
         </td>
</tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$maxLinks = 10; // จำนวนเลขหน้าที่โชว์
$startPage = (int)(floor(($page - 1) / $maxLinks) * $maxLinks) + 1;
$endPage   = min($startPage + $maxLinks - 1, $totalPages);
?>

<nav class="mt-4">
  <ul class="pagination justify-content-center">

    <!-- หน้าแรก -->
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link"
         href="?page=1&search=<?= urlencode($search ?? '') ?>">
        « หน้าแรก
      </a>
    </li>

    <!-- ก่อนหน้า -->
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link"
         href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search ?? '') ?>">
        ‹ ก่อนหน้า
      </a>
    </li>

    <!-- เลขหน้า (1–10 ตามช่วง) -->
    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link"
           href="?page=<?= $i ?>&search=<?= urlencode($search ?? '') ?>">
          <?= $i ?>
        </a>
      </li>
    <?php endfor; ?>

    <!-- ถัดไป -->
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
      <a class="page-link"
         href="?page=<?= min($totalPages, $page + 1) ?>&search=<?= urlencode($search ?? '') ?>">
        ถัดไป ›
      </a>
    </li>

    <!-- หน้าสุดท้าย -->
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
      <a class="page-link"
         href="?page=<?= $totalPages ?>&search=<?= urlencode($search ?? '') ?>">
        หน้าสุดท้าย »
      </a>
    </li>

  </ul>
</nav>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

