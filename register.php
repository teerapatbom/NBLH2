<?php
declare(strict_types=1);

require_once "connect.php";
require_once "security.php";

/* =========================
   FETCH DocTypes
========================= */
$stmt = $conn->query("
  SELECT DocTypeID, DocTypeName
  FROM DocTypes
  ORDER BY DocTypeName
");
$docTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   AJAX Backend
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้อง']);
        exit;
    }

    $name       = trim($_POST['name'] ?? '');
    $position   = trim($_POST['position'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $status     = $_POST['status'] ?? 'USER';
    $docTypeID  = trim($_POST['doc_type'] ?? '');

    if ($name === '' || $position === '' || $username === '' || $password === '' || $docTypeID === '') {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบ']);
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        echo json_encode(['status' => 'error', 'message' => 'ชื่อผู้ใช้ไม่ถูกต้อง']);
        exit;
    }

    if (!in_array($status, ['USER', 'ADMIN'], true)) {
        $status = 'USER';
    }

    /* ตรวจ DocType */
    $stmt = $conn->prepare("SELECT 1 FROM DocTypes WHERE DocTypeID = ? LIMIT 1");
    $stmt->execute([$docTypeID]);
    if (!$stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'กลุ่มงานไม่ถูกต้อง']);
        exit;
    }

    /* duplicate username */
    $stmt = $conn->prepare("SELECT 1 FROM member WHERE Username = ? LIMIT 1");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'ชื่อผู้ใช้นี้ถูกใช้ไปแล้ว']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO member
        (Name, Position, Username, Password, Status, DocTypeID, CreatedAt, LoginAttempts, IsLocked)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), 0, 0)
    ");

$ok = $stmt->execute([
    $name,
    $position,
    $username,
    $hash,
    $status,
    $docTypeID
]);

if ($ok) {

    $newMemberID = $conn->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'message' => 'สมัครสมาชิกเรียบร้อยแล้ว',
        'member_id' => $newMemberID
    ]);
    exit;
}
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>สมัครสมาชิก</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(to bottom right, #f8bbd0, #ffffff);
      font-family: 'Sarabun', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .card {
      border-radius: 20px;
      box-shadow: 0 15px 35px rgba(236, 64, 122, 0.2);
      background-color: #fff;
    }

    .btn-primary {
      background: linear-gradient(to right, #ec407a, #f8bbd0);
      border: 2px solid #ec407a;
      font-weight: bold;
    }

    .btn-primary:hover {
      background: linear-gradient(to right, #d81b60, #f48fb1);
      box-shadow: 0 6px 16px rgba(233, 30, 99, 0.3);
    }

    .form-control:focus {
      border-color: #ec407a;
      box-shadow: 0 0 0 0.2rem rgba(236, 64, 122, 0.25);
    }

    h3.card-title {
      color: #ad1457;
      font-weight: 600;
    }

    label {
      color: #880e4f;
      font-weight: 600;
    }

    a {
      color: #ec407a;
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
      color: #d81b60;
    }
.select2-container--classic .select2-selection--single {
  height: 38px;
  padding: 5px 10px;
  border-radius: 8px;
  border: 1.5px solid #ec407a;
}
.select2-results__option--highlighted {
  background-color: #f48fb1 !important;
  color: white;
}
</style>
</head>
<body>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card p-4">
        <div class="card-body">
          <h3 class="card-title text-center mb-4">สมัครสมาชิก</h3>
          <form id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
              <label class="form-label">ชื่อ-สกุลจริง</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">ตำแหน่ง</label>
              <input type="text" name="position" class="form-control" required>
            </div>

<div class="mb-3">
  <label class="form-label">กลุ่มงาน</label>
  <select name="doc_type" id="doc_type" class="form-select" required>
    <option value="">-- เลือกกลุ่มงาน --</option>
    <?php foreach ($docTypes as $dt): ?>
      <option value="<?= htmlspecialchars($dt['DocTypeID']) ?>">
  <?= htmlspecialchars($dt['DocTypeName']) ?> (<?= htmlspecialchars($dt['DocTypeID']) ?>)
</option>

    <?php endforeach; ?>
  </select>
</div>


            <div class="mb-3">
              <label class="form-label">ชื่อผู้ใช้</label>
              <input type="text" name="username" class="form-control" required minlength="3">
            </div>
            <div class="mb-3">
              <label class="form-label">รหัสผ่าน</label>
              <input type="password" name="password" id="password" class="form-control" required minlength="6">
            </div>
            <div class="mb-3">
              <label class="form-label">ยืนยันรหัสผ่าน</label>
              <input type="password" id="confirm_password" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">สิทธิ์ผู้ใช้งาน</label>
              <select name="status" class="form-select" required>
                <option value="USER">USER</option>
                <option value="ADMIN">ADMIN</option>
              </select>
            </div>
            <div id="message" class="mb-3"></div>
            <button type="submit" class="btn btn-primary w-100">สมัครสมาชิก</button>
          </form>
          <hr>
          <p class="text-center">มีบัญชีแล้ว? <a href="index.php">เข้าสู่ระบบ</a></p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  $("#registerForm").submit(function(e){
    e.preventDefault();

    const pass = $("#password").val();
    const confirm = $("#confirm_password").val();
    if(pass !== confirm){
      $("#message").html('<div class="alert alert-danger">รหัสผ่านไม่ตรงกัน</div>');
      return;
    }

    $("#message").html('');
    $.ajax({
      url: "register.php",
      method: "POST",
      data: $(this).serialize(),
      dataType: "json",
      success: function(res){
  if(res.status === 'success'){

    $("#message").html(
      '<div class="alert alert-success">'+res.message+
      '<br><button id="grantPerm" class="btn btn-sm btn-success mt-2">เปิดสิทธิ์เข้าใช้งาน</button></div>'
    );

   $("#grantPerm").click(function(){

  const btn = $(this);
  btn.prop("disabled", true);

  $.post("add_permission.php", {
    member_id: res.member_id,
    csrf_token: $("input[name=csrf_token]").val()
  }, function(r){

    if(r.status === 'success'){
      $("#message").html('<div class="alert alert-success">'+r.message+'</div>');
    } else {
      $("#message").html('<div class="alert alert-danger">'+r.message+'</div>');
      btn.prop("disabled", false);
    }

  }, "json");

});


    $("#registerForm")[0].reset();

  } else {
    $("#message").html('<div class="alert alert-danger">'+res.message+'</div>');
  }
}

    });
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(function () {
  $('#doc_type').select2({
    placeholder: '-- เลือกกลุ่มงาน --',
    allowClear: true,
    width: '100%',
    theme: 'classic'
  });
});
</script>



</body>
</html>
