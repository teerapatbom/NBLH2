<?php
declare(strict_types=1);

require_once "security.php"; // session + helper
require_once "connect.php";  // PDO MySQL
requireLogin();

if (!hasPermission('USER_MANAGE')) {
    http_response_code(403);
    exit("คุณไม่มีสิทธิ์เข้าหน้านี้");
}


if (($_SESSION['Status'] ?? '') !== 'ADMIN') {
    http_response_code(403);
   exit('คุณไม่มีสิทธิ์เข้าหน้านี้เข้าได้เฉพาะผู้ดูแลระบบเท่านั้น');
}
/* =========================
   Pagination setup
========================= */
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

/* =========================
   Count total users
========================= */
$totalUsers = (int)$conn
    ->query("SELECT COUNT(*) FROM member")
    ->fetchColumn();

$totalPages = (int)ceil($totalUsers / $perPage);

/* =========================
   Get users (limit)
========================= */
$stmt = $conn->prepare("
    SELECT 
        m.MemberID,
        m.Name,
        m.Username,
        m.Position,
        m.Status,
        d.DocTypeName,
        d.DocTypeID
    FROM member m
    LEFT JOIN doctypes d 
      ON m.DocTypeID COLLATE utf8mb4_unicode_ci
       = d.DocTypeID COLLATE utf8mb4_unicode_ci
    ORDER BY m.MemberID ASC
    LIMIT :limit OFFSET :offset
");


$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$users = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>จัดการผู้ใช้</title>
      <?php require_once "layout_head.php"; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet" />
    <style>
        body {
            background: linear-gradient(to bottom, #ffe6f0, #ffffff);
            font-family: 'Sarabun', sans-serif;
        }
        .table th {
            background-color: #ffb3d9;
        }
        .btn-pink {
            background-color: #ff66a3;
            color: white;
        }
        .btn-pink:hover {
            background-color: #e05591;
            color: white;
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
<div class="container py-5">
    <h3 class="mb-4 text-center">จัดการผู้ใช้</h3>

    <!-- ช่องค้นหา -->
    <div class="mb-4">
        <input type="text" id="searchInput" class="form-control"
 placeholder="ค้นหา ชื่อ, Username, ตำแหน่ง, กลุ่มงาน, สถานะ" />
    </div>

    <table class="table table-bordered table-hover bg-white shadow-sm">
        <thead class="text-center">
<tr>
  <th>ลำดับ</th>
  <th>ชื่อ-สกุล</th>
  <th>ชื่อผู้ใช้</th>
  <th>ตำแหน่ง</th>
  <th>กลุ่มงาน</th>
  <th>สถานะ</th>
  <th>จัดการ</th>
</tr>

        </thead>
        <tbody class="align-middle text-center">
   <?php
$i = $offset + 1;
foreach ($users as $row) {
    $name = htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8');
    $username = htmlspecialchars($row['Username'], ENT_QUOTES, 'UTF-8');
    $position = htmlspecialchars($row['Position'], ENT_QUOTES, 'UTF-8');
    $status = $row['Status'];
?>
<tr>
    <td><?= $i ?></td>
    <td><?= $name ?></td>
    <td><?= $username ?></td>
    <td><?= $position ?></td>
        <td>
  <?= htmlspecialchars($row['DocTypeName'] ?? '-') ?>
  <?php if (!empty($row['DocTypeID'])): ?>
    <small class="text-muted">
      (<?= htmlspecialchars($row['DocTypeID']) ?>)
    </small>
  <?php endif; ?>
</td>
    <td><?= $status ?></td>
    <td>
        <button class="btn btn-info btn-sm edit-btn"
  data-id="<?= $row['MemberID'] ?>"
  data-name="<?= $name ?>"
  data-username="<?= $username ?>"
  data-position="<?= $position ?>"
  data-status="<?= $status ?>"
  data-doctype="<?= htmlspecialchars($row['DocTypeID'] ?? '') ?>">
  ✏️ แก้ไข
</button>


        <button class="btn btn-danger btn-sm delete-btn"
            data-id="<?= $row['MemberID'] ?>">
            🗑️ ลบ
        </button>

        <button class="btn btn-pink btn-sm reset-btn"
            data-id="<?= $row['MemberID'] ?>"
            data-name="<?= $name ?>">
            🔄 รีเซ็ตรหัสผ่าน
        </button>
        <button class="btn btn-warning btn-sm perm-btn"
    data-id="<?= $row['MemberID'] ?>"
    data-name="<?= $name ?>">
    🔐 สิทธิ์
</button>

    </td>
</tr>
<?php
$i++;
}
?>

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


    <?php if ($totalPages > 1): ?>
<?php endif; ?>

</div>

<!-- Modal รีเซ็ตรหัสผ่าน -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="resetPasswordForm">
      <input type="hidden" name="csrf_token"
       value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
      <div class="modal-content">
        <div class="modal-header bg-pink">
          <h5 class="modal-title">🔄 รีเซ็ตรหัสผ่าน: <span id="userFullName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="member_id" id="memberID" />
          <div class="mb-3">
            <label>รหัสผ่านใหม่</label>
            <input type="password" name="new_password" class="form-control" required />
          </div>
          <div class="mb-3">
            <label>ยืนยันรหัสผ่าน</label>
            <input type="password" name="confirm_password" class="form-control" required />
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-pink">บันทึก</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modal แก้ไขข้อมูลผู้ใช้ -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editUserForm">
      <input type="hidden" name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

      <div class="modal-content">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title">✏️ แก้ไขข้อมูลผู้ใช้</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="member_id" id="editMemberID" />

          <div class="mb-3">
            <label>ชื่อ-สกุล</label>
            <input type="text" name="name" id="editName" class="form-control" required />
          </div>

          <div class="mb-3">
            <label>ชื่อผู้ใช้</label>
            <input type="text" name="username" id="editUsername" class="form-control" required />
          </div>

          <div class="mb-3">
            <label>ตำแหน่ง</label>
            <input type="text" name="position" id="editPosition" class="form-control" required />
          </div>

          <!-- ✅ เพิ่มกลุ่มงาน -->
          <div class="mb-3">
            <label>กลุ่มงาน</label>
            <select name="doctype_id" id="editDocType" class="form-select" required>
              <option value="">-- เลือกกลุ่มงาน --</option>
              <?php
              $docTypes = $conn->query("SELECT DocTypeID, DocTypeName FROM doctypes ORDER BY DocTypeName")->fetchAll();
              foreach ($docTypes as $d):
              ?>
                <option value="<?= htmlspecialchars($d['DocTypeID']) ?>">
                  <?= htmlspecialchars($d['DocTypeName']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label>สถานะ</label>
            <select name="status" id="editStatus" class="form-select" required>
              <option value="USER">USER</option>
              <option value="ADMIN">ADMIN</option>
            </select>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-info">บันทึก</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        </div>
      </div>
    </form>
  </div>
</div>


<div class="modal fade" id="permissionModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="permissionForm">
      <input type="hidden" name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="member_id" id="permMemberID">

      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title">
            🔐 กำหนดสิทธิ์: <span id="permUserName"></span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <?php
          $perms = $conn->query("SELECT * FROM permissions")->fetchAll();
          foreach ($perms as $p):
          ?>
          <div class="form-check">
            <input class="form-check-input perm-check"
              type="checkbox"
              name="permissions[]"
              value="<?= $p['perm_code'] ?>"
              id="perm_<?= $p['perm_code'] ?>">
            <label class="form-check-label">
              <?= htmlspecialchars($p['perm_name']) ?>
            </label>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="modal-footer">
          <button class="btn btn-warning" type="submit">บันทึกสิทธิ์</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // ค้นหาในตารางแบบ real-time
  document.getElementById('searchInput').addEventListener('keyup', function () {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row => {
      const text = row.innerText.toLowerCase();
      row.style.display = text.includes(filter) ? '' : 'none';
    });
  });

  // รีเซ็ตรหัสผ่าน
  document.querySelectorAll('.reset-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('memberID').value = btn.dataset.id;
      document.getElementById('userFullName').innerText = btn.dataset.name;
      new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
    });
  });

  document.getElementById('resetPasswordForm').addEventListener('submit', async e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('admin_reset_password.php', { method: 'POST', body: formData });
    const text = await res.text();
    alert(text);
    location.reload();
  });

  // แก้ไขข้อมูลผู้ใช้
 document.querySelectorAll('.edit-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('editMemberID').value = btn.dataset.id;
    document.getElementById('editName').value = btn.dataset.name;
    document.getElementById('editUsername').value = btn.dataset.username;
    document.getElementById('editPosition').value = btn.dataset.position;
    document.getElementById('editStatus').value = btn.dataset.status;
    document.getElementById('editDocType').value = btn.dataset.doctype || '';

    new bootstrap.Modal(
      document.getElementById('editUserModal')
    ).show();
  });
});


  document.getElementById('editUserForm').addEventListener('submit', async e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('admin_edit_user.php', { method: 'POST', body: formData });
    const text = await res.text();
    alert(text);
    location.reload();
  });

  // ลบผู้ใช้
  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (confirm('ต้องการลบผู้ใช้นี้จริงหรือไม่?')) {
        const memberId = btn.dataset.id;
        const res = await fetch('admin_delete_user.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'member_id=' + encodeURIComponent(memberId)
    + '&csrf_token=<?= $_SESSION['csrf_token'] ?>',
        });
        const text = await res.text();
        alert(text);
        location.reload();
      }
    });
  });
document.querySelectorAll('.perm-btn').forEach(btn => {
  btn.addEventListener('click', async () => {

    const memberId = btn.dataset.id;
    document.getElementById('permMemberID').value = memberId;
    document.getElementById('permUserName').innerText = btn.dataset.name;

    // ❌ ล้างติ๊กทั้งหมดก่อน
    document.querySelectorAll('.perm-check').forEach(cb => cb.checked = false);

    // ✅ โหลดสิทธิ์ของ user
    const res = await fetch(`admin_get_user_permissions.php?member_id=${memberId}`);
    const perms = await res.json();

    // ✅ ติ๊ก checkbox ที่ตรง
    perms.forEach(code => {
      const cb = document.querySelector(`#perm_${code}`);
      if (cb) cb.checked = true;
    });

    new bootstrap.Modal(
      document.getElementById('permissionModal')
    ).show();
  });
});


document.getElementById('permissionForm').addEventListener('submit', async e => {
  e.preventDefault();
  const formData = new FormData(e.target);

  const res = await fetch('admin_save_permissions.php', {
    method: 'POST',
    body: formData
  });

  alert(await res.text());
});

</script>

</body>
</html>
