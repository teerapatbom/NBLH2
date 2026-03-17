<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

/* =========================
   AUTH + PERMISSION
========================= */
requireLogin();
if (!hasPermission('BUDGET')) {
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์เข้าหน้านี้');
}
/* =========================
   PAGINATION
========================= */
$perPage = 6;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

/* นับจำนวนทั้งหมด */
$totalStmt = $conn->query("SELECT COUNT(*) FROM budgetyear");
$totalRows = (int)$totalStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);

/* =========================
   CSRF TOKEN
========================= */
$csrf = $_SESSION['csrf_token'];
$message = "";

/* =========================
   ADD YEAR
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_year'])) {

    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        exit('CSRF detected');
    }

    $fiscalYear = trim($_POST['fiscalYear'] ?? '');

    if ($fiscalYear !== '') {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM budgetyear WHERE FiscalYear = ?"
        );
        $stmt->execute([$fiscalYear]);

        if ($stmt->fetchColumn() > 0) {
            $message = "ปีงบประมาณ $fiscalYear ซ้ำ กรุณาเพิ่มปีอื่น";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO budgetyear (FiscalYear) VALUES (?)"
            );
            $stmt->execute([$fiscalYear]);

            header("Location: admin_budget_manage.php?success=เพิ่มปีงบประมาณเรียบร้อย");
            exit;
        }
    }
}

/* =========================
   DELETE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {

    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        exit('CSRF detected');
    }

    $id = (int)$_POST['delete_id'];

    $stmt = $conn->prepare(
        "DELETE FROM budgetyear WHERE YearID = ?"
    );
    $stmt->execute([$id]);

    header("Location: admin_budget_manage.php?success=ลบปีงบประมาณเรียบร้อย");
    exit;
}

/* =========================
   EDIT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_year'])) {

    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        exit('CSRF detected');
    }

    $id = (int)$_POST['year_id'];
    $fiscalYear = trim($_POST['fiscalYear'] ?? '');

    if ($fiscalYear !== '') {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM budgetyear
             WHERE FiscalYear = ? AND YearID != ?"
        );
        $stmt->execute([$fiscalYear, $id]);

        if ($stmt->fetchColumn() > 0) {
            $message = "ปีงบประมาณ $fiscalYear ซ้ำ กรุณาใช้ปีอื่น";
        } else {
            $stmt = $conn->prepare(
                "UPDATE budgetyear SET FiscalYear = ? WHERE YearID = ?"
            );
            $stmt->execute([$fiscalYear, $id]);

            header("Location: admin_budget_manage.php?success=แก้ไขปีงบประมาณเรียบร้อย");
            exit;
        }
    }
}

/* =========================
   FETCH DATA
========================= */
$stmt = $conn->prepare(
    "SELECT * FROM budgetyear
     ORDER BY FiscalYear DESC
     LIMIT :limit OFFSET :offset"
);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();



?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>จัดการปีงบประมาณ</title>
      <?php require_once "layout_head.php"; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Bootstrap CSS + Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet" />
   <style>
body {
  font-family: 'Sarabun', sans-serif;
  background: linear-gradient(to bottom right, #f8bbd0, #ffffff);
  min-height: 100vh;
  padding: 60px 15px;
  color: #9c2743;
}

.container {
  max-width: 720px;
  background: #fff0f6;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(255, 182, 193, 0.3);
  padding: 40px 30px;
  backdrop-filter: saturate(180%) blur(10px);
  transition: box-shadow 0.3s ease;
}

.container:hover {
  box-shadow: 0 15px 45px rgba(255, 105, 180, 0.4);
}

h3 {
  font-weight: 700;
  color: #d81b60;
  margin-bottom: 2rem;
  text-align: center;
  letter-spacing: 1.2px;
  text-shadow: 1px 1px 3px #f8bbd0;
}

.btn-nav {
  width: 160px;
  font-weight: 600;
  background-color: #d81b60;
  color: white;
  border-radius: 12px;
  box-shadow: 0 6px 12px rgba(216, 27, 96, 0.4);
  transition: background-color 0.3s ease, box-shadow 0.3s ease;
}

.btn-nav:hover {
  background-color: #ad1457;
  box-shadow: 0 8px 18px rgba(173, 20, 87, 0.6);
  color: white;
}

form.add-year-form {
  display: flex;
  gap: 15px;
  justify-content: center;
  margin-bottom: 2.5rem;
}

form.add-year-form input[type="text"] {
  max-width: 220px;
  font-size: 1.2rem;
  text-align: center;
  border-radius: 12px;
  border: 2px solid #d81b60;
  padding: 10px 15px;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

form.add-year-form input[type="text"]:focus {
  border-color: #ad1457;
  box-shadow: 0 0 8px #d81b6088;
  outline: none;
}

form.add-year-form button {
  padding: 12px 30px;
  font-size: 1.15rem;
  font-weight: 700;
  border-radius: 12px;
  box-shadow: 0 6px 12px rgba(173, 20, 87, 0.4);
  transition: background-color 0.3s ease, box-shadow 0.3s ease;
}

form.add-year-form button:hover {
  background-color: #ad1457;
  box-shadow: 0 8px 18px rgba(173, 20, 87, 0.7);
}

.table-responsive {
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(255, 182, 193, 0.15);
}

table.table {
  border-collapse: separate !important;
  border-spacing: 0 12px;
  width: 100%;
}

table thead tr {
  background-color: #ad1457;
  color: white;
  border-radius: 12px;
  box-shadow: 0 4px 8px rgba(173, 20, 87, 0.25);
}

table thead th {
  padding: 16px 15px;
  font-weight: 700;
  font-size: 1rem;
  border: none !important;
  text-align: center;
}

table tbody tr {
  background: #fff0f6;
  box-shadow: 0 3px 6px rgba(0, 0, 0, 0.07);
  border-radius: 12px;
  transition: transform 0.25s ease, box-shadow 0.25s ease;
  cursor: default;
}

table tbody tr:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 30px rgba(173, 20, 87, 0.3);
}

table tbody td {
  padding: 15px 12px;
  vertical-align: middle;
  font-weight: 600;
  font-size: 1.05rem;
  border: none !important;
  text-align: center;
  color: #ad1457;
}

table tbody td.actions {
  white-space: nowrap;
  width: 190px;
}

button.btn-sm {
  font-weight: 600;
  border-radius: 8px;
  padding: 6px 12px;
  box-shadow: 0 3px 6px rgba(173, 20, 87, 0.15);
  transition: background-color 0.3s ease, box-shadow 0.3s ease;
}

button.btn-sm:hover {
  box-shadow: 0 5px 14px rgba(173, 20, 87, 0.3);
}

.btn-warning {
  background-color: #f48fb1;
  border-color: #f48fb1;
  color: #212529;
}

.btn-warning:hover {
  background-color: #c2185b;
  border-color: #ad1457;
  color: white;
}

.btn-danger {
  background-color: #c2185b;
  border-color: #c2185b;
  color: white;
}

.btn-danger:hover {
  background-color: #880e4f;
  border-color: #6a0d3a;
}

.modal-content {
  border-radius: 16px;
  box-shadow: 0 15px 40px rgba(173, 20, 87, 0.3);
}

.modal-header {
  border-bottom: none;
  font-weight: 700;
  color: #ad1457;
  font-size: 1.5rem;
  justify-content: center;
}

.modal-footer {
  border-top: none;
  justify-content: center;
}

.form-control {
  border-radius: 12px;
  border: 2px solid #ad1457;
  padding: 12px 15px;
  font-weight: 600;
  font-size: 1.1rem;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-control:focus {
  border-color: #c2185b;
  box-shadow: 0 0 10px #d81b6088;
  outline: none;
}

.alert-message {
  max-width: 720px;
  margin: 0 auto 20px;
  font-weight: 600;
  color: #ad1457;
  background: #fde7f0;
  border: 2px solid #f8bbd0;
  border-radius: 12px;
  padding: 10px 15px;
  text-align: center;
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

<div class="container">
    <h3>📚 จัดการปีงบประมาณ</h3>

     <!-- ปุ่มย้อนกลับ / ไปข้างหน้า -->
    <div class="d-flex justify-content-center mb-4 gap-3">
</div>

<?php if ($message): ?>
  <div class="alert alert-warning alert-message" role="alert">
    <?= htmlspecialchars($message) ?>
  </div>
<?php endif; ?>


<form action="" method="POST" class="add-year-form" autocomplete="off" novalidate>
  <input type="hidden" name="csrf_token"
         value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">

  <input type="text" name="fiscalYear" placeholder="กรอกปีงบ (เช่น 2566)" required>
  <button type="submit" name="add_year" class="btn btn-primary btn-nav">เพิ่ม</button>
</form>


    <div class="table-responsive">
      <table class="table">
          <thead>
              <tr>
                  <th style="width:10%;">ลำดับ</th>
                  <th style="width:60%;">ปีงบประมาณ</th>
                  <th style="width:30%;">จัดการ</th>
              </tr>
          </thead>
<tbody>
<?php $i = $offset + 1; foreach ($rows as $row): ?>
<tr>
  <td><?= $i++ ?></td>
  <td><?= htmlspecialchars($row['FiscalYear']) ?></td>
  <td class="actions">


    <!-- Delete -->
<form method="POST" style="display:inline"
      onsubmit="return confirm('ต้องการลบปีงบประมาณ <?= htmlspecialchars($row['FiscalYear']) ?> หรือไม่?');">
  <input type="hidden" name="csrf_token"
         value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">
  <input type="hidden" name="delete_id" value="<?= $row['YearID'] ?>">
  <button type="submit" class="btn btn-danger btn-sm">
    <i class="bi bi-trash"></i> ลบ
  </button>
</form>


  </td>
</tr>

<!-- Edit Modal -->
<?php endforeach; ?>
</tbody>

      </table>

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

    <!-- เลขหน้า (1–10) -->
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


    </div>
</div>
<?php if ($totalPages > 1): ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Activate tooltips
  const tooltipTriggerList = document.querySelectorAll('[title]')
  tooltipTriggerList.forEach(el => {
    new bootstrap.Tooltip(el)
  })
</script>


</body>
</html>
